<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;
use Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Queue worker for pipeline execution via runtime.
 *
 * @QueueWorker(
 * id = "flowdrop_runtime_pipeline",
 * title = @Translation("FlowDrop Runtime Pipeline"),
 * cron = {"time" = 60}
 * )
 */
class PipelineExecutionWorker extends QueueWorkerBase {

  /**
   * The asynchronous orchestrator service.
   *
   * @var \Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator
   */
  protected $asynchronousOrchestrator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  public function __construct(
    AsynchronousOrchestrator $asynchronous_orchestrator,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->asynchronousOrchestrator = $asynchronous_orchestrator;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      if (!isset($data["pipeline_id"])) {
        throw new \InvalidArgumentException("Pipeline ID is required");
      }

      $pipeline_id = $data["pipeline_id"];
      $action = $data["action"] ?? "execute";

      // Load the pipeline.
      $pipeline = $this->entityTypeManager->getStorage("flowdrop_pipeline")->load($pipeline_id);
      if (!$pipeline instanceof FlowDropPipelineInterface) {
        throw new \RuntimeException("Pipeline {$pipeline_id} not found");
      }

      $this->loggerFactory->get("flowdrop_runtime")->info("Processing pipeline @id with action @action", [
        "@id" => $pipeline_id,
        "@action" => $action,
      ]);

      switch ($action) {
        case "execute":
          $this->asynchronousOrchestrator->executePipeline($pipeline);
          break;

        case "reevaluate":
          $this->asynchronousOrchestrator->executePipeline($pipeline);
          break;

        case "start":
          $this->asynchronousOrchestrator->startPipeline($pipeline);
          break;

        default:
          throw new \InvalidArgumentException("Unknown action: {$action}");
      }

      $this->loggerFactory->get("flowdrop_runtime")->info("Successfully processed pipeline @id", ["@id" => $pipeline_id]);

    }
    catch (\Exception $e) {
      $this->loggerFactory->get("flowdrop_runtime")->error("Failed to process pipeline execution: @message", [
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
      $this->loggerFactory->get("flowdrop_runtime")->error("Pipeline execution failed: @message", [
        "@message" => $e->getMessage(),
      ]);
    }
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
