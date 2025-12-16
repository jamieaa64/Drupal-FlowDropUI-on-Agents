<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
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
 * Plugin implementation of the field add tool.
 */
#[Tool(
  id: 'field_add',
  label: new TranslatableMarkup('Add field to bundle'),
  description: new TranslatableMarkup('Adds a field instance to a bundle using an existing field storage.'),
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
      description: new TranslatableMarkup('The bundle to add the field to (e.g., article, page).'),
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('The machine name of an existing field storage (e.g., field_tags).'),
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Label'),
      description: new TranslatableMarkup('The human-readable label for the field on this bundle.'),
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
      description: new TranslatableMarkup('Whether the field is required (boolean).'),
      required: FALSE,
      default_value: FALSE,
    ),
    'settings' => new MapInputDefinition(
      label: new TranslatableMarkup('Field Settings'),
      description: new TranslatableMarkup('Field instance settings (not storage settings).'),
      required: FALSE,
      default_value: [],
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
    'settings' => ['entity_type_id', 'field_name'],
  ],
)]
class FieldAdd extends ToolBase implements InputDefinitionRefinerInterface {

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
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
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

      // Check if field storage exists.
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      if (!$field_storage) {
        return ExecutableResult::failure($this->t('Field storage @field does not exist for entity type @type. You must create the field storage first.', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
        ]));
      }

      // Check if field already exists on this bundle.
      $existing_field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
      if ($existing_field) {
        return ExecutableResult::failure($this->t('Field @field already exists on bundle @bundle', [
          '@field' => $field_name,
          '@bundle' => $bundle,
        ]));
      }

      // Prepare field values.
      $field_values = [
        'field_storage' => $field_storage,
        'bundle' => $bundle,
        'label' => $label,
        'required' => $required ?? FALSE,
      ];

      // Add description if provided.
      if (!empty($description)) {
        $field_values['description'] = $description;
      }

      // Add field-specific settings if provided.
      if (!empty($settings)) {
        $field_values['settings'] = $settings;
      }

      // Create the field instance.
      $field = FieldConfig::create($field_values);
      $field->save();

      $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default')
        ->setComponent($field_name, [])
        ->save();
      $this->entityDisplayRepository->getViewDisplay($entity_type_id, $bundle, 'default')
        ->setComponent($field_name, [])
        ->save();

      return ExecutableResult::success($this->t('Successfully added field @field to bundle @bundle of entity type @type', [
        '@field' => $field_name,
        '@bundle' => $bundle,
        '@type' => $entity_type_id,
      ]));

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error adding field: @message', [
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
    switch ($name) {
      case 'bundle':
        $definition->addConstraint('EntityBundleExists', $values['entity_type_id']);
        break;

      case 'settings':
        // Load the field storage to get its type.
        $field_storage = FieldStorageConfig::loadByName($values['entity_type_id'], $values['field_name']);
        if ($field_storage) {
          $field_type = $field_storage->getType();
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
