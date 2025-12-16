<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_type\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop_node_category\FlowDropNodeCategoryInterface;
use Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;

/**
 * Service for managing flowdrop node types.
 */
class FlowDropNodeTypeManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The node executor plugin manager.
   *
   * @var \Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager
   */
  protected $pluginManager;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FlowDropNodeProcessorPluginManager $plugin_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * Get all node types.
   *
   * @return array
   *   Array of node type definitions.
   */
  public function getAllNodeTypes(): array {
    $node_types = [];
    $entities = $this->entityTypeManager->getStorage('flowdrop_node_type')->loadMultiple();

    foreach ($entities as $entity) {
      if ($entity instanceof FlowDropNodeTypeInterface && $entity->isEnabled()) {
        $node_data = $this->buildNodeData($entity);
        if ($node_data) {
          $node_types[] = $node_data;
        }
      }
    }

    return $node_types;
  }

  /**
   * Get node types by category.
   *
   * @param string $category
   *   The category to filter by.
   *
   * @return array
   *   Array of node type definitions in the specified category.
   */
  public function getNodeTypesByCategory(string $category): array {
    $node_types = [];
    $entities = $this->entityTypeManager->getStorage('flowdrop_node_type')
      ->loadByProperties(['category' => $category]);

    foreach ($entities as $entity) {
      if ($entity instanceof FlowDropNodeTypeInterface && $entity->isEnabled()) {
        $node_data = $this->buildNodeData($entity);
        if ($node_data) {
          $node_types[] = $node_data;
        }
      }
    }

    return $node_types;
  }

  /**
   * Get a specific node type by ID.
   *
   * @param string $id
   *   The node type ID.
   *
   * @return array|null
   *   The node type definition or NULL if not found.
   */
  public function getNodeType(string $id): ?array {
    $entity = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($id);

    if ($entity instanceof FlowDropNodeTypeInterface && $entity->isEnabled()) {
      return $this->buildNodeData($entity);
    }

    return NULL;
  }

  /**
   * Create a node type from a definition array.
   *
   * @param array $definition
   *   The node type definition.
   *
   * @return \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface|null
   *   The created node type entity or NULL if creation failed.
   */
  public function createNodeType(array $definition): ?FlowDropNodeTypeInterface {
    try {
      $values = [
        'id' => $definition['id'],
        'label' => $definition['name'],
        'description' => $definition['description'] ?? '',
        'category' => $definition['category'] ?? 'processing',
        'icon' => $definition['icon'] ?? 'mdi:cog',
        'color' => $definition['color'] ?? '#007cba',
        'version' => $definition['version'] ?? '1.0.0',
        'enabled' => $definition['enabled'] ?? TRUE,
        'configSchema' => $definition['configSchema'] ?? [],
        'tags' => $definition['tags'] ?? [],
        'executor_plugin' => $definition['executor_plugin'] ?? '',
      ];

      $entity = $this->entityTypeManager->getStorage('flowdrop_node_type')->create($values);
      $entity->save();

      if (!$entity instanceof FlowDropNodeTypeInterface) {
        $this->loggerFactory->get('flowdrop_node_type')->error('Error while saving node type @id', ['@id' => $definition['id']]);
        return NULL;
      }

      $this->loggerFactory->get('flowdrop_node_type')->info('Created node type @id', ['@id' => $definition['id']]);

      return $entity;

    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop_node_type')->error('Failed to create node type @id: @error', [
        '@id' => $definition['id'] ?? 'unknown',
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Update a node type from a definition array.
   *
   * @param string $id
   *   The node type ID.
   * @param array $definition
   *   The updated node type definition.
   *
   * @return \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface|null
   *   The updated node type entity or NULL if update failed.
   */
  public function updateNodeType(string $id, array $definition): ?FlowDropNodeTypeInterface {
    try {
      $entity = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($id);

      if (!$entity instanceof FlowDropNodeTypeInterface) {
        $this->loggerFactory->get('flowdrop_node_type')->error('Node type @id not found for update', ['@id' => $id]);
        return NULL;
      }

      $entity->setLabel($definition['name'] ?? $entity->label());
      $entity->setDescription($definition['description'] ?? $entity->getDescription());
      $entity->setCategory($definition['category'] ?? $entity->getCategory());
      $entity->setIcon($definition['icon'] ?? $entity->getIcon());
      $entity->setColor($definition['color'] ?? $entity->getColor());
      $entity->setVersion($definition['version'] ?? $entity->getVersion());
      $entity->setEnabled($definition['enabled'] ?? $entity->isEnabled());
      // @todo Check if we need to store input and output here.
      // $entity->setInputs($definition['inputs'] ?? $entity->getInputs());
      // $entity->setOutputs($definition['outputs'] ?? $entity->getOutputs());
      $entity->setConfig($definition['configSchema'] ?? $entity->getConfig());
      $entity->setTags($definition['tags'] ?? $entity->getTags());
      $entity->setExecutorPlugin($definition['executor_plugin'] ?? $entity->getExecutorPlugin());

      $entity->save();

      $this->loggerFactory->get('flowdrop_node_type')->info('Updated node type @id', ['@id' => $id]);

      return $entity;

    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop_node_type')->error('Failed to update node type @id: @error', [
        '@id' => $id,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Delete a node type.
   *
   * @param string $id
   *   The node type ID.
   *
   * @return bool
   *   TRUE if deletion was successful.
   */
  public function deleteNodeType(string $id): bool {
    try {
      $entity = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($id);

      if (!$entity instanceof FlowDropNodeTypeInterface) {
        $this->loggerFactory->get('flowdrop_node_type')->error('Node type @id not found for deletion', ['@id' => $id]);
        return FALSE;
      }

      $entity->delete();

      $this->loggerFactory->get('flowdrop_node_type')->info('Deleted node type @id', ['@id' => $id]);

      return TRUE;

    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop_node_type')->error('Failed to delete node type @id: @error', [
        '@id' => $id,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Import node types from the NodeTypeService mapping.
   *
   * @param array $mapping
   *   The node mapping from NodeTypeService.
   *
   * @return array
   *   Array of created/updated node types.
   */
  public function importFromMapping(array $mapping): array {
    $results = [];

    foreach ($mapping as $node_type => $definition) {
      $node_id = $definition['id'] ?? strtolower(str_replace(' ', '-', $node_type));

      // Check if node type already exists.
      $existing = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($node_id);

      if ($existing) {
        $entity = $this->updateNodeType($node_id, $definition);
        $results[] = [
          'id' => $node_id,
          'action' => 'updated',
          'success' => $entity !== NULL,
        ];
      }
      else {
        $entity = $this->createNodeType($definition);
        $results[] = [
          'id' => $node_id,
          'action' => 'created',
          'success' => $entity !== NULL,
        ];
      }
    }

    return $results;
  }

  /**
   * Get available categories.
   *
   * @return array
   *   Array of available categories with ID as key and label as value.
   */
  public function getAvailableCategories(): array {
    $categories = [];

    try {
      $category_storage = $this->entityTypeManager->getStorage('flowdrop_node_category');
      $category_entities = $category_storage->loadMultiple();

      foreach ($category_entities as $category) {
        if ($category instanceof FlowDropNodeCategoryInterface && $category->status()) {
          $categories[$category->id()] = $category->label();
        }
      }
    }
    catch (\Exception $e) {
      // Fallback to category from node types if category module
      // is not available.
      $node_type_entities = $this->entityTypeManager->getStorage('flowdrop_node_type')->loadMultiple();

      foreach ($node_type_entities as $entity) {
        if (!$entity instanceof FlowDropNodeTypeInterface) {
          continue;
        }
        $category = $entity->getCategory();
        if (!in_array($category, $categories)) {
          $categories[$category] = $category;
        }
      }
    }

    asort($categories);
    return $categories;
  }

  /**
   * Search node types by tags.
   *
   * @param array $tags
   *   Array of tags to search for.
   *
   * @return array
   *   Array of matching node type definitions.
   */
  public function searchByTags(array $tags): array {
    $node_types = [];
    $entities = $this->entityTypeManager->getStorage('flowdrop_node_type')->loadMultiple();

    foreach ($entities as $entity) {
      if (!$entity instanceof FlowDropNodeTypeInterface) {
        continue;
      }
      if (!$entity->isEnabled()) {
        continue;
      }

      $entity_tags = $entity->getTags();
      foreach ($tags as $tag) {
        if (in_array($tag, $entity_tags)) {
          $node_types[] = $entity->toNodeDefinition();
          break;
        }
      }
    }

    return $node_types;
  }

  /**
   * Build complete node data by merging entity and plugin data.
   *
   * @param \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface $entity
   *   The node type entity.
   *
   * @return array|null
   *   The complete node data or NULL if plugin not found.
   */
  protected function buildNodeData(FlowDropNodeTypeInterface $entity): ?array {
    try {
      $executor_plugin = $entity->getExecutorPlugin();

      // Get plugin data if available.
      $plugin_data = [];
      $inputs = [];
      $outputs = [];

      if ($executor_plugin && $this->pluginManager->hasDefinition($executor_plugin)) {
        $plugin = $this->pluginManager->createInstance($executor_plugin);
        $plugin_data = [
          'input_schema' => $plugin->getInputSchema(),
          'output_schema' => $plugin->getOutputSchema(),
          'configSchema' => $plugin->getConfigSchema(),
        ];

        // Transform input schema to inputs array.
        $input_schema = $plugin->getInputSchema();
        if (!empty($input_schema)) {
          $inputs = $this->transformSchemaToPorts($input_schema, 'input');
        }

        // Transform output schema to outputs array.
        $output_schema = $plugin->getOutputSchema();
        if (!empty($output_schema)) {
          $outputs = $this->transformSchemaToPorts($output_schema, 'output');
        }
      }

      // Build the complete node data.
      $node_data = [
        'id' => $entity->id(),
        'name' => $entity->label(),
        'description' => $entity->getDescription(),
        'category' => $this->transformCategoryToPlural($entity->getCategory()),
        'icon' => $entity->getIcon(),
        'color' => $entity->getColor(),
        'version' => $entity->getVersion(),
        'enabled' => $entity->isEnabled(),
        'tags' => $entity->getTags(),
        'executor_plugin' => $executor_plugin,
        'configSchema' => $entity->getConfig(),
        'inputs' => $inputs,
        'outputs' => $outputs,
      ];

      // Merge plugin data (plugin data takes precedence)
      if (!empty($plugin_data)) {
        $node_data = array_merge($node_data, $plugin_data);
      }

      return $node_data;

    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop_node_type')->error('Failed to build node data for @id: @error', [
        '@id' => $entity->id(),
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Transform schema to NodePort array format.
   *
   * @param array $schema
   *   The schema array.
   * @param string $port_type
   *   The port type ('input' or 'output').
   *
   * @return array
   *   Array of NodePort objects.
   */
  protected function transformSchemaToPorts(array $schema, string $port_type): array {
    $ports = [];

    // Handle JSON schema format (with properties)
    if (isset($schema['properties'])) {
      foreach ($schema['properties'] as $property_name => $property_schema) {
        $port = [
          'id' => $property_name,
          'name' => $property_schema['title'] ?? $property_name,
          'type' => $port_type,
          'dataType' => $this->mapSchemaTypeToDataType($property_schema['type'] ?? 'string'),
          'required' => in_array($property_name, $schema['required'] ?? []),
          'description' => $property_schema['description'] ?? '',
        ];

        if (isset($property_schema['default'])) {
          $port['defaultValue'] = $property_schema['default'];
        }

        $ports[] = $port;
      }
    }
    // Handle direct object format (for output schemas)
    else {
      foreach ($schema as $property_name => $property_schema) {
        $port = [
          'id' => $property_name,
          'name' => $property_schema['title'] ?? $property_name,
          'type' => $port_type,
          'dataType' => $this->mapSchemaTypeToDataType($property_schema['type'] ?? 'string'),
        // Outputs are typically not required.
          'required' => FALSE,
          'description' => $property_schema['description'] ?? '',
        ];

        if (isset($property_schema['default'])) {
          $port['defaultValue'] = $property_schema['default'];
        }

        $ports[] = $port;
      }
    }

    return $ports;
  }

  /**
   * Map JSON schema types to NodeDataType.
   *
   * @param string $schema_type
   *   The JSON schema type.
   *
   * @return string
   *   The NodeDataType.
   */
  protected function mapSchemaTypeToDataType(string $schema_type): string {
    $type_mapping = [
      'string' => 'string',
      'number' => 'number',
      'integer' => 'number',
      'boolean' => 'boolean',
      'array' => 'array',
      'object' => 'json',
    ];

    return $type_mapping[$schema_type] ?? 'string';
  }

  /**
   * Transform singular category to plural form for API compatibility.
   *
   * @param string $category
   *   The singular category.
   *
   * @return string
   *   The plural category.
   */
  protected function transformCategoryToPlural(string $category): string {
    $category_mapping = [
      'input' => 'inputs',
      'output' => 'outputs',
      'model' => 'models',
      'prompt' => 'prompts',
      'processing' => 'processing',
      'logic' => 'logic',
      'data' => 'data',
      'helper' => 'helpers',
      'tool' => 'tools',
      'vectorstore' => 'vectorstores',
      'embedding' => 'embeddings',
      'memory' => 'memories',
      'agent' => 'agents',
      'bundle' => 'bundles',
    ];

    return $category_mapping[$category] ?? $category;
  }

}
