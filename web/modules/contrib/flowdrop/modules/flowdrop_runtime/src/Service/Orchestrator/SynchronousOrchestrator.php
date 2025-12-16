<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Orchestrator;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop\DTO\Input;
use Drupal\flowdrop\DTO\Config;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext;
use Drupal\flowdrop_workflow\DTO\WorkflowDTO;
use Drupal\flowdrop_workflow\FlowDropWorkflowInterface;
use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;
use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\flowdrop_runtime\Service\Runtime\NodeRuntimeService;
use Drupal\flowdrop_runtime\Service\Runtime\ExecutionContext;
use Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager;
use Drupal\flowdrop_runtime\Service\Compiler\WorkflowCompiler;
use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationRequest;
use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationResponse;
use Drupal\flowdrop_runtime\Exception\OrchestrationException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Synchronous workflow orchestrator with real-time updates.
 */
class SynchronousOrchestrator implements OrchestratorInterface {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(
    private readonly NodeRuntimeService $nodeRuntime,
    private readonly ExecutionContext $executionContext,
    private readonly RealTimeManager $realTimeManager,
    private readonly WorkflowCompiler $workflowCompiler,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'synchronous';
  }

  /**
   * Execute a workflow using WorkflowInterface.
   *
   * @param \Drupal\flowdrop_workflow\FlowDropWorkflowInterface $workflow
   *   The workflow to execute.
   * @param array $initialContext
   *   Initial context data for the workflow execution.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationResponse
   *   The orchestration response.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\OrchestrationException
   *   When orchestration fails.
   */
  public function executeWorkflow(FlowDropWorkflowInterface $workflow, array $initialContext = []): OrchestrationResponse {
    $this->logger->info('Starting workflow execution for @workflow_id', [
      '@workflow_id' => $workflow->id(),
      '@workflow_label' => $workflow->getLabel(),
    ]);

    // Convert workflow interface to array format for orchestration.
    $workflowData = [
      'id' => $workflow->id(),
      'label' => $workflow->getLabel(),
      'description' => $workflow->getDescription(),
      'nodes' => $workflow->getNodes(),
      'edges' => $workflow->getEdges(),
      'metadata' => $workflow->getMetadata(),
    ];

    // Create orchestration request.
    $request = new OrchestrationRequest(
      workflowId: $workflow->id(),
      // @todo Check if there could be a collision
      // due to duplicate names.
      pipelineId: 'pipeline_' . $workflow->id() . '_' . time(),
      workflow: $workflowData,
      initialData: $initialContext,
      options: [],
    );

    // Execute the workflow using the existing orchestrate method.
    return $this->orchestrate($request);
  }

  /**
   * {@inheritdoc}
   */
  public function orchestrate(OrchestrationRequest $request): OrchestrationResponse {
    $workflowData = $request->getWorkflow();
    $executionId = $this->generateExecutionId();

    $this->logger->info('Starting synchronous orchestration for workflow @workflow_id', [
      '@workflow_id' => $request->getWorkflowId(),
      '@execution_id' => $executionId,
    ]);

    try {
      // Convert to WorkflowDTO for normalized access to properties.
      $workflowDTO = WorkflowDTO::fromArray($workflowData);

      // Compile workflow using WorkflowCompiler (accepts both array and DTO).
      $compiledWorkflow = $this->workflowCompiler->compile($workflowDTO);
      $executionPlan = $compiledWorkflow->getExecutionPlan();
      $nodeMappings = $compiledWorkflow->getNodeMappings();

      $this->logger->info('Workflow compiled successfully with @nodes nodes', [
        '@nodes' => count($nodeMappings),
        '@execution_id' => $executionId,
      ]);

      // Build edge metadata for gateway support using WorkflowDTO.
      $edgeMetadata = $this->buildEdgeMetadataFromDto($workflowDTO);

      // Start real-time monitoring (uses array format for compatibility).
      $this->realTimeManager->startMonitoring($executionId, $workflowDTO->toCompilerArray());

      // Update execution status to running.
      $this->realTimeManager->updateExecutionStatus($executionId, 'running', [
        'workflow_id' => $request->getWorkflowId(),
        'pipeline_id' => $request->getPipelineId(),
        'node_count' => count($nodeMappings),
        'start_time' => time(),
      ]);

      // Create execution context.
      $context = $this->executionContext->createContext(
        $request->getWorkflowId(),
        $request->getPipelineId(),
        $request->getInitialData()
      );

      // Get execution order from compiled workflow.
      $executionOrder = $executionPlan->getExecutionOrder();

      $results = [];
      $skippedNodes = [];
      $gatewayOutputs = [];
      $startTime = microtime(TRUE);

      // Execute nodes in compiled execution order with real-time updates.
      // Gateway support: nodes on inactive branches will be skipped.
      foreach ($executionOrder as $nodeId) {
        $nodeMapping = $nodeMappings[$nodeId];
        $nodeDTO = $workflowDTO->getNode($nodeId);

        if ($nodeDTO === NULL) {
          throw new OrchestrationException("Node $nodeId not found in workflow");
        }

        $nodeType = $nodeMapping->getProcessorId();

        // Check if this node should be executed based on gateway outputs.
        // Pass executed node IDs so we can check if non-gateway sources
        // were executed.
        $executedNodeIds = array_keys($results);
        $shouldExecute = $this->shouldExecuteNode(
          $nodeId,
          $gatewayOutputs,
          $edgeMetadata,
          $executedNodeIds
        );
        if (!$shouldExecute) {
          $this->logger->info('Skipping node @node_id (@node_type): branch not active', [
            '@node_id' => $nodeId,
            '@node_type' => $nodeType,
            '@execution_id' => $executionId,
          ]);
          $skippedNodes[] = $nodeId;

          // Emit node skipped event (use DTO array for event compatibility).
          $this->eventDispatcher->dispatch(
            new GenericEvent($nodeDTO->toArray(), [
              'execution_id' => $executionId,
              'workflow_id' => $request->getWorkflowId(),
              'reason' => 'branch_not_active',
            ]),
            'flowdrop.node.skipped'
          );
          continue;
        }

        // Log node start.
        $this->logger->info('Starting execution of node @node_id (@node_type)', [
          '@node_id' => $nodeId,
          '@node_type' => $nodeType,
          '@execution_id' => $executionId,
        ]);

        // Emit pre-execute event (use DTO array for event compatibility).
        $this->eventDispatcher->dispatch(
          new GenericEvent($nodeDTO->toArray(), [
            'execution_id' => $executionId,
            'workflow_id' => $request->getWorkflowId(),
            'context' => $context,
          ]),
          'flowdrop.node.pre_execute'
        );

        try {
          $nodeInputs = $this->prepareNodeInputsFromPlan($context, $nodeId, $executionPlan);

          // Create proper Input and Config objects.
          $inputs = new Input();
          $inputs->fromArray($nodeInputs);

          $config = new Config();
          $config->fromArray($nodeMapping->getConfig());

          $result = $this->nodeRuntime->executeNode(
            $executionId,
            $nodeId,
            $nodeType,
            $inputs,
            $config,
            $context
          );

          $results[$nodeId] = $result;

          // Update context with result.
          $this->executionContext->updateContext($context, $nodeId, $result->getOutput());

          // Capture gateway outputs for downstream branch decisions.
          $outputData = $result->getOutput()->toArray();
          if (isset($outputData['active_branches'])) {
            $gatewayOutputs[$nodeId] = $outputData['active_branches'];
            $this->logger->info('Gateway node @node_id activated branches: @branches', [
              '@node_id' => $nodeId,
              '@branches' => $outputData['active_branches'],
              '@execution_id' => $executionId,
            ]);
          }

          // Log node success.
          $this->logger->info('Node @node_id (@node_type) completed successfully', [
            '@node_id' => $nodeId,
            '@node_type' => $nodeType,
            '@execution_id' => $executionId,
            '@execution_time' => $result->getExecutionTime(),
          ]);

          // Emit post-execute event (use DTO array for event compatibility).
          $this->eventDispatcher->dispatch(
            new GenericEvent($nodeDTO->toArray(), [
              'execution_id' => $executionId,
              'workflow_id' => $request->getWorkflowId(),
              'context' => $context,
              'result' => $result,
            ]),
            'flowdrop.node.post_execute'
          );

        }
        catch (\Exception $e) {
          // Log node failure.
          $this->logger->error('Node @node_id (@node_type) execution failed: @error', [
            '@node_id' => $nodeId,
            '@node_type' => $nodeType,
            '@execution_id' => $executionId,
            '@error' => $e->getMessage(),
          ]);

          // Emit post-execute event with error (use DTO array for event
          // compatibility).
          $this->eventDispatcher->dispatch(
            new GenericEvent($nodeDTO->toArray(), [
              'execution_id' => $executionId,
              'workflow_id' => $request->getWorkflowId(),
              'context' => $context,
              'error' => $e->getMessage(),
            ]),
            'flowdrop.node.post_execute'
          );

          throw $e;
        }
      }

      $totalTime = microtime(TRUE) - $startTime;

      // Update execution status to completed.
      $this->realTimeManager->updateExecutionStatus($executionId, 'completed', [
        'total_execution_time' => $totalTime,
        'nodes_executed' => count($results),
        'nodes_skipped' => count($skippedNodes),
        'end_time' => time(),
      ]);

      $this->logger->info('Synchronous orchestration completed in @time seconds (@executed executed, @skipped skipped)', [
        '@time' => round($totalTime, 3),
        '@executed' => count($results),
        '@skipped' => count($skippedNodes),
        '@execution_id' => $executionId,
      ]);

      // Emit workflow completed event (use DTO array for event compatibility).
      $this->eventDispatcher->dispatch(
        new GenericEvent($workflowDTO->toCompilerArray(), [
          'execution_id' => $executionId,
          'workflow_id' => $request->getWorkflowId(),
          'results' => $results,
          'skipped_nodes' => $skippedNodes,
          'execution_time' => $totalTime,
          'context' => $context,
        ]),
        'flowdrop.workflow.completed'
      );

      return new OrchestrationResponse(
        executionId: $executionId,
        status: 'completed',
        results: $results,
        executionTime: $totalTime,
        metadata: [
          'orchestrator_type' => $this->getType(),
          'nodes_executed' => count($results),
          'nodes_skipped' => count($skippedNodes),
          'skipped_node_ids' => $skippedNodes,
          'compiled_workflow_id' => $compiledWorkflow->getWorkflowId(),
        ]
      );

    }
    catch (\Exception $e) {
      // Update execution status to failed.
      $this->realTimeManager->updateExecutionStatus($executionId, 'failed', [
        'error' => $e->getMessage(),
        'end_time' => time(),
      ]);

      $this->logger->error('Synchronous orchestration failed: @error', [
        '@error' => $e->getMessage(),
        '@execution_id' => $executionId,
      ]);

      throw new OrchestrationException(
        "Synchronous orchestration failed: " . $e->getMessage(),
        0,
        $e
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsWorkflow(array $workflow): bool {
    // Synchronous orchestrator supports all workflows.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCapabilities(): array {
    return [
      'synchronous_execution' => TRUE,
      'parallel_execution' => FALSE,
      'async_execution' => FALSE,
      'retry_support' => TRUE,
      'error_recovery' => TRUE,
      'workflow_compilation' => TRUE,
    ];
  }

  /**
   * Generate a unique execution ID.
   */
  private function generateExecutionId(): string {
    return 'exec_' . time() . '_' . uniqid();
  }

  /**
   * Find a node by ID in the workflow nodes array.
   *
   * @param array $nodes
   *   The workflow nodes array.
   * @param string $nodeId
   *   The node ID to find.
   *
   * @return array|null
   *   The node array or NULL if not found.
   */
  private function findNodeById(array $nodes, string $nodeId): ?array {
    foreach ($nodes as $node) {
      if ($node['id'] === $nodeId) {
        return $node;
      }
    }
    return NULL;
  }

  /**
   * Prepare inputs for a node based on the execution plan.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext $context
   *   The execution context.
   * @param string $nodeId
   *   The node ID.
   * @param \Drupal\flowdrop_runtime\DTO\Compiler\ExecutionPlan $executionPlan
   *   The execution plan.
   *
   * @return array
   *   The prepared node inputs.
   */
  private function prepareNodeInputsFromPlan($context, string $nodeId, $executionPlan): array {
    $inputMappings = $executionPlan->getInputMappings();
    $nodeInputMappings = $inputMappings[$nodeId] ?? [];

    $inputData = $context->getInitialData();

    // Add outputs from dependent nodes based on execution plan.
    foreach ($nodeInputMappings as $dependencyId => $mapping) {
      $dependencyOutput = $context->getNodeOutput($dependencyId);
      if ($dependencyOutput) {
        $inputData = array_merge($inputData, $dependencyOutput->toArray());
      }
    }

    return $inputData;
  }

  /**
   * Build edge metadata from WorkflowDTO for gateway support.
   *
   * This uses the WorkflowDTO's edge objects which already have
   * parsed trigger and branch information.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Workflow\WorkflowDTO $workflow
   *   The workflow DTO.
   *
   * @return array<string, array<string, array<int, array<string, mixed>>>>
   *   Edge metadata keyed by node ID with 'incoming' and 'outgoing' arrays.
   */
  private function buildEdgeMetadataFromDto(WorkflowDTO $workflow): array {
    $edgeMetadata = [];

    // Initialize empty edge arrays for all nodes.
    foreach ($workflow->getNodes() as $nodeId => $node) {
      $edgeMetadata[$nodeId] = [
        'incoming' => [],
        'outgoing' => [],
      ];
    }

    // Process each edge using WorkflowEdgeDTO methods.
    foreach ($workflow->getEdges() as $edge) {
      $sourceId = $edge->getSource();
      $targetId = $edge->getTarget();

      if (empty($sourceId) || empty($targetId)) {
        continue;
      }

      // Use the DTO's built-in methods for trigger and branch detection.
      $edgeInfo = [
        'source' => $sourceId,
        'target' => $targetId,
        'source_handle' => $edge->getSourceHandle(),
        'target_handle' => $edge->getTargetHandle(),
        'is_trigger' => $edge->isTrigger(),
        'branch_name' => $edge->getBranchName(),
        'edge_id' => $edge->getId(),
      ];

      // Store incoming edge for target node.
      if (isset($edgeMetadata[$targetId])) {
        $edgeMetadata[$targetId]['incoming'][] = $edgeInfo;
      }

      // Store outgoing edge for source node.
      if (isset($edgeMetadata[$sourceId])) {
        $edgeMetadata[$sourceId]['outgoing'][] = $edgeInfo;
      }
    }

    return $edgeMetadata;
  }

  /**
   * Build edge metadata from workflow array for gateway support.
   *
   * This is kept for backward compatibility with code that may pass
   * raw workflow arrays directly. Prefer using buildEdgeMetadataFromDto()
   * with WorkflowDTO for new code.
   *
   * @param array<string, mixed> $workflow
   *   The workflow definition array.
   *
   * @return array<string, array<string, array<int, array<string, mixed>>>>
   *   Edge metadata keyed by node ID with 'incoming' and 'outgoing' arrays.
   */
  private function buildEdgeMetadata(array $workflow): array {
    // Convert to DTO and use the new method.
    $workflowDTO = WorkflowDTO::fromArray($workflow);
    return $this->buildEdgeMetadataFromDto($workflowDTO);
  }

  /**
   * Determine if a node should execute based on gateway outputs.
   *
   * This implements gateway/branch support by checking:
   * 1. If the node has trigger inputs, at least one trigger must be satisfied
   * 2. A trigger from a gateway is satisfied when its active_branches matches
   *    the edge's branch_name
   * 3. A trigger from a non-gateway is satisfied if the source was executed
   * 4. Nodes without trigger inputs always execute (dependency-only control)
   *
   * @param string $nodeId
   *   The node ID to check.
   * @param array<string, string> $gatewayOutputs
   *   Map of gateway node IDs to their active_branches output.
   * @param array<string, array<string, array<int, array<string, mixed>>>> $edgeMetadata
   *   Edge metadata from buildEdgeMetadata().
   * @param array<string> $executedNodeIds
   *   Array of node IDs that have been executed so far.
   *
   * @return bool
   *   TRUE if the node should execute, FALSE if it should be skipped.
   */
  private function shouldExecuteNode(
    string $nodeId,
    array $gatewayOutputs,
    array $edgeMetadata,
    array $executedNodeIds,
  ): bool {
    $incomingEdges = $edgeMetadata[$nodeId]['incoming'] ?? [];

    // Filter to only trigger edges.
    $triggerEdges = array_filter($incomingEdges, function (array $edge): bool {
      return $edge['is_trigger'] ?? FALSE;
    });

    // If no trigger edges, node executes based on data dependencies only.
    if (empty($triggerEdges)) {
      return TRUE;
    }

    // With trigger edges, at least ONE trigger must be satisfied.
    foreach ($triggerEdges as $triggerEdge) {
      $sourceId = $triggerEdge['source'];
      $branchName = $triggerEdge['branch_name'] ?? '';

      // Check if source is a gateway that has been executed.
      if (isset($gatewayOutputs[$sourceId])) {
        // Source is a gateway - check branch matching.
        $activeBranches = $gatewayOutputs[$sourceId];

        // If no specific branch required, any completion satisfies.
        if ($branchName === '') {
          return TRUE;
        }

        // Check if the branch name matches any active branch.
        // active_branches can be comma-separated for multiple active branches.
        $activeBranchList = array_map('trim', explode(',', strtolower($activeBranches)));

        if (in_array(strtolower($branchName), $activeBranchList, TRUE)) {
          $this->logger->debug('Trigger satisfied for node @node_id: branch @branch matches active @active', [
            '@node_id' => $nodeId,
            '@branch' => $branchName,
            '@active' => $activeBranches,
          ]);
          return TRUE;
        }
      }
      else {
        // Source is NOT a gateway - trigger is satisfied if source was
        // executed.
        if (in_array($sourceId, $executedNodeIds, TRUE)) {
          $this->logger->debug('Trigger satisfied for node @node_id: non-gateway source @source was executed', [
            '@node_id' => $nodeId,
            '@source' => $sourceId,
          ]);
          return TRUE;
        }
      }
    }

    // No trigger was satisfied.
    $this->logger->debug('No triggers satisfied for node @node_id', [
      '@node_id' => $nodeId,
    ]);
    return FALSE;
  }

  /**
   * Execute a pipeline by running all ready jobs synchronously.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to execute.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationResponse
   *   The orchestration response.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\OrchestrationException
   *   When execution fails.
   */
  public function executePipeline(FlowDropPipelineInterface $pipeline): OrchestrationResponse {
    $execution_id = $this->generateExecutionId();
    $startTime = microtime(TRUE);

    $this->logger->info('Starting synchronous pipeline execution for pipeline @pipeline_id', [
      '@pipeline_id' => $pipeline->id(),
      '@execution_id' => $execution_id,
    ]);

    try {
      // Mark pipeline as running.
      $pipeline->markAsStarted();
      $pipeline->save();

      $results = [];
      $executed_jobs = [];
      // Prevent infinite loops.
      $max_iterations = 100;
      $iteration = 0;

      // Keep executing ready jobs until none are left or max iterations
      // reached.
      while ($iteration < $max_iterations) {
        $ready_jobs = $pipeline->getReadyJobs();

        if (empty($ready_jobs)) {
          // No more ready jobs, check if we're done.
          break;
        }

        $this->logger->info('Found @count ready jobs in iteration @iteration', [
          '@count' => count($ready_jobs),
          '@iteration' => $iteration,
          '@execution_id' => $execution_id,
        ]);

        // Execute all ready jobs in this iteration.
        foreach ($ready_jobs as $job) {
          $job_result = $this->executeJob($job, $pipeline, $execution_id);
          $results[$job->getNodeId()] = $job_result;
          $executed_jobs[] = $job->id();
        }

        $iteration++;
      }

      // Check final pipeline status.
      // First, check if there are any failed jobs.
      if ($pipeline->hasFailedJobs()) {
        $pipeline->markAsFailed('Some jobs failed during execution');
        $status = 'failed';
      }
      // If we hit max iterations, pause the pipeline for investigation.
      // This could indicate circular dependencies or a complex workflow
      // that needs more iterations.
      elseif ($iteration >= $max_iterations) {
        $pipeline->pause();
        $status = 'paused';
        $this->logger->warning('Pipeline @pipeline_id paused after reaching max iterations (@max). Possible circular dependencies or complex workflow structure.', [
          '@pipeline_id' => $pipeline->id(),
          '@max' => $max_iterations,
          '@execution_id' => $execution_id,
        ]);
      }
      // Otherwise, if execution completed naturally (no more ready jobs),
      // mark as completed. Jobs on inactive conditional branches remain
      // pending, which is expected behavior.
      else {
        $pipeline->markAsCompleted($results);
        $status = 'completed';
      }

      $pipeline->save();

      $executionTime = microtime(TRUE) - $startTime;

      $this->logger->info('Synchronous pipeline execution completed for pipeline @pipeline_id in @time seconds', [
        '@pipeline_id' => $pipeline->id(),
        '@time' => round($executionTime, 3),
        '@status' => $status,
        '@executed_jobs' => count($executed_jobs),
      ]);

      return new OrchestrationResponse(
        executionId: $execution_id,
        status: $status,
        results: $results,
        executionTime: $executionTime,
        metadata: [
          'pipeline_id' => $pipeline->id(),
          'executed_jobs' => $executed_jobs,
          'iterations' => $iteration,
          'total_jobs' => count($pipeline->getJobs()),
        ]
      );

    }
    catch (\Exception $e) {
      $pipeline->markAsFailed('Pipeline execution failed: ' . $e->getMessage());
      $pipeline->save();

      $this->logger->error('Synchronous pipeline execution failed for pipeline @pipeline_id: @message', [
        '@pipeline_id' => $pipeline->id(),
        '@message' => $e->getMessage(),
        '@execution_id' => $execution_id,
      ]);

      throw new OrchestrationException(
        'Synchronous pipeline execution failed: ' . $e->getMessage(),
        0,
        $e
      );
    }
  }

  /**
   * Execute a single job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to execute.
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline this job belongs to.
   * @param string $execution_id
   *   The execution ID for logging.
   *
   * @return array
   *   The job execution result.
   */
  protected function executeJob(FlowDropJobInterface $job, FlowDropPipelineInterface $pipeline, string $execution_id): array {
    $this->logger->info('Executing job @job_id (node: @node_id)', [
      '@job_id' => $job->id(),
      '@node_id' => $job->getNodeId(),
      '@execution_id' => $execution_id,
    ]);

    try {
      // Mark job as started.
      $job->markAsStarted();
      $job->save();

      // Prepare execution context.
      // Start with config/static input data from the job.
      $job_data = $job->getInputData();

      // Extract config (static configuration from workflow node).
      // Config keys are typically at the root level of node data.
      $config_data = $job_data['config'] ?? $job_data;

      // Extract input data (excluding config).
      $input_data = $job_data['inputs'] ?? [];

      // Build runtime input data from incoming edges.
      $runtime_input_data = $this->buildRuntimeInputData($job, $pipeline);

      // Merge: runtime data takes precedence over static inputs.
      $input_data = array_merge($input_data, $runtime_input_data);

      $input_dto = new Input($input_data);
      $config_dto = new Config($config_data);

      $context = new NodeExecutionContext(
        workflowId: 'pipeline_' . $job->id(),
        pipelineId: 'pipeline_' . $job->id(),
        initialData: $input_data
      );

      // Get node type for execution.
      $node_type_id = $job->getMetadataValue('node_type_id', 'default');

      // Execute the job using node runtime.
      $result = $this->nodeRuntime->executeNode(
        $execution_id,
        $job->getNodeId(),
        $node_type_id,
        $input_dto,
        $config_dto,
        $context
      );

      // Mark job as completed.
      $output_data = $result->getOutput()->toArray();
      $job->markAsCompleted($output_data);
      $job->save();

      $this->logger->info('Job @job_id completed successfully', [
        '@job_id' => $job->id(),
        '@execution_id' => $execution_id,
      ]);

      return $output_data;

    }
    catch (\Exception $e) {
      // Mark job as failed.
      $job->markAsFailed($e->getMessage());
      $job->save();

      $this->logger->error('Job @job_id failed: @message', [
        '@job_id' => $job->id(),
        '@message' => $e->getMessage(),
        '@execution_id' => $execution_id,
      ]);

      // Re-throw for pipeline-level handling.
      throw $e;
    }
  }

  /**
   * Build runtime input data for a job from incoming edges.
   *
   * This method implements data flow by:
   * 1. Finding all incoming data edges (excluding triggers)
   * 2. Reading output data from source jobs
   * 3. Mapping source outputs to target inputs based on port names.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to build input data for.
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline this job belongs to.
   *
   * @return array
   *   Runtime input data mapped by port names.
   */
  protected function buildRuntimeInputData(FlowDropJobInterface $job, FlowDropPipelineInterface $pipeline): array {
    $runtime_data = [];

    // Get incoming edges from job metadata.
    $metadata = $job->getMetadata();
    $incoming_edges = $metadata['incoming_edges'] ?? [];

    $completed_jobs = $pipeline->getJobsByStatus('completed');
    $completed_jobs_map = [];
    foreach ($completed_jobs as $completed_job) {
      $completed_jobs_map[$completed_job->getNodeId()] = $completed_job;
    }

    // Process each incoming edge.
    foreach ($incoming_edges as $edge) {
      // Skip trigger edges - they're only for execution control.
      if ($edge['is_trigger'] ?? FALSE) {
        continue;
      }

      $source_node_id = $edge['source'];
      $source_handle = $edge['source_handle'] ?? '';
      $target_handle = $edge['target_handle'] ?? '';

      // Check if source job is completed.
      if (!isset($completed_jobs_map[$source_node_id])) {
        $this->logger->debug('Source job @source not completed for data flow to job @job', [
          '@source' => $source_node_id,
          '@job' => $job->id(),
        ]);
        continue;
      }

      $source_job = $completed_jobs_map[$source_node_id];
      $source_output = $source_job->getOutputData();

      // Parse port names from handles.
      // Format: {nodeId}-output-{portName} and {nodeId}-input-{portName}.
      $source_port_name = $this->extractPortName($source_handle, 'output');
      $target_port_name = $this->extractPortName($target_handle, 'input');

      if ($source_port_name && $target_port_name) {
        // Map source output to target input.
        if (isset($source_output[$source_port_name])) {
          $runtime_data[$target_port_name] = $source_output[$source_port_name];

          $this->logger->debug('Data flow: job @source[@source_port] -> job @target[@target_port]', [
            '@source' => $source_job->id(),
            '@source_port' => $source_port_name,
            '@target' => $job->id(),
            '@target_port' => $target_port_name,
          ]);
        }
      }
    }

    return $runtime_data;
  }

  /**
   * Extract port name from a handle.
   *
   * Handles have format: {nodeId}-{direction}-{portName}
   * Example: "abc123-output-data" -> "data"
   *
   * @param string $handle
   *   The handle string.
   * @param string $direction
   *   The direction ('output' or 'input').
   *
   * @return string|null
   *   The port name, or NULL if parsing failed.
   */
  protected function extractPortName(string $handle, string $direction): ?string {
    if (empty($handle)) {
      return NULL;
    }

    // Find the pattern: -{direction}-.
    $pattern = '/-' . $direction . '-/';
    $parts = preg_split($pattern, $handle, 2);

    if (count($parts) === 2) {
      // The port name is everything after -{direction}-.
      return $parts[1];
    }

    return NULL;
  }

}
