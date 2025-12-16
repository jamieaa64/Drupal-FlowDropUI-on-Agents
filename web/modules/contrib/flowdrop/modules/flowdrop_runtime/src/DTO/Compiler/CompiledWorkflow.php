<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Compiler;

/**
 * Represents a compiled workflow with execution plan.
 */
class CompiledWorkflow {

  public function __construct(
    private readonly string $workflowId,
    private readonly ExecutionPlan $executionPlan,
    private readonly array $nodeMappings,
    private readonly array $dependencyGraph,
    private readonly array $metadata,
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
   * Get the execution plan.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Compiler\ExecutionPlan
   *   The execution plan.
   */
  public function getExecutionPlan(): ExecutionPlan {
    return $this->executionPlan;
  }

  /**
   * Get the node mappings.
   *
   * @return array
   *   Array of NodeMapping objects keyed by node ID.
   */
  public function getNodeMappings(): array {
    return $this->nodeMappings;
  }

  /**
   * Get the dependency graph.
   *
   * @return array
   *   The dependency graph.
   */
  public function getDependencyGraph(): array {
    return $this->dependencyGraph;
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
   * Get a specific node mapping.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Compiler\NodeMapping|null
   *   The node mapping or NULL if not found.
   */
  public function getNodeMapping(string $nodeId): ?NodeMapping {
    return $this->nodeMappings[$nodeId] ?? NULL;
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the compiled workflow.
   */
  public function toArray(): array {
    $nodeMappingsArray = [];
    foreach ($this->nodeMappings as $nodeId => $mapping) {
      $nodeMappingsArray[$nodeId] = $mapping->toArray();
    }

    return [
      'workflow_id' => $this->workflowId,
      'execution_plan' => $this->executionPlan->toArray(),
      'node_mappings' => $nodeMappingsArray,
      'dependency_graph' => $this->dependencyGraph,
      'metadata' => $this->metadata,
    ];
  }

}
