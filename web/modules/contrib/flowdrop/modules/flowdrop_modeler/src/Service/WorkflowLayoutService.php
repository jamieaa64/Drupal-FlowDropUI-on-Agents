<?php

namespace Drupal\flowdrop_modeler\Service;

/**
 * Service to calculate node positions for workflow layouts.
 *
 * This service takes workflow data with nodes and edges and calculates
 * appropriate positions for each node to create a clean, readable layout.
 */
class WorkflowLayoutService {

  /**
   * Default spacing configuration.
   */
  protected const DEFAULT_SPACING = [
    'horizontal' => 500,
    'vertical' => 750,
    'start_x' => 100,
    'start_y' => 100,
  ];

  /**
   * Calculates positions for all nodes in a workflow.
   *
   * @param array $workflowData
   *   The workflow data containing nodes and edges.
   * @param array $spacing
   *   Optional spacing configuration.
   *
   * @return array
   *   The workflow data with node positions added.
   */
  public function calculateNodePositions(array $workflowData, array $spacing = []): array {
    $spacing = array_merge(self::DEFAULT_SPACING, $spacing);
    $nodes = $workflowData['nodes'] ?? [];
    $edges = $workflowData['edges'] ?? [];

    if (empty($nodes)) {
      return $workflowData;
    }

    // Build a graph representation for layout calculation.
    $graph = $this->buildGraph($nodes, $edges);

    // Calculate positions using a layered layout algorithm.
    $positions = $this->calculateLayeredLayout($graph, $spacing);

    // Apply positions to nodes.
    foreach ($nodes as &$node) {
      $nodeId = $node['id'];
      if (isset($positions[$nodeId])) {
        $node['position'] = $positions[$nodeId];
      }
    }

    $workflowData['nodes'] = $nodes;
    return $workflowData;
  }

  /**
   * Builds a graph representation from nodes and edges.
   *
   * @param array $nodes
   *   The workflow nodes.
   * @param array $edges
   *   The workflow edges.
   *
   * @return array
   *   Graph representation with adjacency lists.
   */
  protected function buildGraph(array $nodes, array $edges): array {
    $graph = [];

    // Initialize graph with all nodes.
    foreach ($nodes as $node) {
      $graph[$node['id']] = [
        'node' => $node,
        'incoming' => [],
        'outgoing' => [],
        'level' => -1,
      ];
    }

    // Add edges to graph.
    foreach ($edges as $edge) {
      $source = $edge['source'];
      $target = $edge['target'];

      if (isset($graph[$source]) && isset($graph[$target])) {
        $graph[$source]['outgoing'][] = $target;
        $graph[$target]['incoming'][] = $source;
      }
    }

    return $graph;
  }

  /**
   * Calculates a layered layout for the graph.
   *
   * @param array $graph
   *   The graph representation.
   * @param array $spacing
   *   Spacing configuration.
   *
   * @return array
   *   Node positions indexed by node ID.
   */
  protected function calculateLayeredLayout(array $graph, array $spacing): array {
    $positions = [];

    // Find start nodes (nodes with no incoming edges)
    $startNodes = [];
    foreach ($graph as $nodeId => $nodeData) {
      if (empty($nodeData['incoming'])) {
        $startNodes[] = $nodeId;
      }
    }

    // If no clear start nodes, use all nodes.
    if (empty($startNodes)) {
      $startNodes = array_keys($graph);
    }

    // Calculate levels using BFS.
    $this->calculateLevels($graph, $startNodes);

    // Group nodes by level.
    $levels = [];
    foreach ($graph as $nodeId => $nodeData) {
      $level = $nodeData['level'];
      if (!isset($levels[$level])) {
        $levels[$level] = [];
      }
      $levels[$level][] = $nodeId;
    }

    // Sort levels.
    ksort($levels);

    // Calculate positions for each level.
    foreach ($levels as $levelIndex => $levelNodes) {
      $x = $spacing['start_x'] + ($levelIndex * $spacing['horizontal']);

      // Center nodes vertically within the level.
      $totalHeight = (count($levelNodes) - 1) * $spacing['vertical'];
      $startY = $spacing['start_y'] - ($totalHeight / 2);

      foreach ($levelNodes as $index => $nodeId) {
        $y = $startY + ($index * $spacing['vertical']);
        $positions[$nodeId] = [
          'x' => $x,
          'y' => $y,
        ];
      }
    }

    return $positions;
  }

  /**
   * Calculates levels for all nodes using BFS.
   *
   * @param array &$graph
   *   The graph representation (passed by reference).
   * @param array $startNodes
   *   The starting nodes for BFS.
   */
  protected function calculateLevels(array &$graph, array $startNodes): void {
    $queue = [];
    $visited = [];

    // Initialize start nodes.
    foreach ($startNodes as $nodeId) {
      $graph[$nodeId]['level'] = 0;
      $queue[] = $nodeId;
      $visited[$nodeId] = TRUE;
    }

    // BFS to calculate levels.
    while (!empty($queue)) {
      $currentNode = array_shift($queue);
      $currentLevel = $graph[$currentNode]['level'];

      foreach ($graph[$currentNode]['outgoing'] as $neighbor) {
        if (!isset($visited[$neighbor])) {
          $graph[$neighbor]['level'] = $currentLevel + 1;
          $queue[] = $neighbor;
          $visited[$neighbor] = TRUE;
        }
        else {
          // Update level if we found a longer path.
          $graph[$neighbor]['level'] = max($graph[$neighbor]['level'], $currentLevel + 1);
        }
      }
    }

    // Handle any unvisited nodes (disconnected components)
    foreach ($graph as $nodeId => &$nodeData) {
      if ($nodeData['level'] === -1) {
        $nodeData['level'] = 0;
      }
    }
  }

  /**
   * Applies a simple grid layout for fallback positioning.
   *
   * @param array $workflowData
   *   The workflow data.
   * @param array $spacing
   *   Spacing configuration.
   *
   * @return array
   *   The workflow data with simple grid positions.
   */
  public function applySimpleGridLayout(array $workflowData, array $spacing = []): array {
    $spacing = array_merge(self::DEFAULT_SPACING, $spacing);
    $nodes = $workflowData['nodes'] ?? [];

    if (empty($nodes)) {
      return $workflowData;
    }

    // Group nodes by type for better organization.
    $nodesByType = [];
    foreach ($nodes as $node) {
      $type = $node['type'] ?? 'unknown';
      if (!isset($nodesByType[$type])) {
        $nodesByType[$type] = [];
      }
      $nodesByType[$type][] = $node;
    }

    // Define type order for layout.
    $typeOrder = ['event', 'gateway', 'action', 'condition'];
    $positions = [];
    $currentX = $spacing['start_x'];

    foreach ($typeOrder as $type) {
      if (isset($nodesByType[$type])) {
        $typeNodes = $nodesByType[$type];
        $totalHeight = (count($typeNodes) - 1) * $spacing['vertical'];
        $startY = $spacing['start_y'] - ($totalHeight / 2);

        foreach ($typeNodes as $index => $node) {
          $positions[$node['id']] = [
            'x' => $currentX,
            'y' => $startY + ($index * $spacing['vertical']),
          ];
        }

        $currentX += $spacing['horizontal'];
      }
    }

    // Apply positions to nodes.
    foreach ($nodes as &$node) {
      $nodeId = $node['id'];
      if (isset($positions[$nodeId])) {
        $node['position'] = $positions[$nodeId];
      }
    }

    $workflowData['nodes'] = $nodes;
    return $workflowData;
  }

}
