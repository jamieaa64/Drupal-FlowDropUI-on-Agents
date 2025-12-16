<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\RealTime;

/**
 * Node status for real-time updates.
 */
class NodeStatus {

  public function __construct(
    private readonly string $executionId,
    private readonly string $nodeId,
    private readonly string $status,
    private readonly array $data,
    private readonly int $timestamp,
  ) {}

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
   * Get the node ID.
   *
   * @return string
   *   The node ID.
   */
  public function getNodeId(): string {
    return $this->nodeId;
  }

  /**
   * Get the node status.
   *
   * @return string
   *   The node status.
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Get the status data.
   *
   * @return array
   *   The status data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Get the status timestamp.
   *
   * @return int
   *   The status timestamp.
   */
  public function getTimestamp(): int {
    return $this->timestamp;
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the status.
   */
  public function toArray(): array {
    return [
      'execution_id' => $this->executionId,
      'node_id' => $this->nodeId,
      'status' => $this->status,
      'data' => $this->data,
      'timestamp' => $this->timestamp,
    ];
  }

}
