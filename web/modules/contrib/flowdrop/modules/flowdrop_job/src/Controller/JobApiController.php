<?php

declare(strict_types=1);

namespace Drupal\flowdrop_job\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\flowdrop_job\Entity\FlowDropJobInterface;

/**
 * API Controller for FlowDrop Job operations.
 */
class JobApiController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a JobApiController object.
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
   * Get jobs for a specific pipeline.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with jobs data.
   */
  public function getPipelineJobs($pipeline_id) {
    try {
      // Load pipeline entity to verify it exists.
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $pipeline = $pipeline_storage->load($pipeline_id);

      if (!$pipeline) {
        return new JsonResponse([
          'error' => 'Pipeline not found',
          'message' => "Pipeline with ID {$pipeline_id} does not exist.",
        ], 404);
      }

      // Load jobs for this pipeline
      // Since jobs don't have a direct pipeline_id field, we'll get all jobs
      // and filter by pipeline_id in metadata or by workflow association.
      $job_storage = $this->entityTypeManager->getStorage('flowdrop_job');
      $query = $job_storage->getQuery()
        ->accessCheck(TRUE);
      $job_ids = $query->execute();

      $jobs = [];
      if (!empty($job_ids)) {
        $job_entities = $job_storage->loadMultiple($job_ids);
        foreach ($job_entities as $job) {
          // Check if job belongs to this pipeline via metadata.
          $job_pipeline_id = $job->getMetadataValue('pipeline_id');
          if ($job_pipeline_id === $pipeline_id) {
            $jobs[] = $this->formatJobData($job);
          }
        }
      }

      return new JsonResponse([
        'pipeline_id' => $pipeline_id,
        'pipeline_name' => $pipeline->label(),
        'jobs' => $jobs,
        'count' => count($jobs),
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_job')->error('Error fetching pipeline jobs: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch jobs for pipeline.',
      ], 500);
    }
  }

  /**
   * Get job status summary for a pipeline.
   *
   * @param string $pipeline_id
   *   The pipeline ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with job status summary.
   */
  public function getPipelineJobStatus($pipeline_id) {
    try {
      // Load pipeline entity to verify it exists.
      $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
      $pipeline = $pipeline_storage->load($pipeline_id);

      if (!$pipeline) {
        return new JsonResponse([
          'error' => 'Pipeline not found',
          'message' => "Pipeline with ID {$pipeline_id} does not exist.",
        ], 404);
      }

      // Load jobs for this pipeline
      // Since jobs don't have a direct pipeline_id field, we'll get all jobs
      // and filter by pipeline_id in metadata or by workflow association.
      $job_storage = $this->entityTypeManager->getStorage('flowdrop_job');
      $query = $job_storage->getQuery()
        ->accessCheck(TRUE);
      $job_ids = $query->execute();

      $statusSummary = [
        'total' => 0,
        'pending' => 0,
        'running' => 0,
        'completed' => 0,
        'failed' => 0,
        'cancelled' => 0,
      ];

      $nodeStatuses = [];

      if (!empty($job_ids)) {
        $job_entities = $job_storage->loadMultiple($job_ids);

        // Filter jobs by pipeline_id in metadata.
        $pipeline_jobs = [];
        foreach ($job_entities as $job) {
          $job_pipeline_id = $job->getMetadataValue('pipeline_id');
          if ($job_pipeline_id === $pipeline_id) {
            $pipeline_jobs[] = $job;
          }
        }

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
        }
      }

      return new JsonResponse([
        'pipeline_id' => $pipeline_id,
        'pipeline_name' => $pipeline->label(),
        'status_summary' => $statusSummary,
        'node_statuses' => $nodeStatuses,
        'timestamp' => date('c'),
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_job')->error('Error fetching pipeline job status: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch job status for pipeline.',
      ], 500);
    }
  }

  /**
   * Get a specific job.
   *
   * @param string $job_id
   *   The job ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with job data.
   */
  public function getJob($job_id) {
    try {
      $job_storage = $this->entityTypeManager->getStorage('flowdrop_job');
      $job = $job_storage->load($job_id);

      if (!$job) {
        return new JsonResponse([
          'error' => 'Job not found',
          'message' => "Job with ID {$job_id} does not exist.",
        ], 404);
      }

      return new JsonResponse($this->formatJobData($job));

    }
    catch (\Exception $e) {
      \Drupal::logger('flowdrop_job')->error('Error fetching job: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Internal server error',
        'message' => 'Failed to fetch job.',
      ], 500);
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
  protected function formatJobData(FlowDropJobInterface $job) {
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
      'pipeline_id' => $job->getMetadataValue('pipeline_id'),
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
   * Access callback for job API endpoints.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $job_id
   *   The job ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessJob(AccountInterface $account, $job_id) {
    // Check if user has permission to view jobs.
    if (!$account->hasPermission('view flowdrop_job')) {
      return AccessResult::forbidden();
    }

    // Check if job exists and user can access it.
    $job_storage = $this->entityTypeManager->getStorage('flowdrop_job');
    $job = $job_storage->load($job_id);

    if (!$job) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
