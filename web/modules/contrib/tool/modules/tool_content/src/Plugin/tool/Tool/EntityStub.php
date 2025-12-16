<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
 * Plugin implementation of the entity create tool.
 */
#[Tool(
  id: 'entity_stub',
  label: new TranslatableMarkup('Create unsaved entity'),
  description: new TranslatableMarkup('Create a new unsaved entity with properties.  Best when used with additional tools to modify the entity and save it.'),
  operation: ToolOperation::Transform,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type to create."),
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
      description: new TranslatableMarkup('The bundle of the entity to create.'),
    ),
    'base_fields' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Entity Base Fields'),
      description: new TranslatableMarkup('An associative array of base field values to set on the new entity.'),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
    'base_fields' => ['entity_type_id'],
  ],
  output_definitions: [
    'created_entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Created Entity"),
      description: new TranslatableMarkup("The unsaved entity.")
    ),
  ],
)]
class EntityStub extends ToolBase implements InputDefinitionRefinerInterface {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle, 'base_fields' => $base_fields] = $values;

    try {
      // Create a new entity.
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $entity_values = [
        $entity_type->getKey('bundle') => $bundle,
      ];
      // Add field values from inputs.
      foreach ($base_fields as $field_name => $field_value) {
        $entity_values[$field_name] = $field_value;
      }
      $entity = $entity_storage->create($entity_values);
      return ExecutableResult::success($this->t('Successfully created unsaved @type entity', [
        '@type' => $entity_type_id,
      ]), ['created_entity' => $entity]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error creating @type entity: @message', [
        '@type' => $entity_type_id,
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle] = $values;
    /**
     * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
     */
    $access_handler = $this->entityTypeManager->getHandler($entity_type_id, 'access');
    $access_result = $access_handler->createAccess($bundle, $account, [], TRUE);
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareExecutableValue(string $name, InputDefinitionInterface $definition, mixed $value, $source): mixed {
    $value = parent::prepareExecutableValue($name, $definition, $value, $source);

    if ($name === 'base_fields' && $source === self::INPUT_SOURCE_INPUT && $this->hasInputValue('entity_type_id') && $definition instanceof MapInputDefinition) {
      $definitions = $definition->getPropertyDefinitions();
      $default_values = $definition->getDefaultValue();
      foreach ($value as $field_name => $field_value) {
        if (!isset($definitions[$field_name])) {
          // Skip unknown properties.
          unset($value[$field_name]);
          continue;
        }
        $base_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->getExecutableValue('entity_type_id'));
        if (isset($base_field_definitions[$field_name])) {
          $value[$field_name] = FieldSetValue::upcastFieldValue($base_field_definitions[$field_name], $field_value);
        }
      }
      // Apply default values for missing properties.
      foreach ($definitions as $field_name => $input_definition) {
        if (!isset($value[$field_name]) && isset($default_values[$field_name])) {
          $value[$field_name] = $default_values[$field_name];
        }
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface {
    switch ($name) {
      case 'bundle':
        $definition->addConstraint('EntityBundleExists', $values['entity_type_id']);
        break;

      case 'base_fields':
        $definition = self::getBaseFieldInputDefinition($values['entity_type_id']);
        break;
    }
    return $definition;
  }

  /**
   * Get the base field input definition for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\tool\TypedData\MapInputDefinition
   *   The input definition.
   */
  public static function getBaseFieldInputDefinition(string $entity_type_id): MapInputDefinition {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $base_field_definitions = $entityFieldManager->getBaseFieldDefinitions($entity_type_id);
    $settings_definitions = [];

    foreach ($base_field_definitions as $field_name => $field_definition) {
      // Skip computed fields, read-only fields, and auto-generated fields.
      if ($field_definition->isComputed() || $field_definition->isReadOnly() || !$field_definition->isDisplayConfigurable('form')) {
        continue;
      }
      $key = $field_name;
      $keys = $entity_type->getKeys();
      if (in_array($field_name, $keys, TRUE)) {
        // Always use the entity key name for id, uuid, langcode, etc.
        $key = array_search($field_name, $keys, TRUE);
      }
      if ($key === 'created' || $key === 'changed') {
        // Skip created and changed fields.
        continue;
      }
      $base_field_input_definition = FieldSetValue::getFieldInputDefinition($field_definition);
      $property_definitions = $base_field_input_definition->getPropertyDefinitions();
      if ($key === 'uid') {
        $property_definitions['target_id']->setDefaultValue(\Drupal::currentUser()->id());
      }
      elseif ($key === 'langcode') {
        $property_definitions['value']->setDefaultValue(\Drupal::languageManager()->getDefaultLanguage()->getId());
      }
      elseif ($key === 'status') {
        $property_definitions['value']->setDefaultValue(FALSE);
      }

      if ($value = $field_definition->getDefaultValueLiteral()) {
        foreach ($value[0] as $property_name => $property_value) {
          if (isset($property_definitions[$property_name])) {
            $property_definitions[$property_name]->setDefaultValue($value[0][$property_name]);
          }
        }
      }
      $settings_definitions[$field_name] = $base_field_input_definition;
    }
    return new MapInputDefinition(
      label: new TranslatableMarkup('Entity Base Fields'),
      description: new TranslatableMarkup('An associative array of base field values to set on the new entity.'),
      required: FALSE,
      property_definitions: $settings_definitions,
    );
  }

}
