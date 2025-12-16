<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;
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
 * Plugin implementation of the field update tool.
 */
#[Tool(
  id: 'field_update',
  label: new TranslatableMarkup('Update field on bundle'),
  description: new TranslatableMarkup('Updates a field instance on a bundle.'),
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
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The bundle containing the field (e.g., article, page).'),
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('The machine name of the field to update (e.g., field_tags).'),
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Label'),
      description: new TranslatableMarkup('The human-readable label for the field.'),
      required: FALSE,
    ),
    'description' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Description'),
      description: new TranslatableMarkup('Help text to display for the field.'),
      required: FALSE,
    ),
    'required' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Required'),
      description: new TranslatableMarkup('Whether the field is required  (boolean).'),
      required: FALSE,
    ),
    'settings' => new MapInputDefinition(
      label: new TranslatableMarkup('Field Settings'),
      description: new TranslatableMarkup('Field instance settings to update (not storage settings).'),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
    'field_name' => ['entity_type_id', 'bundle'],
    'settings' => ['entity_type_id', 'field_name'],
  ],
)]
class FieldUpdate extends ToolBase implements InputDefinitionRefinerInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

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
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    [
      'entity_type_id' => $entity_type_id,
      'bundle' => $bundle,
      'field_name' => $field_name,
      'label' => $label,
      'description' => $description,
      'required' => $required,
      'settings' => $settings,
    ] = $values;

    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      // Load existing field.
      /** @var \Drupal\Core\Field\FieldConfigInterface $field */
      $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
      if (!$field) {
        return ExecutableResult::failure($this->t('Field @field does not exist on bundle @bundle of entity type @type', [
          '@field' => $field_name,
          '@bundle' => $bundle,
          '@type' => $entity_type_id,
        ]));
      }

      // Track what we're updating.
      $updated = [];

      // Update label if provided.
      if ($label !== NULL) {
        $field->setLabel($label);
        $updated[] = 'label';
      }

      // Update description if provided.
      if ($description !== NULL) {
        $field->setDescription($description);
        $updated[] = 'description';
      }

      // Update required if provided.
      if ($required !== NULL) {
        $field->setRequired($required);
        $updated[] = 'required';
      }

      // Update field settings if provided.
      if (!empty($settings)) {
        $current_settings = $field->getSettings();
        // Move all null values from $settings.
        $settings = $this->removeNullValues($settings);
        $merged_settings = array_merge($current_settings, $settings);
        $field->setSettings($merged_settings);
        $updated[] = 'settings';
      }

      // Save the field if anything was updated.
      if (!empty($updated)) {
        $field->save();
        return ExecutableResult::success($this->t('Successfully updated field @field on bundle @bundle. Updated: @updated', [
          '@field' => $field_name,
          '@bundle' => $bundle,
          '@updated' => implode(', ', $updated),
        ]));
      }
      else {
        return ExecutableResult::success($this->t('No changes made to field @field', [
          '@field' => $field_name,
        ]));
      }

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error updating field: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Recursively removes null values from an array or object.
   *
   * @param mixed $data
   *   The data to process (array or object).
   *
   * @return mixed
   *   The processed data with null values removed.
   */
  protected function removeNullValues($data) {
    if (is_array($data)) {
      // Recursively process each element.
      foreach ($data as $key => $value) {
        $data[$key] = $this->removeNullValues($value);

        // Remove if the result is null or an empty array.
        if ($data[$key] === NULL || (is_array($data[$key]) && empty($data[$key]))) {
          unset($data[$key]);
        }
      }
    }
    elseif (is_object($data)) {
      // For objects, iterate through properties.
      foreach ($data as $key => $value) {
        $data->$key = $this->removeNullValues($value);

        if ($data->$key === NULL || (is_array($data->$key) && empty($data->$key))) {
          unset($data->$key);
        }
      }
    }

    return $data;
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
    switch ($name) {
      case 'bundle':
        $definition->addConstraint('EntityBundleExists', $values['entity_type_id']);
        break;

      case 'field_name':
        $definition->addConstraint('FieldExists', [
          'entityTypeId' => $values['entity_type_id'],
          'bundle' => $values['bundle'],
        ]);
        break;

      case 'settings':
        // Load the field to get its type.
        $field = FieldConfig::loadByName($values['entity_type_id'], $values['bundle'] ?? NULL, $values['field_name']);
        // If field doesn't exist, try loading the storage to get the type.
        if (!$field) {
          $field_storage = FieldStorageConfig::loadByName($values['entity_type_id'], $values['field_name']);
          if ($field_storage) {
            $field_type = $field_storage->getType();
          }
        }
        else {
          $field_type = $field->getType();
        }

        if (isset($field_type)) {
          $field_type_definitions = $this->fieldTypePluginManager->getDefinitions();
          if (isset($field_type_definitions[$field_type])) {
            // Get field settings schema from config.
            $definition = EntityBundleFieldDefinitions::getSettingsInputDefinition($field_type_definitions[$field_type], 'field');
          }
        }
        break;
    }

    return $definition;
  }

}
