<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\DTO;

/**
 * Data Transfer Object for a complete workflow.
 *
 * This DTO normalizes the workflow structure from the frontend JSON format
 * to a consistent internal representation used by the orchestrator and
 * compiler.
 */
class WorkflowDTO {

  /**
   * The workflow ID.
   *
   * @var string
   */
  private readonly string $id;

  /**
   * The workflow name/label.
   *
   * @var string
   */
  private readonly string $name;

  /**
   * The workflow description.
   *
   * @var string
   */
  private readonly string $description;

  /**
   * The workflow nodes.
   *
   * @var array<string, WorkflowNodeDTO>
   */
  private readonly array $nodes;

  /**
   * The workflow edges.
   *
   * @var array<string, WorkflowEdgeDTO>
   */
  private readonly array $edges;

  /**
   * The workflow metadata.
   *
   * @var array<string, mixed>
   */
  private readonly array $metadata;

  /**
   * Edge metadata indexed by node ID (incoming/outgoing edges).
   *
   * @var array<string, array{incoming: array<WorkflowEdgeDTO>, outgoing: array<WorkflowEdgeDTO>}>
   */
  private readonly array $edgeIndex;

  /**
   * The original raw workflow data.
   *
   * @var array<string, mixed>
   */
  private readonly array $rawData;

  /**
   * Constructs a WorkflowDTO.
   *
   * @param string $id
   *   The workflow ID.
   * @param string $name
   *   The workflow name.
   * @param string $description
   *   The workflow description.
   * @param array<string, WorkflowNodeDTO> $nodes
   *   The workflow nodes keyed by ID.
   * @param array<string, WorkflowEdgeDTO> $edges
   *   The workflow edges keyed by ID.
   * @param array<string, mixed> $metadata
   *   The workflow metadata.
   * @param array<string, array{incoming: array<WorkflowEdgeDTO>, outgoing: array<WorkflowEdgeDTO>}> $edgeIndex
   *   Edge index by node ID.
   * @param array<string, mixed> $rawData
   *   The original raw workflow data.
   */
  public function __construct(
    string $id,
    string $name,
    string $description,
    array $nodes,
    array $edges,
    array $metadata,
    array $edgeIndex,
    array $rawData,
  ) {
    $this->id = $id;
    $this->name = $name;
    $this->description = $description;
    $this->nodes = $nodes;
    $this->edges = $edges;
    $this->metadata = $metadata;
    $this->edgeIndex = $edgeIndex;
    $this->rawData = $rawData;
  }

  /**
   * Create a WorkflowDTO from raw workflow data.
   *
   * This factory method handles the JSON structure from the frontend:
   * - data.id: Workflow ID
   * - data.name: Workflow name
   * - data.description: Workflow description
   * - data.nodes: Array of node objects
   * - data.edges: Array of edge objects
   * - data.metadata: Workflow metadata.
   *
   * @param array<string, mixed> $workflowData
   *   The raw workflow data. Can be the full API response or just the data.
   *
   * @return self
   *   The created WorkflowDTO.
   */
  public static function fromArray(array $workflowData): self {
    // Handle both full API response and direct data.
    $data = $workflowData['data'] ?? $workflowData;

    $id = $data['id'] ?? '';
    $name = $data['name'] ?? $data['label'] ?? $id;
    $description = $data['description'] ?? '';
    $metadata = $data['metadata'] ?? [];
    $rawNodes = $data['nodes'] ?? [];
    $rawEdges = $data['edges'] ?? $data['connections'] ?? [];

    // Parse nodes into DTOs.
    $nodes = [];
    foreach ($rawNodes as $rawNode) {
      $nodeDTO = WorkflowNodeDTO::fromArray($rawNode);
      $nodes[$nodeDTO->getId()] = $nodeDTO;
    }

    // Parse edges into DTOs.
    $edges = [];
    foreach ($rawEdges as $rawEdge) {
      $edgeDTO = WorkflowEdgeDTO::fromArray($rawEdge);
      $edges[$edgeDTO->getId()] = $edgeDTO;
    }

    // Build edge index for quick lookups.
    $edgeIndex = self::buildEdgeIndex($nodes, $edges);

    return new self(
      id: $id,
      name: $name,
      description: $description,
      nodes: $nodes,
      edges: $edges,
      metadata: $metadata,
      edgeIndex: $edgeIndex,
      rawData: $workflowData,
    );
  }

  /**
   * Build an index of edges by node ID.
   *
   * @param array<string, WorkflowNodeDTO> $nodes
   *   The nodes.
   * @param array<string, WorkflowEdgeDTO> $edges
   *   The edges.
   *
   * @return array<string, array{incoming: array<WorkflowEdgeDTO>, outgoing: array<WorkflowEdgeDTO>}>
   *   Edge index.
   */
  private static function buildEdgeIndex(array $nodes, array $edges): array {
    $index = [];

    // Initialize empty arrays for all nodes.
    foreach ($nodes as $nodeId => $node) {
      $index[$nodeId] = [
        'incoming' => [],
        'outgoing' => [],
      ];
    }

    // Populate with edges.
    foreach ($edges as $edge) {
      $source = $edge->getSource();
      $target = $edge->getTarget();

      if (isset($index[$source])) {
        $index[$source]['outgoing'][] = $edge;
      }

      if (isset($index[$target])) {
        $index[$target]['incoming'][] = $edge;
      }
    }

    return $index;
  }

  /**
   * Get the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get the workflow name.
   *
   * @return string
   *   The workflow name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the workflow description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Get all nodes.
   *
   * @return array<string, WorkflowNodeDTO>
   *   The nodes keyed by ID.
   */
  public function getNodes(): array {
    return $this->nodes;
  }

  /**
   * Get a node by ID.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return WorkflowNodeDTO|null
   *   The node or NULL if not found.
   */
  public function getNode(string $nodeId): ?WorkflowNodeDTO {
    return $this->nodes[$nodeId] ?? NULL;
  }

  /**
   * Get all edges.
   *
   * @return array<string, WorkflowEdgeDTO>
   *   The edges keyed by ID.
   */
  public function getEdges(): array {
    return $this->edges;
  }

  /**
   * Get an edge by ID.
   *
   * @param string $edgeId
   *   The edge ID.
   *
   * @return WorkflowEdgeDTO|null
   *   The edge or NULL if not found.
   */
  public function getEdge(string $edgeId): ?WorkflowEdgeDTO {
    return $this->edges[$edgeId] ?? NULL;
  }

  /**
   * Get incoming edges for a node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array<WorkflowEdgeDTO>
   *   The incoming edges.
   */
  public function getIncomingEdges(string $nodeId): array {
    return $this->edgeIndex[$nodeId]['incoming'] ?? [];
  }

  /**
   * Get outgoing edges for a node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array<WorkflowEdgeDTO>
   *   The outgoing edges.
   */
  public function getOutgoingEdges(string $nodeId): array {
    return $this->edgeIndex[$nodeId]['outgoing'] ?? [];
  }

  /**
   * Get trigger edges for a node (incoming trigger edges).
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array<WorkflowEdgeDTO>
   *   The trigger edges.
   */
  public function getTriggerEdges(string $nodeId): array {
    $incoming = $this->getIncomingEdges($nodeId);
    return array_filter($incoming, fn(WorkflowEdgeDTO $edge) => $edge->isTrigger());
  }

  /**
   * Get data edges for a node (incoming non-trigger edges).
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array<WorkflowEdgeDTO>
   *   The data edges.
   */
  public function getDataEdges(string $nodeId): array {
    $incoming = $this->getIncomingEdges($nodeId);
    return array_filter($incoming, fn(WorkflowEdgeDTO $edge) => !$edge->isTrigger());
  }

  /**
   * Get dependency node IDs for a node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array<string>
   *   Array of dependency node IDs.
   */
  public function getDependencies(string $nodeId): array {
    $dependencies = [];
    foreach ($this->getIncomingEdges($nodeId) as $edge) {
      $dependencies[] = $edge->getSource();
    }
    return array_unique($dependencies);
  }

  /**
   * Get dependent node IDs for a node.
   *
   * @param string $nodeId
   *   The node ID.
   *
   * @return array<string>
   *   Array of dependent node IDs.
   */
  public function getDependents(string $nodeId): array {
    $dependents = [];
    foreach ($this->getOutgoingEdges($nodeId) as $edge) {
      $dependents[] = $edge->getTarget();
    }
    return array_unique($dependents);
  }

  /**
   * Get root nodes (nodes with no incoming edges).
   *
   * @return array<WorkflowNodeDTO>
   *   The root nodes.
   */
  public function getRootNodes(): array {
    $roots = [];
    foreach ($this->nodes as $nodeId => $node) {
      if (empty($this->edgeIndex[$nodeId]['incoming'])) {
        $roots[] = $node;
      }
    }
    return $roots;
  }

  /**
   * Get leaf nodes (nodes with no outgoing edges).
   *
   * @return array<WorkflowNodeDTO>
   *   The leaf nodes.
   */
  public function getLeafNodes(): array {
    $leaves = [];
    foreach ($this->nodes as $nodeId => $node) {
      if (empty($this->edgeIndex[$nodeId]['outgoing'])) {
        $leaves[] = $node;
      }
    }
    return $leaves;
  }

  /**
   * Get the workflow metadata.
   *
   * @return array<string, mixed>
   *   The metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Get the original raw workflow data.
   *
   * @return array<string, mixed>
   *   The raw data.
   */
  public function getRawData(): array {
    return $this->rawData;
  }

  /**
   * Convert to array format compatible with the compiler.
   *
   * This produces a normalized array that the WorkflowCompiler can use.
   *
   * @return array<string, mixed>
   *   Normalized workflow array.
   */
  public function toCompilerArray(): array {
    $nodes = [];
    foreach ($this->nodes as $node) {
      $nodes[] = [
        'id' => $node->getId(),
        'type' => $node->getTypeId(),
        'label' => $node->getLabel(),
        'config' => $node->getConfig(),
        'metadata' => $node->getMetadata(),
      ];
    }

    $connections = [];
    foreach ($this->edges as $edge) {
      $connections[] = [
        'id' => $edge->getId(),
        'source' => $edge->getSource(),
        'target' => $edge->getTarget(),
        'sourceHandle' => $edge->getSourceHandle(),
        'targetHandle' => $edge->getTargetHandle(),
      ];
    }

    return [
      'id' => $this->id,
      'name' => $this->name,
      'description' => $this->description,
      'nodes' => $nodes,
      'connections' => $connections,
      'edges' => $connections,
      'metadata' => $this->metadata,
    ];
  }

  /**
   * Get the node count.
   *
   * @return int
   *   Number of nodes.
   */
  public function getNodeCount(): int {
    return count($this->nodes);
  }

  /**
   * Get the edge count.
   *
   * @return int
   *   Number of edges.
   */
  public function getEdgeCount(): int {
    return count($this->edges);
  }

}
