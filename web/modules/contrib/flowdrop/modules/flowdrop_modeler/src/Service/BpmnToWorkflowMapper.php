<?php

namespace Drupal\flowdrop_modeler\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Service to map BPMN model data to workflow format.
 *
 * Converts BPMN components (events, conditions, gateways, actions) into
 * a workflow format with nodes and edges for the FlowDrop editor.
 */
class BpmnToWorkflowMapper {

  /**
   * Maps BPMN model data to workflow format.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The BPMN model entity.
   * @param string $label
   *   The workflow label.
   * @param string $description
   *   The workflow description.
   *
   * @return array
   *   The workflow data array with nodes and edges.
   */
  public function mapToWorkflow(ConfigEntityInterface $model, string $label, string $description = ''): array {
    $events = $model->get('events') ?? [];
    $conditions = $model->get('conditions') ?? [];
    $gateways = $model->get('gateways') ?? [];
    $actions = $model->get('actions') ?? [];

    $nodes = [];
    $edges = [];

    // Map events to nodes.
    foreach ($events as $eventId => $eventData) {
      $nodeId = $this->generateNodeId($eventId, 'event');
      $nodes[] = [
        'id' => $nodeId,
        'type' => 'event',
        'label' => $eventData['label'] ?? $eventId,
        'data' => [
          'plugin' => $eventData['plugin'] ?? '',
          'configuration' => $eventData['configuration'] ?? [],
          'originalId' => $eventId,
        ],
      ];

      // Create edges from events to their successors.
      if (isset($eventData['successors'])) {
        foreach ($eventData['successors'] as $successor) {
          $edges[] = $this->createEdge($nodeId, $successor['id'], $successor['condition'] ?? '');
        }
      }
    }

    foreach ($gateways as $gatewayId => $gatewayData) {
      $nodeId = $this->generateNodeId($gatewayId, 'gateway');
      $nodes[] = [
        'id' => $nodeId,
        'type' => 'gateway',
        'label' => $this->getGatewayLabel($gatewayData['type'] ?? 0),
        'data' => [
          'gatewayType' => $gatewayData['type'] ?? 0,
          'originalId' => $gatewayId,
        ],
      ];

      // Create edges from gateways to their successors.
      if (isset($gatewayData['successors'])) {
        foreach ($gatewayData['successors'] as $successor) {
          $edges[] = $this->createEdge($nodeId, $successor['id'], $successor['condition'] ?? '');
        }
      }
    }

    // Map actions to nodes.
    foreach ($actions as $actionId => $actionData) {
      $nodeId = $this->generateNodeId($actionId, 'action');
      $nodes[] = [
        'id' => $nodeId,
        'type' => 'action',
        'label' => $actionData['label'] ?? $actionId,
        'data' => [
          'plugin' => $actionData['plugin'] ?? '',
          'configuration' => $actionData['configuration'] ?? [],
          'originalId' => $actionId,
        ],
      ];

      // Create edges from actions to their successors.
      if (isset($actionData['successors'])) {
        foreach ($actionData['successors'] as $successor) {
          $edges[] = $this->createEdge($nodeId, $successor['id'], $successor['condition'] ?? '');
        }
      }
    }

    // Update edge target IDs to use the new node IDs.
    $edges = $this->updateEdgeTargets($edges, $events, $conditions, $gateways, $actions);

    return [
      'id' => $model->id(),
      'label' => $label,
      'description' => $description,
      'nodes' => $nodes,
      'edges' => $edges,
      'metadata' => [
        'bpmn' => [
          'events' => $events,
          'conditions' => $conditions,
          'gateways' => $gateways,
          'actions' => $actions,
        ],
      ],
      'created' => NULL,
      'changed' => NULL,
    ];
  }

  /**
   * Generates a unique node ID from the original BPMN ID.
   *
   * @param string $originalId
   *   The original BPMN component ID.
   * @param string $type
   *   The node type (event, condition, gateway, action).
   *
   * @return string
   *   The generated node ID.
   */
  protected function generateNodeId(string $originalId, string $type): string {
    // Clean the original ID and create a valid node ID.
    $cleanId = preg_replace('/[^a-zA-Z0-9_]/', '_', $originalId);
    return sprintf('%s_%s', $type, $cleanId);
  }

  /**
   * Creates an edge between two nodes.
   *
   * @param string $sourceId
   *   The source node ID.
   * @param string $targetId
   *   The target node ID.
   * @param string $condition
   *   The edge condition.
   *
   * @return array
   *   The edge data array.
   */
  protected function createEdge(string $sourceId, string $targetId, string $condition = ''): array {
    return [
      'id' => sprintf('edge_%s_to_%s', $sourceId, $targetId),
      'source' => $sourceId,
      'target' => $targetId,
      'label' => $condition ?: '',
      'data' => [
        'condition' => $condition,
      ],
    ];
  }

  /**
   * Updates edge target IDs to use the new node IDs.
   *
   * @param array $edges
   *   The edges array.
   * @param array $events
   *   The events data.
   * @param array $conditions
   *   The conditions data.
   * @param array $gateways
   *   The gateways data.
   * @param array $actions
   *   The actions data.
   *
   * @return array
   *   The updated edges array.
   */
  protected function updateEdgeTargets(array $edges, array $events, array $conditions, array $gateways, array $actions): array {
    $idMapping = [];

    // Build mapping from original IDs to new node IDs.
    foreach ($events as $eventId => $eventData) {
      $idMapping[$eventId] = $this->generateNodeId($eventId, 'event');
    }

    foreach ($conditions as $conditionId => $conditionData) {
      $idMapping[$conditionId] = $this->generateNodeId($conditionId, 'condition');
    }

    foreach ($gateways as $gatewayId => $gatewayData) {
      $idMapping[$gatewayId] = $this->generateNodeId($gatewayId, 'gateway');
    }

    foreach ($actions as $actionId => $actionData) {
      $idMapping[$actionId] = $this->generateNodeId($actionId, 'action');
    }

    // Update edge targets.
    foreach ($edges as &$edge) {
      if (isset($idMapping[$edge['target']])) {
        $edge['target'] = $idMapping[$edge['target']];
      }
      if (isset($idMapping[$edge['source']])) {
        $edge['source'] = $idMapping[$edge['source']];
      }
    }

    return $edges;
  }

  /**
   * Gets a human-readable label for gateway types.
   *
   * @param int $gatewayType
   *   The gateway type number.
   *
   * @return string
   *   The gateway label.
   */
  protected function getGatewayLabel(int $gatewayType): string {
    $labels = [
      0 => 'Exclusive Gateway',
      1 => 'Parallel Gateway',
      2 => 'Inclusive Gateway',
      3 => 'Event Gateway',
    ];

    return $labels[$gatewayType] ?? 'Gateway';
  }

  /**
   * Validates the BPMN model data structure.
   *
   * @param array $events
   *   The events data.
   * @param array $conditions
   *   The conditions data.
   * @param array $gateways
   *   The gateways data.
   * @param array $actions
   *   The actions data.
   *
   * @return bool
   *   TRUE if the data is valid, FALSE otherwise.
   */
  public function validateBpmnData(array $events, array $conditions, array $gateways, array $actions): bool {
    // Basic validation - check if required fields exist.
    foreach ($events as $eventId => $eventData) {
      if (!isset($eventData['label']) || !isset($eventData['plugin'])) {
        return FALSE;
      }
    }

    foreach ($conditions as $conditionId => $conditionData) {
      if (!isset($conditionData['label']) || !isset($conditionData['plugin'])) {
        return FALSE;
      }
    }

    foreach ($gateways as $gatewayId => $gatewayData) {
      if (!isset($gatewayData['type'])) {
        return FALSE;
      }
    }

    foreach ($actions as $actionId => $actionData) {
      if (!isset($actionData['label']) || !isset($actionData['plugin'])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Extracts metadata from BPMN components.
   *
   * @param array $events
   *   The events data.
   * @param array $conditions
   *   The conditions data.
   * @param array $gateways
   *   The gateways data.
   * @param array $actions
   *   The actions data.
   *
   * @return array
   *   The extracted metadata.
   */
  public function extractMetadata(array $events, array $conditions, array $gateways, array $actions): array {
    $metadata = [
      'componentCounts' => [
        'events' => count($events),
        'conditions' => count($conditions),
        'gateways' => count($gateways),
        'actions' => count($actions),
      ],
      'plugins' => [],
      'gatewayTypes' => [],
    ];

    // Extract unique plugins.
    $allComponents = array_merge($events, $conditions, $actions);
    foreach ($allComponents as $component) {
      if (isset($component['plugin'])) {
        $metadata['plugins'][] = $component['plugin'];
      }
    }
    $metadata['plugins'] = array_unique($metadata['plugins']);

    // Extract gateway types.
    foreach ($gateways as $gateway) {
      if (isset($gateway['type'])) {
        $metadata['gatewayTypes'][] = $gateway['type'];
      }
    }
    $metadata['gatewayTypes'] = array_unique($metadata['gatewayTypes']);

    return $metadata;
  }

}
