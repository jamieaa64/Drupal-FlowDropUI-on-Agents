<?php

namespace Drupal\flowdrop_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;

/**
 * API Controller for FlowDrop Pipeline operations.
 */
class PipelineApiController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PipelineApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get pipelines for a workflow.
   *
   * @param string $workflow_id
   *   The workflow ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with pipelines data.
   */
  public function getWorkflowPipelines($workflow_id) {
    try {
      // Load workflow entity.
      $workflow_storage = $this->entityTypeManager->getStorage('flowdrop_workflow');
      $workflow = $workflow_storage->load($workflow_id);

      if (!$workflow) {
        return new JsonResponse([
          'error' => 'Workflow not found',
          'message' => "Workflow with ID {$workflow_id} does not exist.",
        ], 404);
      }

      // Load pipelines for this workflow.
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $query = $pipeline_storage->getQuery()
        ->condition('workflow_id', $workflow_id)
        ->accessCheck(TRUE);
      $pipeline_ids = $query->execute();

      $pipelines = [];
      if (!empty($pipeline_ids)) {
        $pipeline_entities = $pipeline_storage->loadMultiple($pipeline_ids);
        foreach ($pipeline_entities as $pipeline) {
          $pipelines[] = $this->formatPipelineData($pipeline);
        }
      }

      return new JsonResponse([
        'workflow_id' => $workflow_id,
        'workflow_name' => $workflow->label(),
        'pipelines' => $pipelines,
        'count' => count($pipelines),
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_pipeline')->error('Error fetching workflow pipelines: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch pipelines for workflow.',
      ], 500);
    }
  }

  /**
   * Get a specific pipeline.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with pipeline data.
   */
  public function getPipeline($pipeline_id) {
    try {
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $pipeline = $pipeline_storage->load($pipeline_id);

      if (!$pipeline) {
        return new JsonResponse([
          'error' => 'Pipeline not found',
          'message' => "Pipeline with ID {$pipeline_id} does not exist.",
        ], 404);
      }

      return new JsonResponse($this->formatPipelineData($pipeline));

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_pipeline')->error('Error fetching pipeline: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch pipeline.',
      ], 500);
    }
  }

  /**
   * Get pipeline execution status.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with pipeline status data.
   */
  public function getPipelineStatus($pipeline_id) {
    try {
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $pipeline = $pipeline_storage->load($pipeline_id);

      if (!$pipeline instanceof FlowDropPipelineInterface) {
        return new JsonResponse([
          'error' => 'Pipeline not found',
          'message' => "Pipeline with ID {$pipeline_id} does not exist.",
        ], 404);
      }

      // Get execution status from pipeline.
      return new JsonResponse($this->formatPipelineData($pipeline));

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_pipeline')->error('Error fetching pipeline status: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch pipeline status.',
      ], 500);
    }
  }

  /**
   * Get pipeline execution logs.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with pipeline logs.
   */
  public function getPipelineLogs($pipeline_id) {
    try {
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $pipeline = $pipeline_storage->load($pipeline_id);

      if (!$pipeline) {
        return new JsonResponse([
          'error' => 'Pipeline not found',
          'message' => "Pipeline with ID {$pipeline_id} does not exist.",
        ], 404);
      }

      // Get logs from pipeline.
      $logs_data = $pipeline->get('execution_logs')->value ?? [];

      // Parse logs data if it's a JSON string.
      if (is_string($logs_data)) {
        $logs_data = json_decode($logs_data, TRUE) ?? [];
      }

      // Ensure logs is an array.
      if (!is_array($logs_data)) {
        $logs_data = [];
      }

      return new JsonResponse([
        'pipeline_id' => $pipeline_id,
        'logs' => $logs_data,
        'count' => count($logs_data),
        'timestamp' => date('c'),
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_pipeline')->error('Error fetching pipeline logs: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch pipeline logs.',
      ], 500);
    }
  }

  /**
   * Format pipeline data for API response.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline entity.
   *
   * @return array
   *   Formatted pipeline data.
   */
  protected function formatPipelineData(FlowDropPipelineInterface $pipeline) {
    // Get creation date.
    $created_at = $pipeline->get('created')->value;
    $created_date = $created_at ? date('c', (int) $created_at) : date('c');

    // Get completed date if available (this is the closest to "last executed")
    $completed = $pipeline->get('completed')->value ?? NULL;
    $last_executed_date = $completed ? date('c', (int) $completed) : NULL;

    // Get associated jobs for this pipeline.
    $jobs_data = $this->getPipelineJobs($pipeline->id());

    // Calculate execution count from jobs.
    $execution_count = count($jobs_data['jobs']);

    return [
      'id' => $pipeline->id(),
      'name' => $pipeline->label(),
      'description' => $pipeline->get('description')->value ?? '',
      'status' => $pipeline->getStatus(),
      'createdAt' => $created_date,
      'lastExecuted' => $last_executed_date,
      'executionCount' => (int) $execution_count,
      'execution_data' => [
        'input' => $pipeline->getInputData(),
        'output' => $pipeline->getOutputData(),
        'context' => $pipeline->getExecutionContext(),
      ],
      'node_statuses' => $jobs_data['node_statuses'],
      'jobs' => $jobs_data['jobs'],
      'job_status_summary' => $jobs_data['status_summary'],
      'timestamp' => date('c'),
    ];
  }

  /**
   * Get jobs for a specific pipeline.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return array
   *   Array containing jobs, node_statuses, and status_summary.
   */
  protected function getPipelineJobs($pipeline_id) {
    try {
      // Load the pipeline entity to get its jobs directly.
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $pipeline = $pipeline_storage->load($pipeline_id);

      if (!$pipeline) {
        return [
          'jobs' => [],
          'node_statuses' => [],
          'status_summary' => [
            'total' => 0,
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
          ],
        ];
      }

      // Get jobs directly from the pipeline entity.
      $pipeline_jobs = $pipeline->getJobs();

      $jobs = [];
      $statusSummary = [
        'total' => 0,
        'pending' => 0,
        'running' => 0,
        'completed' => 0,
        'failed' => 0,
        'cancelled' => 0,
      ];
      $nodeStatuses = [];

      foreach ($pipeline_jobs as $job) {
        $status = $job->getStatus();
        $statusSummary['total']++;
        $statusSummary[$status]++;

        // Get node ID from job metadata.
        $nodeId = $job->getNodeId();
        if ($nodeId) {
          $nodeStatuses[$nodeId] = [
            'status' => $status,
            'job_id' => $job->id(),
            'started' => $job->get('started')->value ? date('c', (int) $job->get('started')->value) : NULL,
            'completed' => $job->get('completed')->value ? date('c', (int) $job->get('completed')->value) : NULL,
            'error_message' => $job->get('error_message')->value ?? NULL,
            'retry_count' => (int) $job->get('retry_count')->value ?? 0,
          ];
        }

        // Format job data.
        $jobs[] = $this->formatJobData($job);
      }

      return [
        'jobs' => $jobs,
        'node_statuses' => $nodeStatuses,
        'status_summary' => $statusSummary,
      ];

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_pipeline')->error('Error fetching pipeline jobs: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'jobs' => [],
        'node_statuses' => [],
        'status_summary' => [
          'total' => 0,
          'pending' => 0,
          'running' => 0,
          'completed' => 0,
          'failed' => 0,
          'cancelled' => 0,
        ],
      ];
    }
  }

  /**
   * Format job data for API response.
   *
   * @param \Drupal\flowdrop_job\Entity\FlowDropJobInterface $job
   *   The job entity.
   *
   * @return array
   *   Formatted job data.
   */
  protected function formatJobData($job) {
    // Get creation date.
    $created_at = $job->get('created')->value;
    $created_date = $created_at ? date('c', (int) $created_at) : date('c');

    // Get started date if available.
    $started = $job->get('started')->value ?? NULL;
    $started_date = $started ? date('c', (int) $started) : NULL;

    // Get completed date if available.
    $completed = $job->get('completed')->value ?? NULL;
    $completed_date = $completed ? date('c', (int) $completed) : NULL;

    return [
      'id' => $job->id(),
      'label' => $job->label(),
      'status' => $job->getStatus(),
      'priority' => $job->getPriority(),
      'node_id' => $job->getNodeId(),
      'created_at' => $created_date,
      'started' => $started_date,
      'completed' => $completed_date,
      'retry_count' => (int) $job->get('retry_count')->value ?? 0,
      'max_retries' => (int) $job->get('max_retries')->value ?? 3,
      'error_message' => $job->get('error_message')->value ?? NULL,
      'input_data' => $job->getInputData(),
      'output_data' => $job->getOutputData(),
      'metadata' => $job->getMetadata(),
      'timestamp' => date('c'),
    ];
  }

  /**
   * Access callback for pipeline API endpoints.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessPipeline(AccountInterface $account, $pipeline_id) {
    // Check if user has permission to view pipelines.
    if (!$account->hasPermission('view flowdrop_pipeline')) {
      return AccessResult::forbidden();
    }

    // Check if pipeline exists and user can access it.
    $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
    $pipeline = $pipeline_storage->load($pipeline_id);

    if (!$pipeline) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
