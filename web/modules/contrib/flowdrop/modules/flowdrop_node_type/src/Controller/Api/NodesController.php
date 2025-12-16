<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_type\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for node type API endpoints.
 */
final class NodesController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node executor plugin manager.
   *
   * @var \Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager
   */
  protected $pluginManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, FlowDropNodeProcessorPluginManager $plugin_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('flowdrop.node_processor_plugin_manager')
    );
  }

  /**
   * Get all node types.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with all node types.
   */
  public function getNodes(): JsonResponse {
    try {
      $storage = $this->entityTypeManager->getStorage('flowdrop_node_type');
      $entities = $storage->loadMultiple();

      $nodes = [];
      foreach ($entities as $entity) {
        if ($entity instanceof FlowDropNodeTypeInterface && $entity->isEnabled()) {
          $node_data = $this->buildNodeData($entity);
          if ($node_data) {
            $nodes[] = $node_data;
          }
        }
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $nodes,
        'count' => count($nodes),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get node type metadata.
   *
   * @param string $node_type_id
   *   The node type ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with node type metadata.
   */
  public function getNodeMetadata(string $node_type_id): JsonResponse {
    try {
      $entity = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($node_type_id);

      if (!$entity instanceof FlowDropNodeTypeInterface) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Node type not found',
        ], Response::HTTP_NOT_FOUND);
      }

      $metadata = $this->buildNodeData($entity);
      if (!$metadata) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to build node metadata',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'node_type_id' => $node_type_id,
          'metadata' => $metadata,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get node types by category.
   *
   * @param string $category
   *   The category to filter by (plural form from API).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with filtered node types.
   */
  public function getNodesByCategory(string $category): JsonResponse {
    try {
      // Transform plural category back to singular for database query.
      $singular_category = $this->transformCategoryToSingular($category);

      $storage = $this->entityTypeManager->getStorage('flowdrop_node_type');
      $ids = $storage->getQuery()
        ->condition('category', $singular_category)
        ->accessCheck(FALSE)
        ->execute();
      $entities = $storage->loadMultiple($ids);

      $nodes = [];
      foreach ($entities as $entity) {
        if ($entity instanceof FlowDropNodeTypeInterface && $entity->isEnabled()) {
          $node_data = $this->buildNodeData($entity);
          if ($node_data) {
            $nodes[] = $node_data;
          }
        }
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $nodes,
        'count' => count($nodes),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Validate node type configuration.
   *
   * @param string $node_type_id
   *   The node type ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with validation results.
   */
  public function validateConfiguration(string $node_type_id, Request $request): JsonResponse {
    try {
      $content = json_decode($request->getContent(), TRUE);

      if (!$content) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON in request body',
        ], Response::HTTP_BAD_REQUEST);
      }

      $config = $content['config'] ?? [];

      // Load the node type entity.
      $entity = $this->entityTypeManager->getStorage('flowdrop_node_type')->load($node_type_id);

      if (!$entity instanceof FlowDropNodeTypeInterface) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Node type not found',
        ], Response::HTTP_NOT_FOUND);
      }

      // Get plugin schema for validation.
      $plugin_schema = $this->getPluginConfigSchema($entity->getExecutorPlugin());
      $entity_schema = $entity->getConfig();

      // Merge schemas (plugin schema takes precedence)
      $schema = array_merge($entity_schema, $plugin_schema);

      $errors = [];
      $properties = $schema['properties'] ?? [];

      foreach ($properties as $property_name => $property_schema) {
        if (isset($property_schema['required']) && $property_schema['required']) {
          if (!array_key_exists($property_name, $config)) {
            $errors[] = "Required property '{$property_name}' is missing";
          }
        }

        if (array_key_exists($property_name, $config)) {
          $value = $config[$property_name];
          $expected_type = $property_schema['type'] ?? 'mixed';

          if (!$this->validateValueType($value, $expected_type)) {
            $errors[] = "Property '{$property_name}' must be of type '{$expected_type}'";
          }
        }
      }

      $result = [
        'valid' => empty($errors),
        'errors' => $errors,
      ];

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'node_type_id' => $node_type_id,
          'validation_result' => $result,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Build complete node data by merging entity and plugin data.
   *
   * @param \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface $flowdropNodeType
   *   The node type entity.
   *
   * @return array|null
   *   The complete node data or NULL if plugin not found.
   */
  protected function buildNodeData($flowdropNodeType): ?array {
    try {
      $executor_plugin = $flowdropNodeType->getExecutorPlugin();

      // Get plugin data if available.
      $inputs = [];
      $outputs = [];
      $config_schema = [];

      if ($executor_plugin && $this->pluginManager->hasDefinition($executor_plugin)) {
        $node_processor = $this->pluginManager->createInstance($executor_plugin);
        $config_schema = $node_processor->getConfigSchema();
        $input_schema = $node_processor->getInputSchema();
        $output_schema = $node_processor->getOutputSchema();
        // @todo Introduce a service to allow altering of PluginManager data during runtime.
        // This means, other modules can alter the
        // - schema
        // - introduce access check
        // - modify schema
        // This is because, the flowdrop_node_processor_info is too early
        // and has little context.
        // Transform input schema to inputs array.
        if (!empty($input_schema)) {
          $modified_input_schema = $input_schema;
          if (empty($input_schema['properties']['trigger'])) {
            $modified_input_schema['properties']['trigger'] = [
              'type' => 'trigger',
              'title' => 'Trigger',
              'required' => FALSE,
            ];
          }
          $inputs = $this->transformSchemaToPorts($modified_input_schema, 'input');
        }

        // Transform output schema to outputs array.
        if (!empty($output_schema)) {
          $outputs = $this->transformSchemaToPorts($output_schema, 'output');
        }
      }
      // Get node type from plugin if available, otherwise use default.
      $node_type = 'default';
      $supported_types = NULL;
      if ($executor_plugin && $this->pluginManager->hasDefinition($executor_plugin)) {
        $node_processor = $this->pluginManager->createInstance($executor_plugin);
        $node_type = $node_processor->getType();

        // Get supported types from plugin definition.
        $plugin_definition = $this->pluginManager->getDefinition($executor_plugin);
        if (isset($plugin_definition['supportedTypes']) && is_array($plugin_definition['supportedTypes'])) {
          $supported_types = $plugin_definition['supportedTypes'];
        }
        else {
          $supported_types = [$node_type];
        }
      }
      // Build the complete node data.
      $node_data = [
        'id' => $flowdropNodeType->id(),
        'name' => $flowdropNodeType->label(),
        'type' => $node_type,
        'supportedTypes' => $supported_types,
        'description' => $flowdropNodeType->getDescription(),
        'category' => $this->transformCategoryToPlural($flowdropNodeType->getCategory()),
        'icon' => $flowdropNodeType->getIcon(),
        'color' => $flowdropNodeType->getColor(),
        'version' => $flowdropNodeType->getVersion(),
        'enabled' => $flowdropNodeType->isEnabled(),
        'tags' => $flowdropNodeType->getTags(),
        'executor_plugin' => $executor_plugin,
        'inputs' => $inputs,
        'outputs' => $outputs,
        'config' => $flowdropNodeType->getConfig(),
        'configSchema' => $config_schema,
      ];

      return $node_data;

    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop')->error('Failed to build node data for @id: @error', [
        '@id' => $flowdropNodeType->id(),
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Get plugin configuration schema.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return array
   *   The plugin configuration schema.
   */
  protected function getPluginConfigSchema(string $plugin_id): array {
    try {
      if ($this->pluginManager->hasDefinition($plugin_id)) {
        $plugin = $this->pluginManager->createInstance($plugin_id);
        return $plugin->getConfigSchema();
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop')->error('Failed to get plugin schema for @id: @error', [
        '@id' => $plugin_id,
        '@error' => $e->getMessage(),
      ]);
    }

    return [];
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
      'trigger' => 'triggers',
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
      'tool' => 'tool',
      'mixed' => 'mixed',
      'trigger' => 'trigger',
      'branch' => 'branch',
    ];

    return $type_mapping[$schema_type] ?? 'string';
  }

  /**
   * Transform plural category to singular form for database queries.
   *
   * @param string $category
   *   The plural category.
   *
   * @return string
   *   The singular category.
   */
  protected function transformCategoryToSingular(string $category): string {
    $category_mapping = [
      'inputs' => 'input',
      'outputs' => 'output',
      'models' => 'model',
      'prompts' => 'prompt',
      'processing' => 'processing',
      'logic' => 'logic',
      'data' => 'data',
      'helpers' => 'helper',
      'tools' => 'tool',
      'vectorstores' => 'vectorstore',
      'embeddings' => 'embedding',
      'memories' => 'memory',
      'agents' => 'agent',
      'bundles' => 'bundle',
    ];

    return $category_mapping[$category] ?? $category;
  }

  /**
   * Validate value type.
   *
   * @param mixed $value
   *   The value to validate.
   * @param string $expected_type
   *   The expected type.
   *
   * @return bool
   *   TRUE if the value matches the expected type.
   */
  protected function validateValueType($value, string $expected_type): bool {
    switch ($expected_type) {
      case 'string':
        return is_string($value);

      case 'integer':
        return is_int($value);

      case 'number':
        return is_float($value) || is_int($value);

      case 'boolean':
        return is_bool($value);

      case 'array':
        return is_array($value);

      case 'object':
        return is_array($value) || is_object($value);

      case 'mixed':
        return TRUE;

      default:
        return TRUE;
    }
  }

  /**
   * Get port configuration for the FlowDrop system.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with port configuration.
   */
  public function getPortConfiguration(): JsonResponse {
    // Get port configuration from Drupal configuration.
    $config = $this->config('flowdrop_node_type.port_config');

    // Default port configuration if none exists in Drupal.
    $defaultConfig = [
      'version' => '1.0.0',
      'defaultDataType' => 'string',
      'dataTypes' => [
        [
          'id' => 'mixed',
          'name' => 'Mixed Data',
          'description' => 'Mixed data that can be bool, int, string, ...',
          'color' => 'var(--color-ref-teal-500)',
          'category' => 'temporal',
          'enabled' => TRUE,
        ],
        // Text and basic types.
        [
          'id' => 'string',
          'name' => 'String',
          'description' => 'Text data',
          'color' => 'var(--color-ref-emerald-500)',
          'category' => 'basic',
          'enabled' => TRUE,
        ],

        // Numeric types.
        [
          'id' => 'number',
          'name' => 'Number',
          'description' => 'Numeric data',
          'color' => 'var(--color-ref-blue-600)',
          'category' => 'numeric',
          'enabled' => TRUE,
        ],

        // Boolean types.
        [
          'id' => 'boolean',
          'name' => 'Boolean',
          'description' => 'True/false values',
          'color' => 'var(--color-ref-purple-600)',
          'category' => 'logical',
          'enabled' => TRUE,
        ],

        // Collection types.
        [
          'id' => 'array',
          'name' => 'Array',
          'description' => 'Ordered list of items',
          'color' => 'var(--color-ref-amber-500)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],

        // Typed arrays.
        [
          'id' => 'string[]',
          'name' => 'String Array',
          'description' => 'Array of strings',
          'color' => 'var(--color-ref-emerald-400)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],
        [
          'id' => 'number[]',
          'name' => 'Number Array',
          'description' => 'Array of numbers',
          'color' => 'var(--color-ref-blue-400)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],
        [
          'id' => 'boolean[]',
          'name' => 'Boolean Array',
          'description' => 'Array of true/false values',
          'color' => 'var(--color-ref-purple-400)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],
        [
          'id' => 'json[]',
          'name' => 'JSON Array',
          'description' => 'Array of JSON objects',
          'color' => 'var(--color-ref-orange-400)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],
        [
          'id' => 'file[]',
          'name' => 'File Array',
          'description' => 'Array of files',
          'color' => 'var(--color-ref-red-400)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],
        [
          'id' => 'image[]',
          'name' => 'Image Array',
          'description' => 'Array of images',
          'color' => 'var(--color-ref-pink-400)',
          'category' => 'collection',
          'enabled' => TRUE,
        ],

        // Complex types.
        [
          'id' => 'json',
          'name' => 'JSON',
          'description' => 'JSON structured data',
          'color' => 'var(--color-ref-orange-500)',
          'category' => 'complex',
          'enabled' => TRUE,
        ],

        // File types.
        [
          'id' => 'file',
          'name' => 'File',
          'description' => 'File data',
          'color' => 'var(--color-ref-red-500)',
          'category' => 'file',
          'enabled' => TRUE,
        ],

        // Media types.
        [
          'id' => 'image',
          'name' => 'Image',
          'description' => 'Image data',
          'color' => 'var(--color-ref-pink-500)',
          'category' => 'media',
          'enabled' => TRUE,
        ],
        [
          'id' => 'audio',
          'name' => 'Audio',
          'description' => 'Audio data',
          'color' => 'var(--color-ref-indigo-500)',
          'category' => 'media',
          'enabled' => TRUE,
        ],
        [
          'id' => 'video',
          'name' => 'Video',
          'description' => 'Video data',
          'color' => 'var(--color-ref-teal-500)',
          'category' => 'media',
          'enabled' => TRUE,
        ],

        // Special types.
        [
          'id' => 'url',
          'name' => 'URL',
          'description' => 'Web address',
          'color' => 'var(--color-ref-cyan-500)',
          'category' => 'special',
          'enabled' => TRUE,
        ],
        [
          'id' => 'email',
          'name' => 'Email',
          'description' => 'Email address',
          'color' => 'var(--color-ref-cyan-500)',
          'category' => 'special',
          'enabled' => TRUE,
        ],
        [
          'id' => 'date',
          'name' => 'Date',
          'description' => 'Date value',
          'color' => 'var(--color-ref-lime-500)',
          'category' => 'temporal',
          'enabled' => TRUE,
        ],
        [
          'id' => 'datetime',
          'name' => 'DateTime',
          'description' => 'Date and time value',
          'color' => 'var(--color-ref-lime-500)',
          'category' => 'temporal',
          'enabled' => TRUE,
        ],
        [
          'id' => 'time',
          'name' => 'Time',
          'description' => 'Time value',
          'color' => 'var(--color-ref-lime-500)',
          'category' => 'temporal',
          'enabled' => TRUE,
        ],
      ],

      'compatibilityRules' => [
        // Pure same-type compatibility: string connects to string,
        // number to number, etc.
        // No additional rules needed - the system
        // handles same-type connections automatically.
      ],
    ];

    // Use custom configuration if available, otherwise use default.
    $portConfig = $config->get('config') ?: $defaultConfig;

    return new JsonResponse([
      'success' => TRUE,
      'data' => $portConfig,
      'message' => 'Port configuration loaded successfully',
    ]);
  }

}
