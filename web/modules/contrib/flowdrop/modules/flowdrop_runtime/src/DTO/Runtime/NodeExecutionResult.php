<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Runtime;

use Drupal\flowdrop\DTO\OutputInterface;

/**
 * Result of a single node execution.
 */
class NodeExecutionResult {

  public function __construct(
    private readonly string $nodeId,
    private readonly string $nodeType,
    private readonly string $status,
    private readonly OutputInterface $output,
    private readonly float $executionTime,
    private readonly int $timestamp,
    private readonly NodeExecutionContext $context,
  ) {}

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
   * Get the node type.
   *
   * @return string
   *   The node type.
   */
  public function getNodeType(): string {
    return $this->nodeType;
  }

  /**
   * Get the execution status.
   *
   * @return string
   *   The execution status.
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Get the node output.
   *
   * @return \Drupal\flowdrop\DTO\OutputInterface
   *   The node output.
   */
  public function getOutput(): OutputInterface {
    return $this->output;
  }

  /**
   * Get the execution time in seconds.
   *
   * @return float
   *   The execution time.
   */
  public function getExecutionTime(): float {
    return $this->executionTime;
  }

  /**
   * Get the execution timestamp.
   *
   * @return int
   *   The execution timestamp.
   */
  public function getTimestamp(): int {
    return $this->timestamp;
  }

  /**
   * Get the execution context.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext
   *   The execution context.
   */
  public function getContext(): NodeExecutionContext {
    return $this->context;
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the result.
   */
  public function toArray(): array {
    return [
      'node_id' => $this->nodeId,
      'node_type' => $this->nodeType,
      'status' => $this->status,
      'output' => $this->output->toArray(),
      'execution_time' => $this->executionTime,
      'timestamp' => $this->timestamp,
      'context' => [
        'workflow_id' => $this->context->getWorkflowId(),
        'pipeline_id' => $this->context->getPipelineId(),
      ],
    ];
  }

}
