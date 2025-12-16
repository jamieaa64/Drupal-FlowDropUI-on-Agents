<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Drupal\tool\TypedData\MapInputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the field storage add tool.
 */
#[Tool(
  id: 'field_storage_add',
  label: new TranslatableMarkup('Add field storage'),
  description: new TranslatableMarkup('Creates a new field storage configuration for an entity type.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type."),
      constraints: [
        'PluginExists' => [
          'manager' => 'entity_type.manager',
          'interface' => ContentEntityInterface::class,
        ],
      ],
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('The machine name for the field (e.g., field_tags).'),
      constraints: [
        'Regex' => [
          'pattern' => '/^field_[a-z0-9_]+$/',
          'message' => 'Field name must start with "field_" and contain only lowercase letters, numbers, and underscores.',
        ],
      ],
    ),
    'field_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Type'),
      description: new TranslatableMarkup('The field type (e.g., string, text, entity_reference).'),
      constraints: [
        'PluginExists' => [
          'manager' => 'plugin.manager.field.field_type',
        ],
      ],
    ),
    'cardinality' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Cardinality'),
      description: new TranslatableMarkup('The number of values the field can hold. Use -1 for unlimited.'),
      required: FALSE,
      default_value: 1,
    ),
    'translatable' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Translatable'),
      description: new TranslatableMarkup('Whether the field is translatable. Only needed if Content Translation is enabled.'),
      required: FALSE,
      default_value: FALSE,
    ),
    'settings' => new MapInputDefinition(
      label: new TranslatableMarkup('Storage Settings'),
      description: new TranslatableMarkup('Field type-specific storage settings.'),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'settings' => ['field_type'],
  ],
)]
class FieldStorageAdd extends ToolBase implements ContainerFactoryPluginInterface, InputDefinitionRefinerInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The field type plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected FieldTypePluginManagerInterface $fieldTypePluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    [
      'entity_type_id' => $entity_type_id,
      'field_name' => $field_name,
      'field_type' => $field_type,
      'cardinality' => $cardinality,
      'translatable' => $translatable,
      'settings' => $settings,
    ] = $values;

    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      // Check if field storage already exists.
      $existing_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      if ($existing_storage) {
        return ExecutableResult::failure($this->t('Field storage @field already exists for entity type @type', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
        ]));
      }

      // Get field type definition.
      $field_type_definitions = $this->fieldTypePluginManager->getDefinitions();
      if (!isset($field_type_definitions[$field_type])) {
        return ExecutableResult::failure($this->t('Field type "@type" does not exist.', [
          '@type' => $field_type,
        ]));
      }

      // Prepare field storage values.
      $storage_values = [
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'type' => $field_type,
        'cardinality' => $cardinality,
        'translatable' => $translatable,
      ];

      // Add type-specific settings if provided.
      if (!empty($settings)) {
        $storage_values['settings'] = $settings;
      }

      // Create the field storage.
      $field_storage = FieldStorageConfig::create($storage_values);
      $field_storage->save();

      return ExecutableResult::success($this->t('Successfully created field storage @field for entity type @type', [
        '@field' => $field_name,
        '@type' => $entity_type_id,
      ]));

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error creating field storage: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to administer fields.
    $result = AccessResult::allowedIfHasPermission($account, 'administer ' . $values['entity_type_id'] . ' fields')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer site configuration'));

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface {
    if ($name === 'settings' && !empty($values['field_type'])) {
      // Get the field type definition and build settings input definition.
      $field_type_definitions = $this->fieldTypePluginManager->getDefinitions();
      if (isset($field_type_definitions[$values['field_type']])) {
        $definition = EntityBundleFieldDefinitions::getSettingsInputDefinition($field_type_definitions[$values['field_type']], 'storage');
      }
    }
    return $definition;
  }

}
