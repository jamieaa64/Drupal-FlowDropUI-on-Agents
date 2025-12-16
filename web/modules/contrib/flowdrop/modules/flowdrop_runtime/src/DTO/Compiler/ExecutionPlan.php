<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Compiler;

/**
 * Represents an execution plan for a workflow.
 */
class ExecutionPlan {

  public function __construct(
    private readonly array $executionOrder,
    private readonly array $inputMappings,
    private readonly array $outputMappings,
    private readonly array $metadata,
  ) {}

  /**
   * Get the execution order.
   *
   * @return array
   *   Array of node IDs in execution order.
   */
  public function getExecutionOrder(): array {
    return $this->executionOrder;
  }

  /**
   * Get the input mappings.
   *
   * @return array
   *   Input mappings keyed by node ID.
   */
  public function getInputMappings(): array {
    return $this->inputMappings;
  }

  /**
   * Get the output mappings.
   *
   * @return array
   *   Output mappings keyed by node ID.
   */
  public function getOutputMappings(): array {
    return $this->outputMappings;
  }

  /**
   * Get the metadata.
   *
   * @return array
   *   The metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Get input mappings for a specific node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array
   *   Input mappings for the node.
   */
  public function getNodeInputMappings(string $nodeId): array {
    return $this->inputMappings[$nodeId] ?? [];
  }

  /**
   * Get output mappings for a specific node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array
   *   Output mappings for the node.
   */
  public function getNodeOutputMappings(string $nodeId): array {
    return $this->outputMappings[$nodeId] ?? [];
  }

  /**
   * Get dependencies for a specific node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array
   *   Array of dependency node IDs.
   */
  public function getNodeDependencies(string $nodeId): array {
    $dependencies = [];
    foreach ($this->inputMappings[$nodeId] ?? [] as $mapping) {
      if (isset($mapping['source_node'])) {
        $dependencies[] = $mapping['source_node'];
      }
    }
    return array_unique($dependencies);
  }

  /**
   * Get dependents for a specific node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array
   *   Array of dependent node IDs.
   */
  public function getNodeDependents(string $nodeId): array {
    $dependents = [];
    foreach ($this->outputMappings[$nodeId] ?? [] as $mapping) {
      if (isset($mapping['target_node'])) {
        $dependents[] = $mapping['target_node'];
      }
    }
    return array_unique($dependents);
  }

  /**
   * Check if a node is a root node (no dependencies).
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return bool
   *   True if the node is a root node.
   */
  public function isRootNode(string $nodeId): bool {
    return empty($this->getNodeDependencies($nodeId));
  }

  /**
   * Check if a node is a leaf node (no dependents).
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return bool
   *   True if the node is a leaf node.
   */
  public function isLeafNode(string $nodeId): bool {
    return empty($this->getNodeDependents($nodeId));
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the execution plan.
   */
  public function toArray(): array {
    return [
      'execution_order' => $this->executionOrder,
      'input_mappings' => $this->inputMappings,
      'output_mappings' => $this->outputMappings,
      'metadata' => $this->metadata,
    ];
  }

}
