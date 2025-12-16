<?php

namespace Drupal\flowdrop_modeler\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Service to provide available node types for the FlowDrop editor.
 *
 * This service manages the available node types that can be dragged
 * from the sidebar into the workflow canvas, based on available
 * owner components.
 */
class AvailableNodeTypesService {

  public function __construct(
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {}

  /**
   * Gets all available node types for the FlowDrop editor.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner interface.
   *
   * @return array
   *   Array of available node types with their configurations.
   */
  public function getAvailableNodeTypes(ModelOwnerInterface $owner): array {
    $nodeTypes = [];

    // Get supported component types from the owner.
    $supportedTypes = $owner->supportedOwnerComponentTypes();

    foreach ($supportedTypes as $typeKey => $typeName) {
      $components = $owner->availableOwnerComponents($typeKey);
      $nodeTypes = array_merge($nodeTypes, $this->convertComponentsToNodeTypes($components, $typeKey, $typeName));
    }

    return $nodeTypes;
  }

  /**
   * Converts owner components to node types for the FlowDrop editor.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $components
   *   Array of available components.
   * @param int $typeKey
   *   The component type key.
   * @param string $typeName
   *   The component type name.
   *
   * @return array
   *   Array of node types converted from components.
   */
  protected function convertComponentsToNodeTypes(array $components, int $typeKey, string $typeName): array {
    $nodeTypes = [];
    foreach ($components as $component) {
      $nodeType = $this->convertComponentToNodeType($component, $typeKey, $typeName);
      if ($nodeType !== NULL) {
        $nodeTypes[] = $nodeType;
      }
    }
    return $nodeTypes;
  }

  /**
   * Converts a single component to a node type.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $component
   *   The component to convert.
   * @param int $typeKey
   *   The component type key.
   * @param string $typeName
   *   The component type name.
   *
   * @return array|null
   *   The node type configuration or null if conversion fails.
   */
  protected function convertComponentToNodeType(PluginInspectionInterface $component, int $typeKey, string $typeName): ?array {
    try {
      $pluginId = $component->getPluginId();
      $pluginDefinition = $component->getPluginDefinition();

      // Map component type to node type category.
      $category = $this->mapComponentTypeToCategory($typeKey);
      $color = $this->getColorForComponentType($typeKey);
      $icon = $this->getIconForComponentType($typeKey);

      // Determine supported node types based on component type and category.
      $supportedTypes = $this->getSupportedNodeTypesForComponent($typeKey, $category);
      $primaryType = $supportedTypes[0] ?? 'default';

      return [
        'id' => $pluginId,
        'name' => $pluginDefinition['label'] ?? $pluginId,
        'type' => $primaryType,
        'supportedTypes' => $supportedTypes,
        'description' => $pluginDefinition['description'] ?? $typeName,
        'category' => $category,
        'version' => '1.0.0',
        'color' => $color,
        'inputs' => $this->getInputsForComponentType($typeKey),
        'outputs' => $this->getOutputsForComponentType($typeKey),
        'tags' => [$category],
        'icon' => $icon,
        'metadata' => [
          'category' => $category,
          'version' => '1.0.0',
          'color' => $color,
          'tags' => [$category],
          'pluginId' => $pluginId,
          'componentType' => $typeKey,
          'componentTypeName' => $typeName,
        ],
      ];
    }
    catch (\Exception $e) {
      // Log error and return null for this component.
      $this->loggerChannelFactory->get('flowdrop_modeler')->error('Failed to convert component to node type: @error', [
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Maps component type to category.
   *
   * @param int $typeKey
   *   The component type key.
   *
   * @return string
   *   The category name.
   */
  protected function mapComponentTypeToCategory(int $typeKey): string {
    $categoryMap = [
    // Assuming 0 is events.
      Api::COMPONENT_TYPE_START => 'events',
    // Assuming 1 is conditions.
      Api::COMPONENT_TYPE_LINK => 'conditions',
    // Assuming 2 is gateways.
      Api::COMPONENT_TYPE_GATEWAY => 'gateways',
    // Assuming 3 is actions.
      Api::COMPONENT_TYPE_ELEMENT => 'actions',
    ];

    return $categoryMap[$typeKey] ?? 'unknown';
  }

  /**
   * Gets color for component type.
   *
   * @param int $typeKey
   *   The component type key.
   *
   * @return string
   *   The color hex code.
   */
  protected function getColorForComponentType(int $typeKey): string {
    $colorMap = [
    // Blue for events.
      Api::COMPONENT_TYPE_START => '#3b82f6',
    // Amber for conditions.
      Api::COMPONENT_TYPE_LINK => '#f59e0b',
    // Purple for gateways.
      Api::COMPONENT_TYPE_GATEWAY => '#8b5cf6',
    // Emerald for actions.
      Api::COMPONENT_TYPE_ELEMENT => '#10b981',
    ];

    // Gray fallback.
    return $colorMap[$typeKey] ?? '#6b7280';
  }

  /**
   * Gets icon for component type.
   *
   * @param int $typeKey
   *   The component type key.
   *
   * @return string
   *   The icon name.
   */
  protected function getIconForComponentType(int $typeKey): string {
    $iconMap = [
    // Events.
      Api::COMPONENT_TYPE_START  => 'play-circle',
    // Conditions.
      Api::COMPONENT_TYPE_LINK  => 'git-branch',
    // Gateways.
      Api::COMPONENT_TYPE_GATEWAY  => 'git-merge',
    // Actions.
      Api::COMPONENT_TYPE_ELEMENT => 'settings',
    ];

    return $iconMap[$typeKey] ?? 'circle';
  }

  /**
   * Gets inputs for component type.
   *
   * @param int $typeKey
   *   The component type key.
   *
   * @return array
   *   Array of input definitions.
   */
  protected function getInputsForComponentType(int $typeKey): array {
    $inputMap = [
    // Events have no inputs.
      Api::COMPONENT_TYPE_START => [],
      Api::COMPONENT_TYPE_LINK => [
        [
          'id' => 'input',
          'name' => 'Input',
          'type' => 'input',
          'dataType' => 'any',
          'required' => TRUE,
          'description' => 'Input value to evaluate',
        ],
      ],
      Api::COMPONENT_TYPE_GATEWAY => [
        [
          'id' => 'input',
          'name' => 'Input',
          'type' => 'input',
          'dataType' => 'any',
          'required' => TRUE,
          'description' => 'Input for gateway evaluation',
        ],
      ],
      Api::COMPONENT_TYPE_ELEMENT => [
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

    return $inputMap[$typeKey] ?? [];
  }

  /**
   * Gets outputs for component type.
   *
   * @param int $typeKey
   *   The component type key.
   *
   * @return array
   *   Array of output definitions.
   */
  protected function getOutputsForComponentType(int $typeKey): array {
    $outputMap = [
    // Events.
      Api::COMPONENT_TYPE_START => [
        [
          'id' => 'trigger',
          'name' => 'Trigger',
          'type' => 'output',
          'dataType' => 'string',
          'description' => 'Event trigger output',
        ],
      ],
      // Conditions.
      Api::COMPONENT_TYPE_LINK => [
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
      // Gateways.
      Api::COMPONENT_TYPE_GATEWAY => [
        [
          'id' => 'output1',
          'name' => 'Output 1',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'First gateway output',
        ],
        [
          'id' => 'output2',
          'name' => 'Output 2',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Second gateway output',
        ],
      ],
      // Actions.
      Api::COMPONENT_TYPE_ELEMENT => [
        [
          'id' => 'output',
          'name' => 'Output',
          'type' => 'output',
          'dataType' => 'any',
          'description' => 'Action output data',
        ],
      ],
    ];

    return $outputMap[$typeKey] ?? [];
  }

  /**
   * Gets supported node types for a component type and category.
   *
   * @param int $typeKey
   *   The component type key.
   * @param string $category
   *   The category name.
   *
   * @return array
   *   Array of supported node types.
   */
  protected function getSupportedNodeTypesForComponent(int $typeKey, string $category): array {
    // Define supported node types based on component type and category.
    $supportedTypesMap = [
      // Events can be simple or default.
      Api::COMPONENT_TYPE_START => ['simple', 'default'],
      // Conditions can be simple or default.
      Api::COMPONENT_TYPE_LINK => ['simple', 'default'],
      // Gateways are typically default.
      Api::COMPONENT_TYPE_GATEWAY => ['default'],
      // Actions can be simple or default.
      Api::COMPONENT_TYPE_ELEMENT => ['simple', 'default'],
    ];

    // Category-based overrides.
    $categoryOverrides = [
    // Actions can be tools.
      'actions' => ['tool', 'default', 'simple'],
      'events' => ['simple', 'default'],
      'conditions' => ['simple', 'default'],
      'gateways' => ['default'],
    ];

    // Use category override if available, otherwise use component type mapping.
    if (isset($categoryOverrides[$category])) {
      return $categoryOverrides[$category];
    }

    return $supportedTypesMap[$typeKey] ?? ['default'];
  }

}
