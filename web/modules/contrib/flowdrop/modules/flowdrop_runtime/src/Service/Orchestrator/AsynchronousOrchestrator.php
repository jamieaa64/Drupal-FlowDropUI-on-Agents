<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Orchestrator;

use Psr\Log\LoggerInterface;

use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationRequest;
use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationResponse;
use Drupal\flowdrop_runtime\Exception\OrchestrationException;
use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;
use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;
use Drupal\flowdrop_workflow\Exception\WorkflowExecutionException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Asynchronous workflow orchestrator with queue-based execution.
 *
 * This orchestrator provides pipeline and job management through Drupal's
 * queue system, enabling background processing and scalable execution.
 */
class AsynchronousOrchestrator implements OrchestratorInterface {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->logger = $loggerFactory->get("flowdrop_runtime");
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return "asynchronous";
  }

  /**
   * {@inheritdoc}
   */
  public function orchestrate(OrchestrationRequest $request): OrchestrationResponse {
    // Use pipeline ID as execution ID.
    $execution_id = $request->getPipelineId();
    $workflow_array = $request->getWorkflow();

    $this->logger->info("Starting asynchronous orchestration", [
      "execution_id" => $execution_id,
      "workflow_id" => $request->getWorkflowId(),
    ]);

    try {
      // Create pipeline from workflow.
      $pipeline = $this->createPipelineFromWorkflow(
        $workflow_array,
        $request->getInitialData(),
        $request->getOptions()
      );

      if (!$pipeline) {
        throw new OrchestrationException("Failed to create pipeline from workflow");
      }

      // Start pipeline execution.
      $this->startPipeline($pipeline);

      // Return response with pipeline information.
      return new OrchestrationResponse(
        $execution_id,
        "queued",
        [
          "pipeline_id" => $pipeline->id(),
          "status" => $pipeline->getStatus(),
          "job_counts" => $pipeline->calculateJobCounts(),
        ],
        // Execution time (will be updated when completed)
        0.0,
        [
          "message" => "Pipeline queued for asynchronous execution",
          "pipeline_id" => $pipeline->id(),
        ]
      );

    }
    catch (\Exception $e) {
      $this->logger->error("Asynchronous orchestration failed", [
        "execution_id" => $execution_id,
        "error" => $e->getMessage(),
      ]);

      throw new OrchestrationException(
         "Asynchronous orchestration failed: " . $e->getMessage(),
         0,
         $e
       );
    }
  }

  /**
   * Create a pipeline from a workflow.
   *
   * @param array $workflow
   *   The workflow definition array.
   * @param array $initial_data
   *   The initial data.
   * @param array $options
   *   The configuration options.
   *
   * @return \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface|null
   *   The created pipeline or NULL if creation failed.
   */
  public function createPipelineFromWorkflow(
    array $workflow,
    array $initial_data,
    array $options,
  ): ?FlowDropPipelineInterface {
    try {
      // Create the pipeline.
      $workflow_label = $workflow['label'] ?? $workflow['id'] ?? 'Unknown Workflow';
      $pipeline = $this->entityTypeManager->getStorage("flowdrop_pipeline")->create([
        "label" => $workflow_label . " - Pipeline " . date("Y-m-d H:i:s"),
        "bundle" => "default",
        "workflow_id" => ["target_id" => $workflow['id']],
        "input_data" => json_encode($initial_data),
        "status" => "pending",
        "max_concurrent_jobs" => $options["max_concurrent_jobs"] ?? 5,
        "job_priority_strategy" => $options["job_priority_strategy"] ?? "dependency_order",
        "retry_strategy" => $options["retry_strategy"] ?? "individual",
      ]);

      $pipeline->save();

      // Dispatch pipeline created event.
      $this->eventDispatcher->dispatch(new GenericEvent($pipeline, [
        "workflow_id" => $workflow['id'],
        "input_data" => $initial_data,
        "config" => $options,
      ]), "flowdrop.pipeline.created");

      $this->logger->info("Pipeline @id created from workflow @workflow_id", [
        "@id" => $pipeline->id(),
        "@workflow_id" => $workflow['id'],
      ]);

      return $pipeline instanceof FlowDropPipelineInterface ? $pipeline : NULL;

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to create pipeline from workflow @id: @message", [
        "@id" => $workflow['id'],
        "@message" => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Start a pipeline execution.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to start.
   *
   * @return bool
   *   TRUE if the pipeline was started successfully.
   */
  public function startPipeline(FlowDropPipelineInterface $pipeline): bool {
    try {
      if (!$pipeline->isReadyToStart()) {
        $this->logger->warning("Pipeline @id is not ready to start (status: @status)", [
          "@id" => $pipeline->id(),
          "@status" => $pipeline->getStatus(),
        ]);
        return FALSE;
      }

      // Mark pipeline as started.
      $pipeline->markAsStarted();
      $pipeline->save();

      // Check if pipeline has jobs.
      $jobs = $pipeline->getJobs();
      if (empty($jobs)) {
        throw new \RuntimeException("Pipeline has no jobs. Please generate jobs first.");
      }

      // Dispatch pipeline started event.
      $this->eventDispatcher->dispatch(new GenericEvent($pipeline), "flowdrop.pipeline.started");

      $this->logger->info("Pipeline @id started successfully with @job_count jobs", [
        "@id" => $pipeline->id(),
        "@job_count" => count($jobs),
      ]);

      // Queue the pipeline for execution.
      $this->queuePipelineExecution($pipeline);

      return TRUE;

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to start pipeline @id: @message", [
        "@id" => $pipeline->id(),
        "@message" => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Create jobs from workflow nodes.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to create jobs for.
   */
  protected function createJobsFromWorkflow(FlowDropPipelineInterface $pipeline): void {
    $workflow = $pipeline->getWorkflow();
    if (!$workflow) {
      throw new \RuntimeException("Workflow not found for pipeline " . $pipeline->id());
    }

    $nodes = $workflow->getNodes();
    $edges = $workflow->getEdges();

    // Build dependency graph.
    $dependencies = $this->buildDependencyGraph($nodes, $edges);

    // Create jobs first (without entity reference dependencies).
    // Map node IDs to job entities.
    $node_to_job_map = [];

    foreach ($nodes as $node) {
      // Get the executor plugin ID from the node type.
      $executor_plugin_id = $this->getExecutorPluginIdForNode($node);

      $job = $this->entityTypeManager->getStorage("flowdrop_job")->create([
        "label" => $node["data"]["label"] ?? "Job " . $node["id"],
        "bundle" => "default",
        "metadata" => json_encode([
          "node_id" => $node["id"],
          "executor_plugin_id" => $executor_plugin_id,
        ]),
        "status" => "pending",
        "priority" => $this->calculateJobPriority($node, $dependencies),
        "input_data" => json_encode($node["data"] ?? []),
        "max_retries" => 3,
        "executor_plugin_id" => $executor_plugin_id,
      ]);

      $job->save();

      // Store mapping for dependency resolution.
      $node_to_job_map[$node["id"]] = $job;

      // Add job to pipeline's job_id field.
      assert($job instanceof FlowDropJobInterface);
      $pipeline->addJob($job);

      // Dispatch job created event.
      $this->eventDispatcher->dispatch(new GenericEvent($job, [
        "pipeline_id" => $pipeline->id(),
        "node_id" => $node["id"],
      ]), "flowdrop.job.created");
    }

    // Now establish entity reference dependencies between jobs.
    foreach ($dependencies as $node_id => $dependency_node_ids) {
      if (empty($dependency_node_ids)) {
        // No dependencies for this node.
        continue;
      }

      $job = $node_to_job_map[$node_id];
      assert($job instanceof FlowDropJobInterface);

      // Add each dependency as an entity reference.
      foreach ($dependency_node_ids as $dependency_node_id) {
        if (isset($node_to_job_map[$dependency_node_id])) {
          $dependency_job = $node_to_job_map[$dependency_node_id];
          assert($dependency_job instanceof FlowDropJobInterface);
          $job->addDependentJob($dependency_job);
        }
      }

      // Save the job with its new dependencies.
      $job->save();
    }

    // Save pipeline with all added jobs.
    $pipeline->save();
  }

  /**
   * Build dependency graph from workflow nodes and edges.
   *
   * @param array $nodes
   *   The workflow nodes.
   * @param array $edges
   *   The workflow edges.
   *
   * @return array
   *   The dependency graph.
   */
  protected function buildDependencyGraph(array $nodes, array $edges): array {
    $dependencies = [];

    // Initialize dependencies for all nodes.
    foreach ($nodes as $node) {
      $dependencies[$node["id"]] = [];
    }

    // Build dependencies from edges.
    foreach ($edges as $edge) {
      $source_id = $edge["source"];
      $target_id = $edge["target"];

      if (isset($dependencies[$target_id])) {
        $dependencies[$target_id][] = $source_id;
      }
    }

    return $dependencies;
  }

  /**
   * Calculate job priority based on dependencies and node type.
   *
   * @param array $node
   *   The workflow node.
   * @param array $dependencies
   *   The dependency graph.
   *
   * @return int
   *   The calculated priority (lower = higher priority).
   */
  protected function calculateJobPriority(array $node, array $dependencies): int {
    $priority = 0;

    // Higher priority for nodes with fewer dependencies.
    $dependency_count = count($dependencies[$node["id"]] ?? []);
    $priority += $dependency_count * 10;

    // Higher priority for input nodes.
    if (isset($node["data"]["type"]) && strpos($node["data"]["type"], "input") !== FALSE) {
      $priority -= 50;
    }

    // Higher priority for output nodes.
    if (isset($node["data"]["type"]) && strpos($node["data"]["type"], "output") !== FALSE) {
      $priority += 50;
    }

    return $priority;
  }

  /**
   * Get the executor plugin ID for a node.
   *
   * @param array $node
   *   The workflow node.
   *
   * @return string
   *   The executor plugin ID.
   */
  protected function getExecutorPluginIdForNode(array $node): string {
    $node_type_id = $node["data"]["type"] ?? "unknown";

    // Try to load the node type entity to get the executor plugin.
    $node_type = $this->entityTypeManager->getStorage("flowdrop_node_type")->load($node_type_id);

    if ($node_type instanceof FlowDropNodeTypeInterface && $node_type->getExecutorPlugin()) {
      return $node_type->getExecutorPlugin();
    }

    throw new WorkflowExecutionException(
      "No executor plugin ID found for node type: $node_type_id"
    );
  }

  /**
   * Queue pipeline execution.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to queue.
   */
  protected function queuePipelineExecution(FlowDropPipelineInterface $pipeline): void {
    $queue = $this->queueFactory->get("flowdrop_runtime_pipeline_execution");
    $queue->createItem([
      "pipeline_id" => $pipeline->id(),
      "action" => "execute",
      "timestamp" => time(),
    ]);
  }

  /**
   * Execute pipeline (called from queue worker).
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to execute.
   */
  public function executePipeline(FlowDropPipelineInterface $pipeline): void {
    try {
      if (!$pipeline->isRunning()) {
        $this->logger->warning("Pipeline @id is not running (status: @status)", [
          "@id" => $pipeline->id(),
          "@status" => $pipeline->getStatus(),
        ]);
        return;
      }

      // Get ready jobs.
      $ready_jobs = $pipeline->getReadyJobs();
      $running_jobs = $pipeline->getJobsByStatus("running");
      $max_concurrent = $pipeline->getMaxConcurrentJobs();

      // Start new jobs if we have capacity.
      $available_slots = $max_concurrent - count($running_jobs);
      $jobs_to_start = array_slice($ready_jobs, 0, $available_slots);

      foreach ($jobs_to_start as $job) {
        $this->startJob($job);
      }

      // Check if pipeline is complete.
      if ($pipeline->allJobsCompleted()) {
        $this->completePipeline($pipeline);
      }
      elseif ($pipeline->hasFailedJobs() && $pipeline->getRetryStrategy() === "stop_on_failure") {
        $this->failPipeline($pipeline, "Pipeline failed due to job failure with stop_on_failure strategy");
      }

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to execute pipeline @id: @message", [
        "@id" => $pipeline->id(),
        "@message" => $e->getMessage(),
      ]);
      $this->failPipeline($pipeline, "Pipeline execution failed: " . $e->getMessage());
    }
  }

  /**
   * Start a job execution.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to start.
   */
  protected function startJob(FlowDropJobInterface $job): void {
    try {
      $job->markAsStarted();
      $job->save();

      // Job counts are now calculated dynamically by pipelines.
      // Dispatch job started event.
      $this->eventDispatcher->dispatch(new GenericEvent($job), "flowdrop.job.started");

      // Queue job execution.
      $queue = $this->queueFactory->get("flowdrop_runtime_job_execution");
      $queue->createItem([
        "job_id" => $job->id(),
        "action" => "execute",
        "timestamp" => time(),
      ]);

      $this->logger->info("Job @id started", [
        "@id" => $job->id(),
      ]);

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to start job @id: @message", [
        "@id" => $job->id(),
        "@message" => $e->getMessage(),
      ]);
      $job->markAsFailed($e->getMessage());
      $job->save();
    }
  }

  /**
   * Complete a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to complete.
   */
  protected function completePipeline(FlowDropPipelineInterface $pipeline): void {
    try {
      // Aggregate output data from completed jobs.
      $output_data = $this->aggregatePipelineOutput($pipeline);

      $pipeline->markAsCompleted($output_data);
      $pipeline->save();

      // Dispatch pipeline completed event.
      $this->eventDispatcher->dispatch(new GenericEvent($pipeline, [
        "output_data" => $output_data,
      ]), "flowdrop.pipeline.completed");

      $this->logger->info("Pipeline @id completed successfully", ["@id" => $pipeline->id()]);

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to complete pipeline @id: @message", [
        "@id" => $pipeline->id(),
        "@message" => $e->getMessage(),
      ]);
    }
  }

  /**
   * Fail a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to fail.
   * @param string $error_message
   *   The error message.
   */
  protected function failPipeline(FlowDropPipelineInterface $pipeline, string $error_message = ""): void {
    try {
      $pipeline->markAsFailed($error_message);
      $pipeline->save();

      // Dispatch pipeline failed event.
      $this->eventDispatcher->dispatch(new GenericEvent($pipeline, [
        "error_message" => $error_message,
      ]), "flowdrop.pipeline.failed");

      $this->logger->error("Pipeline @id failed: @message", [
        "@id" => $pipeline->id(),
        "@message" => $error_message,
      ]);

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to mark pipeline @id as failed: @message", [
        "@id" => $pipeline->id(),
        "@message" => $e->getMessage(),
      ]);
    }
  }

  /**
   * Aggregate output data from completed jobs.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to aggregate output for.
   *
   * @return array
   *   The aggregated output data.
   */
  protected function aggregatePipelineOutput(FlowDropPipelineInterface $pipeline): array {
    $output_data = [];
    $completed_jobs = $pipeline->getJobsByStatus("completed");

    foreach ($completed_jobs as $job) {
      $job_output = $job->getOutputData();
      $node_id = $job->getNodeId();

      if (!empty($job_output)) {
        $output_data[$node_id] = $job_output;
      }
    }

    return $output_data;
  }

  /**
   * Handle job completion.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The completed job.
   * @param array $output_data
   *   The job output data.
   */
  public function handleJobCompletion(FlowDropJobInterface $job, array $output_data = []): void {
    try {
      $job->markAsCompleted($output_data);
      $job->save();

      // Job counts are now calculated dynamically by pipelines.
      // Dispatch job completed event.
      $this->eventDispatcher->dispatch(new GenericEvent($job, [
        "output_data" => $output_data,
      ]), "flowdrop.job.completed");

      // Pipeline re-evaluation is handled separately.
      $this->logger->info("Job @id completed", [
        "@id" => $job->id(),
      ]);

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to handle job completion @id: @message", [
        "@id" => $job->id(),
        "@message" => $e->getMessage(),
      ]);
    }
  }

  /**
   * Handle job failure.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The failed job.
   * @param string $error_message
   *   The error message.
   */
  public function handleJobFailure(FlowDropJobInterface $job, string $error_message = ""): void {
    try {
      $job->markAsFailed($error_message);
      $job->save();

      // Job counts are now calculated dynamically by pipelines.
      // Dispatch job failed event.
      $this->eventDispatcher->dispatch(new GenericEvent($job, [
        "error_message" => $error_message,
      ]), "flowdrop.job.failed");

      // Handle retry logic.
      if ($job->canRetry()) {
        $this->retryJob($job);
      }
      // Pipeline failure handling is now managed at the pipeline level.
      $this->logger->error("Job @id failed: @message", [
        "@id" => $job->id(),
        "@message" => $error_message,
      ]);

    }
    catch (\Exception $e) {
      $this->logger->error("Failed to handle job failure @id: @message", [
        "@id" => $job->id(),
        "@message" => $e->getMessage(),
      ]);
    }
  }

  /**
   * Retry a failed job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to retry.
   */
  protected function retryJob(FlowDropJobInterface $job): void {
    $job->incrementRetryCount()
      ->setStatus("pending");
    $job->save();

    // Queue job for retry.
    $queue = $this->queueFactory->get("flowdrop_runtime_job_execution");
    $queue->createItem([
      "job_id" => $job->id(),
      "action" => "retry",
      "timestamp" => time(),
    ]);

    $this->logger->info("Job @id queued for retry (attempt @attempt)", [
      "@id" => $job->id(),
      "@attempt" => $job->getRetryCount(),
    ]);
  }

  /**
   * Re-evaluate pipeline after job completion.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to re-evaluate.
   */
  protected function reEvaluatePipeline(FlowDropPipelineInterface $pipeline): void {
    // Queue pipeline for re-evaluation.
    $queue = $this->queueFactory->get("flowdrop_runtime_pipeline_execution");
    $queue->createItem([
      "pipeline_id" => $pipeline->id(),
      "action" => "reevaluate",
      "timestamp" => time(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsWorkflow(array $workflow): bool {
    // Asynchronous orchestrator supports all workflows.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCapabilities(): array {
    return [
      'synchronous_execution' => FALSE,
      'parallel_execution' => TRUE,
      'async_execution' => TRUE,
      'queue_based' => TRUE,
      'retry_support' => TRUE,
      'error_recovery' => TRUE,
      'workflow_compilation' => TRUE,
      'pipeline_management' => TRUE,
    ];
  }

}
