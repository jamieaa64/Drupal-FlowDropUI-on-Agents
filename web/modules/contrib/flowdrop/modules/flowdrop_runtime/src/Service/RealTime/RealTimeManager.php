<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\RealTime;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop_runtime\DTO\RealTime\RealTimeEvent;
use Drupal\flowdrop_runtime\DTO\RealTime\ExecutionStatus;
use Drupal\flowdrop_runtime\DTO\RealTime\NodeStatus;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Manages real-time execution updates and status tracking.
 */
class RealTimeManager {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventBroadcaster $eventBroadcaster,
    private readonly StatusTracker $statusTracker,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Start real-time monitoring for an execution.
   */
  public function startMonitoring(string $executionId, array $workflow): void {
    $this->logger->info('Starting real-time monitoring for execution @id', [
      '@id' => $executionId,
    ]);

    // Initialize status tracking.
    $this->statusTracker->initializeExecution($executionId, $workflow);

    // Broadcast execution started event.
    $this->broadcastExecutionEvent($executionId, 'started', [
      'workflow_id' => $workflow['id'] ?? '',
      'node_count' => count($workflow['nodes'] ?? []),
      'timestamp' => time(),
    ]);

    // Dispatch Drupal event for other modules.
    $event = new GenericEvent($executionId, [
      'execution_id' => $executionId,
      'workflow' => $workflow,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.monitoring.started');
  }

  /**
   * Update node status in real-time.
   */
  public function updateNodeStatus(
    string $executionId,
    string $nodeId,
    string $status,
    array $data = [],
  ): void {
    $nodeStatus = new NodeStatus(
      executionId: $executionId,
      nodeId: $nodeId,
      status: $status,
      data: $data,
      timestamp: time(),
    );

    $this->statusTracker->updateNodeStatus($nodeStatus);

    // Broadcast node status update.
    $this->broadcastNodeEvent($executionId, $nodeId, $status, $data);

    $this->logger->debug('Updated node status: @execution_id:@node_id = @status', [
      '@execution_id' => $executionId,
      '@node_id' => $nodeId,
      '@status' => $status,
    ]);
  }

  /**
   * Update overall execution status.
   */
  public function updateExecutionStatus(
    string $executionId,
    string $status,
    array $data = [],
  ): void {
    $executionStatus = new ExecutionStatus(
      executionId: $executionId,
      status: $status,
      data: $data,
      timestamp: time(),
    );

    $this->statusTracker->updateExecutionStatus($executionStatus);

    // Broadcast execution status update.
    $this->broadcastExecutionEvent($executionId, $status, $data);

    $this->logger->info('Updated execution status: @execution_id = @status', [
      '@execution_id' => $executionId,
      '@status' => $status,
    ]);
  }

  /**
   * Get current execution status for polling.
   */
  public function getExecutionStatus(string $executionId): array {
    return $this->statusTracker->getExecutionStatus($executionId);
  }

  /**
   * Get detailed node statuses for an execution.
   */
  public function getNodeStatuses(string $executionId): array {
    return $this->statusTracker->getNodeStatuses($executionId);
  }

  /**
   * Broadcast execution event to connected clients.
   */
  private function broadcastExecutionEvent(string $executionId, string $event, array $data): void {
    switch ($event) {
      case 'started':
        $this->eventBroadcaster->broadcastExecutionStarted($executionId, $data);
        break;

      case 'completed':
        $this->eventBroadcaster->broadcastExecutionCompleted($executionId, $data);
        break;

      case 'failed':
        $this->eventBroadcaster->broadcastExecutionFailed($executionId, $data);
        break;

      default:
        // For other events, use the generic broadcast method.
        $this->eventBroadcaster->broadcast(new RealTimeEvent(
          type: 'execution',
          executionId: $executionId,
          event: $event,
          data: $data,
          timestamp: time(),
        ));
    }
  }

  /**
   * Broadcast node event to connected clients.
   */
  private function broadcastNodeEvent(string $executionId, string $nodeId, string $status, array $data): void {
    switch ($status) {
      case 'running':
        $this->eventBroadcaster->broadcastNodeStarted($executionId, $nodeId, $data);
        break;

      case 'completed':
        $this->eventBroadcaster->broadcastNodeCompleted($executionId, $nodeId, $data);
        break;

      case 'failed':
        $this->eventBroadcaster->broadcastNodeFailed($executionId, $nodeId, $data);
        break;

      default:
        // For other statuses, use the generic broadcast method.
        $this->eventBroadcaster->broadcast(new RealTimeEvent(
          type: 'node',
          executionId: $executionId,
          event: $status,
          data: $data,
          timestamp: time(),
          nodeId: $nodeId,
        ));
    }
  }

}
