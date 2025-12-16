<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\RealTime;

/**
 * Real-time event for broadcasting execution updates.
 *
 * We could use this to implement WebSocket
 * based broadcaster using notification_server project.
 */
class RealTimeEvent {

  public function __construct(
    private readonly string $type,
    private readonly string $executionId,
    private readonly string $event,
    private readonly array $data,
    private readonly int $timestamp,
    private readonly ?string $nodeId = NULL,
  ) {}

  /**
   * Get the event type.
   *
   * @return string
   *   The event type.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Get the execution ID.
   *
   * @return string
   *   The execution ID.
   */
  public function getExecutionId(): string {
    return $this->executionId;
  }

  /**
   * Get the event name.
   *
   * @return string
   *   The event name.
   */
  public function getEvent(): string {
    return $this->event;
  }

  /**
   * Get the event data.
   *
   * @return array
   *   The event data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Get the event timestamp.
   *
   * @return int
   *   The event timestamp.
   */
  public function getTimestamp(): int {
    return $this->timestamp;
  }

  /**
   * Get the node ID (if applicable).
   *
   * @return string|null
   *   The node ID or NULL.
   */
  public function getNodeId(): ?string {
    return $this->nodeId;
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the event.
   */
  public function toArray(): array {
    return [
      'type' => $this->type,
      'execution_id' => $this->executionId,
      'event' => $this->event,
      'data' => $this->data,
      'timestamp' => $this->timestamp,
      'node_id' => $this->nodeId,
    ];
  }

}
