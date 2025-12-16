<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\RealTime;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop_runtime\DTO\RealTime\RealTimeEvent;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Broadcasts real-time events to connected clients.
 */
class EventBroadcaster {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Broadcast a real-time event.
   */
  public function broadcast(RealTimeEvent $event): void {
    $this->logger->debug('Broadcasting real-time event: @type:@event for @execution_id', [
      '@type' => $event->getType(),
      '@event' => $event->getEvent(),
      '@execution_id' => $event->getExecutionId(),
    ]);

    // Dispatch Drupal event for other modules to listen to.
    $drupalEvent = new GenericEvent($event, [
      'type' => $event->getType(),
      'execution_id' => $event->getExecutionId(),
      'event' => $event->getEvent(),
      'data' => $event->getData(),
      'timestamp' => $event->getTimestamp(),
      'node_id' => $event->getNodeId(),
    ]);

    $this->eventDispatcher->dispatch($drupalEvent, 'flowdrop_runtime.real_time_event');

    // Log the event for debugging.
    $this->logger->info('Real-time event broadcasted: @type:@event', [
      '@type' => $event->getType(),
      '@event' => $event->getEvent(),
      '@execution_id' => $event->getExecutionId(),
      '@node_id' => $event->getNodeId(),
    ]);
  }

  /**
   * Broadcast execution started event.
   */
  public function broadcastExecutionStarted(string $executionId, array $data = []): void {
    $event = new RealTimeEvent(
      type: 'execution',
      executionId: $executionId,
      event: 'started',
      data: $data,
      timestamp: time(),
    );

    $this->broadcast($event);
  }

  /**
   * Broadcast execution completed event.
   */
  public function broadcastExecutionCompleted(string $executionId, array $data = []): void {
    $event = new RealTimeEvent(
      type: 'execution',
      executionId: $executionId,
      event: 'completed',
      data: $data,
      timestamp: time(),
    );

    $this->broadcast($event);
  }

  /**
   * Broadcast execution failed event.
   */
  public function broadcastExecutionFailed(string $executionId, array $data = []): void {
    $event = new RealTimeEvent(
      type: 'execution',
      executionId: $executionId,
      event: 'failed',
      data: $data,
      timestamp: time(),
    );

    $this->broadcast($event);
  }

  /**
   * Broadcast node started event.
   */
  public function broadcastNodeStarted(string $executionId, string $nodeId, array $data = []): void {
    $event = new RealTimeEvent(
      type: 'node',
      executionId: $executionId,
      event: 'started',
      data: $data,
      timestamp: time(),
      nodeId: $nodeId,
    );

    $this->broadcast($event);
  }

  /**
   * Broadcast node completed event.
   */
  public function broadcastNodeCompleted(string $executionId, string $nodeId, array $data = []): void {
    $event = new RealTimeEvent(
      type: 'node',
      executionId: $executionId,
      event: 'completed',
      data: $data,
      timestamp: time(),
      nodeId: $nodeId,
    );

    $this->broadcast($event);
  }

  /**
   * Broadcast node failed event.
   */
  public function broadcastNodeFailed(string $executionId, string $nodeId, array $data = []): void {
    $event = new RealTimeEvent(
      type: 'node',
      executionId: $executionId,
      event: 'failed',
      data: $data,
      timestamp: time(),
      nodeId: $nodeId,
    );

    $this->broadcast($event);
  }

}
