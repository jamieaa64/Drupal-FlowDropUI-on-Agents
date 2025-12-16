<?php

declare(strict_types=1);

namespace Drupal\flowdrop_pipeline\Service;

use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;
use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;
use Drupal\flowdrop_workflow\Exception\WorkflowExecutionException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Service for generating jobs from pipeline workflows.
 */
class JobGenerationService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Generate jobs for a pipeline based on its workflow.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to generate jobs for.
   *
   * @return array
   *   Array of created job entities.
   *
   * @throws \Exception
   *   If job generation fails.
   */
  public function generateJobs(FlowDropPipelineInterface $pipeline): array {
    $logger = $this->loggerFactory->get('flowdrop_pipeline');

    try {
      $logger->info('Starting job generation for pipeline @id', [
        '@id' => $pipeline->id(),
      ]);

      // Get the workflow.
      $workflow = $pipeline->getWorkflow();
      if (!$workflow) {
        throw new \RuntimeException('Workflow not found for pipeline ' . $pipeline->id());
      }

      // Get nodes and edges from workflow.
      $nodes = $workflow->getNodes();
      $edges = $workflow->getEdges();

      if (empty($nodes)) {
        throw new \RuntimeException('No nodes found in workflow ' . $workflow->id());
      }

      // Build detailed edge information for each node.
      $edge_info = $this->buildEdgeInformation($nodes, $edges);

      // Build dependency graph.
      $dependency_graph = $this->buildDependencyGraph($nodes, $edges);

      // Validate dependency graph for cycles.
      $this->validateDependencyGraph($dependency_graph);

      // Calculate execution order and priorities.
      $execution_order = $this->calculateExecutionOrder($dependency_graph);

      // Create jobs first (without dependencies).
      $created_jobs = [];
      $job_storage = $this->entityTypeManager->getStorage('flowdrop_job');
      // Map node IDs to job entities.
      $node_to_job_map = [];

      foreach ($nodes as $node) {
        $node_id = $node['id'];
        $node_type_id = $node['data']['metadata']['id'] ?? '';

        // Calculate priority based on execution order.
        $priority = $this->calculateJobPriority($node_id, $execution_order, $dependency_graph);

        // Prepare job metadata with edge information.
        $metadata = [
          'node_id' => $node_id,
          'node_type_id' => $node_type_id,
          'incoming_edges' => $edge_info[$node_id]['incoming'] ?? [],
          'outgoing_edges' => $edge_info[$node_id]['outgoing'] ?? [],
        ];

        // Prepare job data (dependencies will be added as
        // entity references later).
        $job_data = [
          'label' => $node['data']['label'] ?? 'Job ' . $node_id,
          'bundle' => 'default',
          'metadata' => json_encode($metadata),
          'node_type_id' => $node_type_id,
          'status' => 'pending',
          'priority' => $priority,
          'input_data' => json_encode($node['data'] ?? []),
          'output_data' => json_encode([]),
          'max_retries' => $this->getMaxRetriesForNode($node),
        ];

        // Create the job.
        $job = $job_storage->create($job_data);
        $job->save();

        // Store mapping for dependency resolution.
        $node_to_job_map[$node_id] = $job;

        // Add job to pipeline's job_id field.
        assert($job instanceof FlowDropJobInterface);
        $pipeline->addJob($job);

        $created_jobs[] = $job;

        // Dispatch job created event.
        $this->eventDispatcher->dispatch(new GenericEvent($job, [
          'pipeline_id' => $pipeline->id(),
          'node_id' => $node_id,
          'dependencies' => $dependency_graph[$node_id] ?? [],
        ]), 'flowdrop.job.created');

        $logger->info('Created job @job_id for node @node_id in pipeline @pipeline_id', [
          '@job_id' => $job->id(),
          '@node_id' => $node_id,
          '@pipeline_id' => $pipeline->id(),
        ]);
      }

      // Now establish entity reference dependencies between jobs.
      foreach ($dependency_graph as $node_id => $dependency_node_ids) {
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

      // Dispatch pipeline jobs generated event.
      $this->eventDispatcher->dispatch(new GenericEvent($pipeline, [
        'jobs_created' => count($created_jobs),
        'job_ids' => array_map(fn($job) => $job->id(), $created_jobs),
      ]), 'flowdrop.pipeline.jobs_generated');

      $logger->info('Successfully generated @count jobs for pipeline @id', [
        '@count' => count($created_jobs),
        '@id' => $pipeline->id(),
      ]);

      return $created_jobs;

    }
    catch (\Exception $e) {
      $logger->error('Failed to generate jobs for pipeline @id: @message', [
        '@id' => $pipeline->id(),
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Build detailed edge information for each node.
   *
   * This stores full edge details including sourceHandle and targetHandle,
   * which are needed to implement gateway nodes and trigger inputs.
   *
   * @param array $nodes
   *   The workflow nodes.
   * @param array $edges
   *   The workflow edges.
   *
   * @return array
   *   Edge information keyed by node ID with 'incoming' and 'outgoing' arrays.
   */
  protected function buildEdgeInformation(array $nodes, array $edges): array {
    $edge_info = [];

    // Initialize empty edge arrays for all nodes.
    foreach ($nodes as $node) {
      $edge_info[$node['id']] = [
        'incoming' => [],
        'outgoing' => [],
      ];
    }

    // Process each edge.
    foreach ($edges as $edge) {
      $source_id = $edge['source'];
      $target_id = $edge['target'];
      $source_handle = $edge['sourceHandle'] ?? '';
      $target_handle = $edge['targetHandle'] ?? '';

      // Determine edge type based on targetHandle.
      // Trigger inputs have format: {nodeId}-input-trigger.
      $is_trigger = str_contains($target_handle, '-input-trigger');

      // Extract the branch name from sourceHandle if present.
      // Format: {nodeId}-output-{branchName} (e.g., "node-123-output-True")
      $branch_name = '';
      if ($source_handle && str_contains($source_handle, '-output-')) {
        $parts = explode('-output-', $source_handle);
        if (count($parts) === 2) {
          $branch_name = $parts[1];
        }
      }

      // Store incoming edge for target node.
      if (isset($edge_info[$target_id])) {
        $edge_info[$target_id]['incoming'][] = [
          'source' => $source_id,
          'target' => $target_id,
          'source_handle' => $source_handle,
          'target_handle' => $target_handle,
          'is_trigger' => $is_trigger,
          'branch_name' => $branch_name,
          'edge_id' => $edge['id'] ?? uniqid('edge_'),
        ];
      }

      // Store outgoing edge for source node.
      if (isset($edge_info[$source_id])) {
        $edge_info[$source_id]['outgoing'][] = [
          'source' => $source_id,
          'target' => $target_id,
          'source_handle' => $source_handle,
          'target_handle' => $target_handle,
          'is_trigger' => $is_trigger,
          'branch_name' => $branch_name,
          'edge_id' => $edge['id'] ?? uniqid('edge_'),
        ];
      }
    }

    return $edge_info;
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
   *   Dependency graph where keys are node IDs and values are arrays of
   *   dependency node IDs.
   */
  protected function buildDependencyGraph(array $nodes, array $edges): array {
    $dependencies = [];

    // Initialize empty dependencies for all nodes.
    foreach ($nodes as $node) {
      $dependencies[$node['id']] = [];
    }

    // Build dependencies from edges.
    foreach ($edges as $edge) {
      $source_id = $edge['source'];
      $target_id = $edge['target'];

      // Target depends on source.
      if (isset($dependencies[$target_id])) {
        $dependencies[$target_id][] = $source_id;
      }
    }

    return $dependencies;
  }

  /**
   * Validate dependency graph for cycles.
   *
   * @param array $dependency_graph
   *   The dependency graph to validate.
   *
   * @throws \RuntimeException
   *   If a cycle is detected.
   */
  protected function validateDependencyGraph(array $dependency_graph): void {
    $visited = [];
    $rec_stack = [];

    foreach (array_keys($dependency_graph) as $node_id) {
      if (!isset($visited[$node_id])) {
        if ($this->hasCycleDfs($dependency_graph, $node_id, $visited, $rec_stack)) {
          throw new \RuntimeException('Circular dependency detected in workflow');
        }
      }
    }
  }

  /**
   * Depth-first search to detect cycles.
   *
   * @param array $dependency_graph
   *   The dependency graph.
   * @param string $node_id
   *   Current node ID.
   * @param array $visited
   *   Visited nodes tracker.
   * @param array $rec_stack
   *   Recursion stack tracker.
   *
   * @return bool
   *   TRUE if cycle is detected.
   */
  protected function hasCycleDfs(array $dependency_graph, string $node_id, array &$visited, array &$rec_stack): bool {
    $visited[$node_id] = TRUE;
    $rec_stack[$node_id] = TRUE;

    // Check all dependencies (reverse direction for cycle detection)
    foreach ($dependency_graph as $target_id => $dependencies) {
      if (in_array($node_id, $dependencies, TRUE)) {
        if (!isset($visited[$target_id])) {
          if ($this->hasCycleDfs($dependency_graph, $target_id, $visited, $rec_stack)) {
            return TRUE;
          }
        }
        elseif (isset($rec_stack[$target_id]) && $rec_stack[$target_id]) {
          return TRUE;
        }
      }
    }

    $rec_stack[$node_id] = FALSE;
    return FALSE;
  }

  /**
   * Calculate execution order using topological sort.
   *
   * @param array $dependency_graph
   *   The dependency graph.
   *
   * @return array
   *   Execution order where keys are node IDs and values are order indices.
   */
  protected function calculateExecutionOrder(array $dependency_graph): array {
    $in_degree = [];
    $execution_order = [];
    $order_index = 0;

    // Calculate in-degree for each node.
    foreach (array_keys($dependency_graph) as $node_id) {
      $in_degree[$node_id] = count($dependency_graph[$node_id]);
    }

    // Queue nodes with no dependencies.
    $queue = [];
    foreach ($in_degree as $node_id => $degree) {
      if ($degree === 0) {
        $queue[] = $node_id;
      }
    }

    // Process nodes in topological order.
    while (!empty($queue)) {
      $current_node = array_shift($queue);
      $execution_order[$current_node] = $order_index++;

      // Reduce in-degree for nodes that depend on current node.
      foreach ($dependency_graph as $node_id => $dependencies) {
        if (in_array($current_node, $dependencies, TRUE)) {
          $in_degree[$node_id]--;
          if ($in_degree[$node_id] === 0) {
            $queue[] = $node_id;
          }
        }
      }
    }

    return $execution_order;
  }

  /**
   * Calculate job priority based on execution order and dependencies.
   *
   * @param string $node_id
   *   The node ID.
   * @param array $execution_order
   *   The execution order.
   * @param array $dependency_graph
   *   The dependency graph.
   *
   * @return int
   *   The calculated priority (lower number = higher priority).
   */
  protected function calculateJobPriority(string $node_id, array $execution_order, array $dependency_graph): int {
    $base_priority = ($execution_order[$node_id] ?? 1000) * 10;

    // Adjust priority based on number of dependencies.
    $dependency_count = count($dependency_graph[$node_id] ?? []);
    $dependency_adjustment = $dependency_count * 5;

    return $base_priority + $dependency_adjustment;
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
    $node_type_id = $node['data']['metadata']['id'] ?? 'unknown';

    // Try to load the node type entity to get the executor plugin.
    $node_type = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($node_type_id);

    if ($node_type instanceof FlowDropNodeTypeInterface && $node_type->getExecutorPlugin()) {
      return $node_type->getExecutorPlugin();
    }

    // Fall back to a default or throw exception.
    throw new WorkflowExecutionException(
      "No executor plugin ID found for node type: $node_type_id"
    );
  }

  /**
   * Get max retries for a node based on its configuration.
   *
   * @param array $node
   *   The workflow node.
   *
   * @return int
   *   The maximum number of retries.
   */
  protected function getMaxRetriesForNode(array $node): int {
    // Check if the node has a specific retry configuration.
    if (isset($node['data']['config']['max_retries'])) {
      return (int) $node['data']['config']['max_retries'];
    }

    // Default retry count.
    return 3;
  }

  /**
   * Get ready jobs for a pipeline (jobs whose dependencies are completed).
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline.
   *
   * @return array
   *   Array of ready job entities.
   */
  public function getReadyJobs(FlowDropPipelineInterface $pipeline): array {
    $all_jobs = $pipeline->getJobs();
    $pending_jobs = $pipeline->getJobsByStatus('pending');
    $completed_jobs = $pipeline->getJobsByStatus('completed');

    // Build a map of completed jobs by node ID for quick lookup.
    $completed_jobs_map = [];
    foreach ($completed_jobs as $job) {
      $completed_jobs_map[$job->getNodeId()] = $job;
    }

    $ready_jobs = [];
    foreach ($pending_jobs as $job) {
      if ($this->areJobDependenciesMet($job, $completed_jobs_map)) {
        $ready_jobs[] = $job;
      }
    }

    // Sort by priority (lower number = higher priority).
    usort($ready_jobs, function ($a, $b) {
      return $a->getPriority() <=> $b->getPriority();
    });

    return $ready_jobs;
  }

  /**
   * Check if a job's dependencies are met.
   *
   * This method implements support for:
   * - Gateway nodes (e.g., IfElse) with conditional branches
   * - Trigger inputs that control execution flow
   * - Regular data inputs.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to check.
   * @param array $completed_jobs_map
   *   Map of completed node IDs to job entities.
   *
   * @return bool
   *   TRUE if all dependencies are met.
   */
  protected function areJobDependenciesMet(FlowDropJobInterface $job, array $completed_jobs_map): bool {
    $logger = $this->loggerFactory->get('flowdrop_pipeline');
    $dependent_jobs = $job->getDependentJobs();

    if (empty($dependent_jobs)) {
      // No dependencies - job is ready to run.
      return TRUE;
    }

    // Get incoming edges from job metadata.
    $metadata = $job->getMetadata();
    $incoming_edges = $metadata['incoming_edges'] ?? [];

    // Separate trigger and data edges.
    $trigger_edges = [];
    $data_edges = [];

    foreach ($incoming_edges as $edge) {
      if ($edge['is_trigger'] ?? FALSE) {
        $trigger_edges[] = $edge;
      }
      else {
        $data_edges[] = $edge;
      }
    }

    $logger->debug('Checking dependencies for job @job_id (@label): @trigger_count trigger edges, @data_count data edges', [
      '@job_id' => $job->id(),
      '@label' => $job->label(),
      '@trigger_count' => count($trigger_edges),
      '@data_count' => count($data_edges),
    ]);

    // RULE: If a job has trigger inputs, ONLY triggers control execution.
    // Data edges are for fetching data, not for execution flow.
    if (!empty($trigger_edges)) {
      $logger->debug('Job @job_id has trigger inputs - checking ONLY triggers for execution', [
        '@job_id' => $job->id(),
      ]);

      $any_trigger_satisfied = FALSE;

      foreach ($trigger_edges as $trigger_edge) {
        $source_node_id = $trigger_edge['source'];
        $branch_name = $trigger_edge['branch_name'] ?? '';

        // Check if source node is completed.
        if (!isset($completed_jobs_map[$source_node_id])) {
          // Source not completed, this trigger not satisfied.
          $logger->debug('Trigger not satisfied for job @job_id: source @source not completed', [
            '@job_id' => $job->id(),
            '@source' => $source_node_id,
          ]);
          continue;
        }

        $source_job = $completed_jobs_map[$source_node_id];

        // For gateway nodes, check if the active_branches matches.
        if ($branch_name !== '') {
          $output_data = $source_job->getOutputData();
          $active_branches = $output_data['active_branches'] ?? '';

          $logger->debug('Checking branch match for job @job_id: branch @branch vs active @active', [
            '@job_id' => $job->id(),
            '@branch' => $branch_name,
            '@active' => $active_branches,
          ]);

          // Check if this branch was activated.
          // active_branches can be a single string (e.g., "true")
          // or potentially comma-separated for multiple branches.
          $active_branch_list = explode(',', strtolower($active_branches));
          $active_branch_list = array_map('trim', $active_branch_list);

          if (in_array(strtolower($branch_name), $active_branch_list, TRUE)) {
            // This trigger is satisfied!
            $logger->info('Trigger satisfied for job @job_id (@label): branch @branch matches', [
              '@job_id' => $job->id(),
              '@label' => $job->label(),
              '@branch' => $branch_name,
            ]);
            $any_trigger_satisfied = TRUE;
            break;
          }
        }
        else {
          // No branch name specified - any completion satisfies.
          $logger->info('Trigger satisfied for job @job_id (@label): no branch requirement', [
            '@job_id' => $job->id(),
            '@label' => $job->label(),
          ]);
          $any_trigger_satisfied = TRUE;
          break;
        }
      }

      // If no trigger is satisfied, job is not ready.
      if (!$any_trigger_satisfied) {
        $logger->info('Job @job_id (@label) NOT ready: no triggers satisfied', [
          '@job_id' => $job->id(),
          '@label' => $job->label(),
        ]);
        return FALSE;
      }

      // Trigger satisfied - job is ready!
      // Data edges are ignored for execution flow when triggers are present.
      $logger->info('Job @job_id (@label) is READY to execute (trigger satisfied)', [
        '@job_id' => $job->id(),
        '@label' => $job->label(),
      ]);
      return TRUE;
    }

    // No triggers - use traditional dependency checking.
    // All data dependencies must be completed.
    $logger->debug('Job @job_id has NO triggers - checking all dependencies', [
      '@job_id' => $job->id(),
    ]);

    foreach ($dependent_jobs as $dependent_job) {
      $dependency_node_id = $dependent_job->getNodeId();

      // For data dependencies, check if completed.
      if (!isset($completed_jobs_map[$dependency_node_id])) {
        $logger->debug('Data dependency not met for job @job_id: @dep_node_id not completed', [
          '@job_id' => $job->id(),
          '@dep_node_id' => $dependency_node_id,
        ]);
        return FALSE;
      }
    }

    // All data dependencies met!
    $logger->info('Job @job_id (@label) is READY to execute (all data dependencies met)', [
      '@job_id' => $job->id(),
      '@label' => $job->label(),
    ]);
    return TRUE;
  }

  /**
   * Clear all jobs for a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to clear jobs for.
   *
   * @return int
   *   Number of jobs deleted.
   */
  public function clearJobs(FlowDropPipelineInterface $pipeline): int {
    $jobs = $pipeline->getJobs();
    $count = count($jobs);

    if ($count > 0) {
      $job_storage = $this->entityTypeManager->getStorage('flowdrop_job');
      $job_storage->delete($jobs);

      // Clear jobs from pipeline's job_id field.
      $pipeline->clearJobs();
      $pipeline->save();

      $this->loggerFactory->get('flowdrop_pipeline')->info('Cleared @count jobs for pipeline @id', [
        '@count' => $count,
        '@id' => $pipeline->id(),
      ]);
    }

    return $count;
  }

}
