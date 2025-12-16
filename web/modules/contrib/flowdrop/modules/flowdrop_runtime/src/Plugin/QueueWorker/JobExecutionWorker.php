<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\flowdrop_runtime\Service\Runtime\NodeRuntimeService;
use Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext;
use Drupal\flowdrop\DTO\Input;
use Drupal\flowdrop\DTO\Config;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Queue worker for job execution via runtime.
 *
 * @QueueWorker(
 * id = "flowdrop_runtime_job",
 * title = @Translation("FlowDrop Runtime Job"),
 * cron = {"time" = 60}
 * )
 */
class JobExecutionWorker extends QueueWorkerBase {

  public function __construct(
    protected NodeRuntimeService $nodeRuntime,
    protected AsynchronousOrchestrator $asynchronousOrchestrator,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      if (!isset($data["job_id"])) {
        throw new \InvalidArgumentException("Job ID is required");
      }

      $job_id = $data["job_id"];
      $action = $data["action"] ?? "execute";

      // Load the job.
      $job = $this->entityTypeManager->getStorage("flowdrop_job")->load($job_id);
      if (!$job instanceof FlowDropJobInterface) {
        throw new \RuntimeException("Job {$job_id} not found");
      }

      $this->loggerFactory->get("flowdrop_runtime")->info("Processing job @id with action @action", [
        "@id" => $job_id,
        "@action" => $action,
      ]);

      switch ($action) {
        case "execute":
          $this->executeJob($job);
          break;

        case "retry":
          $this->retryJob($job);
          break;

        default:
          throw new \InvalidArgumentException("Unknown action: {$action}");
      }

      $this->loggerFactory->get("flowdrop_runtime")->info("Successfully processed job @id", ["@id" => $job_id]);

    }
    catch (\Exception $e) {
      $this->loggerFactory->get("flowdrop_runtime")->error("Failed to process job execution: @message", [
        "@message" => $e->getMessage(),
      ]);

      // Requeue the item if it's a temporary failure.
      if ($this->isTemporaryFailure($e)) {
        throw new RequeueException($e->getMessage());
      }

      // Suspend the queue if it's a permanent failure.
      if ($this->isPermanentFailure($e)) {
        throw new SuspendQueueException($e->getMessage());
      }

      // For other exceptions, just log and continue.
      $this->loggerFactory->get("flowdrop_runtime")->error("Job execution failed: @message", [
        "@message" => $e->getMessage(),
      ]);
    }
  }

  /**
   * Execute a job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to execute.
   */
  protected function executeJob(FlowDropJobInterface $job): void {
    try {
      // Validate the job before execution.
      $this->validateJob($job);

      // Prepare execution context.
      $context = $this->prepareExecutionContext($job);
      $inputs = $this->prepareInputs($job);
      $config = $this->prepareConfig($job);

      // Execute the job using node runtime.
      $result = $this->nodeRuntime->executeNode(
        $job->id(),
        $job->getNodeId(),
        $this->getNodeType($job),
        $inputs,
        $config,
        $context
      );

      // Handle successful completion.
      $this->asynchronousOrchestrator->handleJobCompletion($job, $result->toArray());

    }
    catch (\Exception $e) {
      // Handle job failure.
      $this->asynchronousOrchestrator->handleJobFailure($job, $e->getMessage());
      throw $e;
    }
  }

  /**
   * Retry a failed job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to retry.
   */
  protected function retryJob(FlowDropJobInterface $job): void {
    try {
      // Execute the job with retry logic.
      $this->executeJob($job);

    }
    catch (\Exception $e) {
      // Handle job failure.
      $this->asynchronousOrchestrator->handleJobFailure($job, $e->getMessage());
      throw $e;
    }
  }

  /**
   * Validate job before execution.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to validate.
   *
   * @throws \InvalidArgumentException
   *   If the job is invalid.
   */
  protected function validateJob(FlowDropJobInterface $job): void {
    // Check required fields.
    if (empty($job->getNodeId())) {
      throw new \InvalidArgumentException("Job must have a node ID");
    }

    // Check job status.
    if (!in_array($job->getStatus(), ["pending", "running"], TRUE)) {
      throw new \InvalidArgumentException("Job must be in pending or running status");
    }

    // Validate node data.
    $node_data = $job->getInputData();
    if (empty($node_data) || !isset($node_data["type"])) {
      throw new \InvalidArgumentException("Job must have valid node data with type");
    }
  }

  /**
   * Prepare execution context for a job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to prepare context for.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext
   *   The execution context.
   */
  protected function prepareExecutionContext(FlowDropJobInterface $job): NodeExecutionContext {
    $context_data = [
      "job_id" => $job->id(),
      "node_id" => $job->getNodeId(),
      "execution_time" => time(),
      "retry_count" => $job->getRetryCount(),
      "max_retries" => $job->getMaxRetries(),
    ];

    return new NodeExecutionContext(
      $job->id(),
      $job->id(),
      $context_data
    );
  }

  /**
   * Prepare inputs for a job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to prepare inputs for.
   *
   * @return \Drupal\flowdrop\DTO\Input
   *   The prepared inputs.
   */
  protected function prepareInputs(FlowDropJobInterface $job): Input {
    $input_data = $job->getInputData();
    return new Input($input_data);
  }

  /**
   * Prepare config for a job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to prepare config for.
   *
   * @return \Drupal\flowdrop\DTO\Config
   *   The prepared config.
   */
  protected function prepareConfig(FlowDropJobInterface $job): Config {
    $config_data = [
      "timeout" => 300,
      "max_retries" => $job->getMaxRetries(),
      "memory_limit" => 128,
    ];

    return new Config($config_data);
  }

  /**
   * Get the node type for a job.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to get node type for.
   *
   * @return string
   *   The node type.
   */
  protected function getNodeType(FlowDropJobInterface $job): string {
    $input_data = $job->getInputData();
    return $input_data["type"] ?? "unknown";
  }

  /**
   * Check if the exception represents a temporary failure.
   *
   * @param \Exception $e
   *   The exception to check.
   *
   * @return bool
   *   TRUE if the failure is temporary.
   */
  protected function isTemporaryFailure(\Exception $e): bool {
    // Network errors, database connection issues, etc.
    $temporary_exceptions = [
      "PDOException",
      "DatabaseException",
      "ConnectionException",
      "TimeoutException",
    ];

    foreach ($temporary_exceptions as $exception_class) {
      if ($e instanceof $exception_class) {
        return TRUE;
      }
    }

    // Check for specific error messages that indicate temporary issues.
    $temporary_messages = [
      "connection",
      "timeout",
      "temporary",
      "retry",
      "busy",
      "locked",
      "rate limit",
      "quota exceeded",
    ];

    $message = strtolower($e->getMessage());
    foreach ($temporary_messages as $keyword) {
      if (strpos($message, $keyword) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if the exception represents a permanent failure.
   *
   * @param \Exception $e
   *   The exception to check.
   *
   * @return bool
   *   TRUE if the failure is permanent.
   */
  protected function isPermanentFailure(\Exception $e): bool {
    // Invalid data, missing dependencies, etc.
    $permanent_exceptions = [
      "InvalidArgumentException",
      "RuntimeException",
      "LogicException",
    ];

    foreach ($permanent_exceptions as $exception_class) {
      if ($e instanceof $exception_class) {
        return TRUE;
      }
    }

    // Check for specific error messages that indicate permanent issues.
    $permanent_messages = [
      "not found",
      "invalid",
      "missing",
      "required",
      "not allowed",
      "authentication failed",
      "authorization failed",
      "permission denied",
    ];

    $message = strtolower($e->getMessage());
    foreach ($permanent_messages as $keyword) {
      if (strpos($message, $keyword) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
