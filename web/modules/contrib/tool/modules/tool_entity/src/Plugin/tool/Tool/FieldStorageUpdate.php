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
 * Plugin implementation of the field storage update tool.
 */
#[Tool(
  id: 'field_storage_update',
  label: new TranslatableMarkup('Update field storage'),
  description: new TranslatableMarkup('Updates an existing field storage configuration.'),
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
      description: new TranslatableMarkup('The machine name of the field to update (e.g., field_tags).'),
    ),
    'cardinality' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Cardinality'),
      description: new TranslatableMarkup('The number of values the field can hold. Use -1 for unlimited. Note: Can only be changed if field has no data.'),
      required: FALSE,
    ),
    'translatable' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Translatable'),
      description: new TranslatableMarkup('Whether the field is translatable. Note: Can only be changed if field has no data.'),
      required: FALSE,
    ),
    'settings' => new MapInputDefinition(
      label: new TranslatableMarkup('Storage Settings'),
      description: new TranslatableMarkup('Field type-specific storage settings to update. Note: Some settings may be locked and cannot be changed after field storage creation.'),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'settings' => ['entity_type_id', 'field_name'],
  ],
)]
class FieldStorageUpdate extends ToolBase implements ContainerFactoryPluginInterface, InputDefinitionRefinerInterface {

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

      // Load existing field storage.
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      if (!$field_storage) {
        return ExecutableResult::failure($this->t('Field storage @field does not exist for entity type @type', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
        ]));
      }

      // Check if field data exists.
      // @phpstan-ignore-next-line
      if ($field_storage->hasData()) {
        return ExecutableResult::failure($this->t('Cannot update field storage @field for entity type @type because field data already exists. Delete all content using this field first, or create a new field instead.', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
        ]));
      }

      // Track what we're updating.
      $updated = [];

      // Update cardinality if provided.
      if (isset($cardinality)) {
        $field_storage->setCardinality($cardinality);
        $updated[] = 'cardinality';
      }

      // Update translatable if provided.
      if (isset($translatable)) {
        $field_storage->setTranslatable($translatable);
        $updated[] = 'translatable';
      }

      // Update type-specific settings if provided.
      if (!empty($settings)) {
        // Get current settings and merge with new ones.
        $current_settings = $field_storage->getSettings();
        $merged_settings = array_merge($current_settings, $settings);
        $field_storage->setSettings($merged_settings);
        $updated[] = 'settings';
      }

      // Save the field storage if anything was updated.
      if (!empty($updated)) {
        $field_storage->save();
        return ExecutableResult::success($this->t('Successfully updated field storage @field for entity type @type. Updated: @updated', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
          '@updated' => implode(', ', $updated),
        ]));
      }
      else {
        return ExecutableResult::success($this->t('No changes made to field storage @field', [
          '@field' => $field_name,
        ]));
      }

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error updating field storage: @message', [
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
    if ($name === 'settings' && !empty($values['entity_type_id']) && !empty($values['field_name'])) {
      // Load the field storage to get its type.
      $field_storage = FieldStorageConfig::loadByName($values['entity_type_id'], $values['field_name']);
      if ($field_storage) {
        $field_type = $field_storage->getType();
        $field_type_definitions = $this->fieldTypePluginManager->getDefinitions();
        if (isset($field_type_definitions[$field_type])) {
          $definition = EntityBundleFieldDefinitions::getSettingsInputDefinition($field_type_definitions[$field_type], 'storage');
        }
      }
    }
    return $definition;
  }

}
