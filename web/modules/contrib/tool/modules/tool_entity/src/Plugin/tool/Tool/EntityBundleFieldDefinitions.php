<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity bundle field definitions tool.
 */
#[Tool(
  id: 'entity_bundle_field_definitions',
  label: new TranslatableMarkup('Get field configuration definitions'),
  description: new TranslatableMarkup('Get detailed field and storage configuration, and settings schemas for an entity type and bundle.'),
  operation: ToolOperation::Explain,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The entity type ID (e.g., node, user, taxonomy_term).")
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle name (e.g., article, page for nodes).")
    ),
  ],
  output_definitions: [
    'field_definitions' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Field Definitions"),
      description: new TranslatableMarkup("Array of field storage and instance settings and schemas for provided bundle.")
    ),
    'available_storages' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Available Storages"),
      description: new TranslatableMarkup("Field storages available but not used by this bundle.")
    ),
  ],
)]
class EntityBundleFieldDefinitions extends ToolBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The field type plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected FieldTypePluginManagerInterface $fieldTypePluginManager;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    $instance->serializer = $container->get('serializer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle] = $values;
    // @todo Simplify with normalizer.
    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      // Validate bundle exists.
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      if (!isset($bundle_info[$bundle])) {
        return ExecutableResult::failure($this->t('Bundle "@bundle" does not exist for entity type "@type".', [
          '@bundle' => $bundle,
          '@type' => $entity_type_id,
        ]));
      }

      // Get all field storages for this entity type.
      $all_storages = FieldStorageConfig::loadMultiple();
      $field_storages_info = [];

      // Get bundle field definitions to know which are used.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      $field_type_definitions = $this->fieldTypePluginManager->getDefinitions();

      foreach ($all_storages as $field_storage) {
        // Filter by entity type.
        if ($field_storage->getTargetEntityTypeId() !== $entity_type_id) {
          continue;
        }

        $field_name = $field_storage->getName();
        $field_type = $field_storage->getType();

        // Get field type definitions for schema.
        $storage_settings_schema = NULL;
        $settings_definition = self::getSettingsInputDefinition($field_type_definitions[$field_type], 'storage');
        $storage_settings_schema = $this->serializer->normalize($settings_definition, 'json_schema');
        // @todo Consider using form and item defaults for better accuracy.
        $field_info = [
          'field_name' => $field_name,
          'type' => $field_type,
          'storage' => [
            'id' => $field_storage->id(),
            'cardinality' => $field_storage->getCardinality(),
            'translatable' => $field_storage->isTranslatable(),
            'locked' => $field_storage->isLocked(),
            'settings' => $field_storage->getSettings(),
            'settings_schema' => $storage_settings_schema,
          ],
        ];
        // Add bundle usage information.
        $bundles_using = [];
        $field_map = $this->entityFieldManager->getFieldMap();
        if (isset($field_map[$entity_type_id][$field_name])) {
          $bundles_using = $field_map[$entity_type_id][$field_name]['bundles'];
        }
        $field_info['storage']['bundles_used_on'] = $bundles_using;

        if (isset($field_definitions[$field_name]) && $field_definitions[$field_name] instanceof FieldConfigInterface) {
          $field_definition = $field_definitions[$field_name];

          $settings_definition = self::getSettingsInputDefinition($field_type_definitions[$field_definition->getType()], 'field');
          $field_settings_schema = $this->serializer->normalize($settings_definition, 'json_schema');
          // @todo Consider using form and item defaults for better accuracy.
          $field_info['instance'] = [
            'label' => (string) $field_definition->getLabel(),
            'description' => (string) $field_definition->getDescription(),
            'required' => $field_definition->isRequired(),
            'default_value' => $field_definition->getDefaultValueLiteral(),
            'field_settings' => $field_definition->getSettings(),
            'settings_schema' => $field_settings_schema,
            'field_type' => $field_definition->getType(),
          ];
        }
        $field_storages_info[$field_name] = $field_info;

      }

      // Separate available but unused storages.
      $available_storages = array_filter($field_storages_info, function ($storage) {
        return !isset($storage['instance']);
      });

      // Filter field_storages_info to only used ones for the main output.
      $used_storages = array_filter($field_storages_info, function ($storage) {
        return isset($storage['instance']);
      });

      return ExecutableResult::success($this->t('Found @storage_count field storages (@used used, @available available) and @field_count bundle fields for @type:@bundle', [
        '@storage_count' => count($field_storages_info),
        '@used' => count($used_storages),
        '@available' => count($available_storages),
        '@field_count' => count($used_storages),
        '@type' => $entity_type_id,
        '@bundle' => $bundle,
      ]), [
        'field_definitions' => $used_storages,
        'available_storages' => $available_storages,
      ]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving field information: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to view field information.
    $result = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Get the properties input definition for a bundle config entity.
   *
   * @param array $field_type_definition
   *   The field type definition.
   * @param string $settings_type
   *   The settings type, either 'storage' or 'field'.
   *
   * @return \Drupal\tool\TypedData\MapInputDefinition
   *   The properties input definition.
   */
  public static function getSettingsInputDefinition(array $field_type_definition, string $settings_type): MapInputDefinition {
    if ($settings_type !== 'storage' && $settings_type !== 'field') {
      throw new \InvalidArgumentException('Settings type must be either "storage" or "field".');
    }
    $typed_config_manager = \Drupal::service('config.typed');
    $config_id = 'field.' . $settings_type . '_settings.' . $field_type_definition['id'];
    // @todo Consider adding defaults for common entity keys.
    if ($settings_type === 'storage') {
      $label = new TranslatableMarkup('Field Storage Settings');
      $description = new TranslatableMarkup('Settings for field storage.');
      $default_value = $field_type_definition['class']::defaultStorageSettings();
    }
    else {
      $label = new TranslatableMarkup('Field Instance Settings');
      $description = new TranslatableMarkup('Settings for the field instance.');
      $default_value = $field_type_definition['class']::defaultFieldSettings();
    }
    if ($typed_config_manager->hasConfigSchema($config_id)) {
      $config_schema = $typed_config_manager->getDefinition($config_id);
      /** @var \Drupal\tool\TypedData\MapInputDefinition $input_definition */
      $input_definition = InputDefinition::fromConfigSchema($config_schema);
    }
    else {
      // Add translatable property.
      $input_definition = new MapInputDefinition(
        label: $label,
        description: $description,
        required: FALSE,
        default_value: $default_value,
      );
    }
    return $input_definition;
  }

}
