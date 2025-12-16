<?php

namespace Drupal\flowdrop_modeler\Service;

/**
 * Service to transform workflow data for FlowDrop editor compatibility.
 *
 * This service transforms workflow data from the BPMN mapper format
 * to the format expected by the FlowDrop editor, including node metadata,
 * inputs/outputs, and edge formatting.
 */
class WorkflowDataTransformer {

  /**
   * Transforms workflow data to FlowDrop editor format.
   *
   * @param array $workflowData
   *   The raw workflow data from BPMN mapper.
   *
   * @return array
   *   The transformed workflow data for FlowDrop editor.
   */
  public function transformForFlowDrop(array $workflowData): array {
    $nodes = $workflowData['nodes'] ?? [];
    $edges = $workflowData['edges'] ?? [];

    // Transform nodes.
    $transformedNodes = [];
    foreach ($nodes as $node) {
      $transformedNode = $this->transformNode($node);
      if ($transformedNode !== NULL) {
        $transformedNodes[] = $transformedNode;
      }
    }

    // Transform edges.
    $transformedEdges = [];
    $supportedNodeIds = array_column($transformedNodes, 'id');
    $supportedNodeIdsSet = array_flip($supportedNodeIds);

    foreach ($edges as $edge) {
      $transformedEdge = $this->transformEdge($edge, $supportedNodeIdsSet);
      if ($transformedEdge !== NULL) {
        $transformedEdges[] = $transformedEdge;
      }
    }

    return [
      'id' => $workflowData['id'] ?? '',
      'name' => $workflowData['label'] ?? $workflowData['name'] ?? 'Untitled Workflow',
      'description' => $workflowData['description'] ?? '',
      'nodes' => $transformedNodes,
      'edges' => $transformedEdges,
      'metadata' => $this->transformMetadata($workflowData),
    ];
  }

  /**
   * Transforms a single node for FlowDrop compatibility.
   *
   * @param array $node
   *   The original node data.
   *
   * @return array|null
   *   The transformed node data or null if unsupported.
   */
  protected function transformNode(array $node): ?array {
    $nodeType = $node['type'] ?? '';
    $supportedTypes = ['event', 'condition', 'gateway', 'action'];

    if (!in_array($nodeType, $supportedTypes)) {
      return NULL;
    }

    $nodeId = $node['id'] ?? '';
    $label = $node['label'] ?? $nodeId;

    return [
      'id' => $nodeId,
    // FlowDrop expects 'workflowNode' type.
      'type' => 'workflowNode',
      'label' => $label,
      'data' => [
        'plugin' => $node['data']['plugin'] ?? '',
        'configuration' => $node['data']['configuration'] ?? [],
        'originalId' => $node['data']['originalId'] ?? '',
        'label' => $label,
        'config' => $node['data']['configuration'] ?? [],
        'metadata' => [
          'plugin' => $node['data']['plugin'] ?? '',
          'configuration' => $node['data']['configuration'] ?? [],
          'originalId' => $node['data']['originalId'] ?? '',
    // Preserve original type in metadata.
          'nodeType' => $nodeType,
          'inputs' => $this->getNodeInputs($nodeType),
          'outputs' => $this->getNodeOutputs($nodeType),
          'id' => $nodeId,
          'name' => $label,
          'description' => $this->getNodeDescription($node),
          'category' => $nodeType,
          'version' => '1.0.0',
          'color' => $this->getNodeColor($nodeType),
          'tags' => [$nodeType],
        ],
      ],
      'position' => $node['position'] ?? ['x' => 0, 'y' => 0],
    ];
  }

  /**
   * Transforms a single edge for FlowDrop compatibility.
   *
   * @param array $edge
   *   The original edge data.
   * @param array $supportedNodeIds
   *   Array of supported node IDs for filtering.
   *
   * @return array|null
   *   The transformed edge data or null if unsupported.
   */
  protected function transformEdge(array $edge, array $supportedNodeIds): ?array {
    $source = $edge['source'] ?? '';
    $target = $edge['target'] ?? '';

    // Check if both source and target nodes are supported.
    if (!isset($supportedNodeIds[$source]) || !isset($supportedNodeIds[$target])) {
      return NULL;
    }

    return [
      'id' => $edge['id'] ?? '',
      'source' => $source,
      'target' => $target,
    // FlowDrop default edge type.
      'type' => 'bezier',
      'label' => $edge['label'] ?? '',
      'data' => [
        'label' => $edge['label'] ?? '',
        'condition' => $edge['data']['condition'] ?? '',
      ],
    ];
  }

  /**
   * Transforms metadata for FlowDrop compatibility.
   *
   * @param array $workflowData
   *   The original workflow data.
   *
   * @return array
   *   The transformed metadata.
   */
  protected function transformMetadata(array $workflowData): array {
    $created = $workflowData['created'] ?? NULL;
    $changed = $workflowData['changed'] ?? NULL;

    return [
      'version' => '1.0.0',
      'createdAt' => $created ? date('c', $created) : date('c'),
      'updatedAt' => $changed ? date('c', $changed) : date('c'),
      'bpmn' => $workflowData['metadata']['bpmn'] ?? [],
    ];
  }

  /**
   * Gets node inputs based on node type.
   *
   * @param string $nodeType
   *   The node type.
   *
   * @return array
   *   Array of input definitions.
   */
  protected function getNodeInputs(string $nodeType): array {
    $inputMap = [
      'event' => [
        [
          'id' => 'trigger',
          'name' => 'Trigger',
          'type' => 'input',
          'dataType' => 'string',
          'required' => FALSE,
          'description' => 'Event trigger input',
        ],
      ],
      'condition' => [
        [
          'id' => 'input',
          'name' => 'Input',
          'type' => 'input',
          'dataType' => 'any',
          'required' => TRUE,
          'description' => 'Input value to evaluate',
        ],
      ],
      'gateway' => [
        [
          'id' => 'input',
          'name' => 'Input',
          'type' => 'input',
          'dataType' => 'any',
          'required' => TRUE,
          'description' => 'Input for gateway evaluation',
        ],
      ],
      'action' => [
        [
          'id' => 'input',
          'name' => 'Input',
          'type' => 'input',
          'dataType' => 'any',
          'required' => FALSE,
          'description' => 'Action input data',
        ],
      ],
    ];

    return $inputMap[$nodeType] ?? [];
  }

  /**
   * Gets node outputs based on node type.
   *
   * @param string $nodeType
   *   The node type.
   *
   * @return array
   *   Array of output definitions.
   */
  protected function getNodeOutputs(string $nodeType): array {
    $outputMap = [
      'event' => [
        [
          'id' => 'output',
          'name' => 'Output',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Event output data',
        ],
      ],
      'condition' => [
        [
          'id' => 'true',
          'name' => 'True',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Output when condition is true',
        ],
        [
          'id' => 'false',
          'name' => 'False',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Output when condition is false',
        ],
      ],
      'gateway' => [
        [
          'id' => 'output',
          'name' => 'Output',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Gateway output data',
        ],
      ],
      'action' => [
        [
          'id' => 'output',
          'name' => 'Output',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Action output data',
        ],
      ],
    ];

    return $outputMap[$nodeType] ?? [];
  }

  /**
   * Gets node color based on node type.
   *
   * @param string $nodeType
   *   The node type.
   *
   * @return string
   *   The color hex code.
   */
  protected function getNodeColor(string $nodeType): string {
    $colorMap = [
    // Blue.
      'event' => '#3b82f6',
    // Amber.
      'condition' => '#f59e0b',
    // Purple.
      'gateway' => '#8b5cf6',
    // Emerald.
      'action' => '#10b981',
    ];

    // Gray fallback.
    return $colorMap[$nodeType] ?? '#6b7280';
  }

  /**
   * Gets node description from node data.
   *
   * @param array $node
   *   The node data.
   *
   * @return string
   *   The node description.
   */
  protected function getNodeDescription(array $node): string {
    $configuration = $node['data']['configuration'] ?? [];
    $label = $node['label'] ?? $node['id'] ?? '';

    // Try to get description from configuration first.
    if (isset($configuration['description'])) {
      return $configuration['description'];
    }

    // Fallback to label.
    return $label;
  }

}
