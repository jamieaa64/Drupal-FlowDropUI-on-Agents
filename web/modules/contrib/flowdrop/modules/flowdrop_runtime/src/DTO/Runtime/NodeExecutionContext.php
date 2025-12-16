<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Runtime;

use Drupal\flowdrop\DTO\OutputInterface;

/**
 * Execution context for a workflow run.
 */
class NodeExecutionContext {

  public function __construct(
    private readonly string $workflowId,
    private readonly string $pipelineId,
    private readonly array $initialData,
    private array $nodeOutputs = [],
    private array $metadata = [],
  ) {}

  /**
   * Get the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId(): string {
    return $this->workflowId;
  }

  /**
   * Get the pipeline ID.
   *
   * @return string
   *   The pipeline ID.
   */
  public function getPipelineId(): string {
    return $this->pipelineId;
  }

  /**
   * Get the initial data.
   *
   * @return array
   *   The initial data array.
   */
  public function getInitialData(): array {
    return $this->initialData;
  }

  /**
   * Get all node outputs.
   *
   * @return array
   *   Array of node outputs keyed by node ID.
   */
  public function getNodeOutputs(): array {
    return $this->nodeOutputs;
  }

  /**
   * Get output for a specific node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return \Drupal\flowdrop\DTO\OutputInterface|null
   *   The node output or NULL if not found.
   */
  public function getNodeOutput(string $nodeId): ?OutputInterface {
    return $this->nodeOutputs[$nodeId] ?? NULL;
  }

  /**
   * Add output for a node.
   *
   * @param string $nodeId
   *   The node ID.
   * @param \Drupal\flowdrop\DTO\OutputInterface $output
   *   The node output.
   */
  public function addNodeOutput(string $nodeId, OutputInterface $output): void {
    $this->nodeOutputs[$nodeId] = $output;
  }

  /**
   * Get metadata.
   *
   * @return array
   *   The metadata array.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Set metadata.
   *
   * @param array $metadata
   *   The metadata array.
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

}
