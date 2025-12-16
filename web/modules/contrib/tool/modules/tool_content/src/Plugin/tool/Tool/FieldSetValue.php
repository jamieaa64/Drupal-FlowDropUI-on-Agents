<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Drupal\tool\TypedData\MapInputDefinition;

/**
 * Plugin implementation of the field set value tool.
 */
#[Tool(
  id: 'field_set_value',
  label: new TranslatableMarkup('Set field value'),
  description: new TranslatableMarkup('Set a field value on an entity.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity' => new InputDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity to modify.")
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The machine name of the field to set.")
    ),
    'value' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Value"),
      description: new TranslatableMarkup("The value(s) to set for the field. Can be a single value or multiple values for multi-value fields. Should follow accepted field definition schema."),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'field_name' => ['entity'],
    'value' => ['entity', 'field_name'],
  ],
  output_definitions: [
    'updated_entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Updated Entity"),
      description: new TranslatableMarkup("The entity with the updated field value.")
    ),
  ],
)]
final class FieldSetValue extends ToolBase implements InputDefinitionRefinerInterface {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity' => $entity, 'field_name' => $field_name, 'value' => $value] = $values;

    try {
      // Get the field definition to understand the field structure.
      // @todo move this to refined input definition if possible.
      $field_definition = $entity->getFieldDefinition($field_name);
      $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
      // Validate cardinality.
      if ($cardinality !== -1 && is_array($value) && isset($value[0]) && count($value) > $cardinality) {
        return ExecutableResult::failure($this->t('Field "@field" accepts maximum @max values, @count provided.', [
          '@field' => $field_name,
          '@max' => $cardinality,
          '@count' => count($value),
        ]));
      }

      // Set the field value.
      $entity->set($field_name, $value);

      // Validate the field value.
      $violations = $entity->get($field_name)->validate();
      if ($violations->count() > 0) {
        $violation_messages = [];
        foreach ($violations as $violation) {
          $violation_messages[] = $violation->getMessage();
        }
        return ExecutableResult::failure($this->t('Field validation failed: @violations', [
          '@violations' => implode(', ', $violation_messages),
        ]));
      }

      return ExecutableResult::success($this->t('Successfully set field "@field" on @type entity.', [
        '@field' => $field_name,
        '@type' => $entity->getEntityTypeId(),
      ]), ['updated_entity' => $entity]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error setting field "@field": @message', [
        '@field' => $field_name,
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    ['entity' => $entity, 'field_name' => $field_name] = $values;

    if (!$entity->get($field_name)->access('edit', $account)) {
      return $return_as_object ? AccessResult::forbidden() : AccessResult::forbidden()->isForbidden();
    }
    return $return_as_object ? AccessResult::allowed() : AccessResult::allowed()->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareExecutableValue(string $name, InputDefinitionInterface $definition, mixed $value, $source): mixed {
    $value = parent::prepareExecutableValue($name, $definition, $value, $source);
    if ($name === 'value' && $source === self::INPUT_SOURCE_INPUT && $this->hasInputValue('entity') && $this->hasInputValue('field_name')) {
      try {
        $entity = $this->getExecutableValue('entity');
        $field_name = $this->getExecutableValue('field_name');
        $value = self::upcastFieldValue($entity->getFieldDefinition($field_name), $value);
      }
      catch (\Exception $e) {
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setInputValue(string $name, mixed $value): static {
    if ($name === 'value') {
      // Attempt to upcast non-conforming fields to avoid early violations.
      $value = $this->prepareExecutableValue($name, $this->getInputDefinition($name), $value, self::INPUT_SOURCE_INPUT);
    }
    return parent::setInputValue($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface {
    switch ($name) {
      case 'field_name':
        $definition->addConstraint('FieldExists',
          [
            'entityTypeId' => $values['entity']->getEntityTypeId(),
            'bundle' => $values['entity']->bundle(),
          ]
        );
        break;

      case 'value':
        ['entity' => $entity, 'field_name' => $field_name] = $values;
        $field_definition = $entity->getFieldDefinition($field_name);
        $definition = self::getFieldInputDefinition($field_definition);
        $definition->setDefaultValue($field_definition->getDefaultValue($entity));
        break;
    }
    return $definition;
  }

  /**
   * Upcast a field value according to its field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition to use for upcasting.
   * @param mixed $value
   *   The value to upcast.
   *
   * @return mixed
   *   The upcasted value.
   */
  public static function upcastFieldValue(FieldDefinitionInterface $field_definition, mixed $value): mixed {
    $typed_data_manager = \Drupal::service('typed_data_manager');

    // Create a detached item (not bound to an entity).
    /** @var \Drupal\Core\TypedData\ComplexDataInterface $field_item */
    $field_item = $typed_data_manager->create($field_definition->getItemDefinition());
    try {
      $field_item->setValue($value);
      $value = $field_item->getValue();
    }
    catch (\Exception $e) {
      // Ignore exceptions during upcasting.
    }
    return $value;
  }

  /**
   * Get an input definition for a field based on its field definition.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition to create the input definition for.
   *
   * @return \Drupal\tool\TypedData\MapInputDefinition
   *   The input definition for the field.
   */
  public static function getFieldInputDefinition(FieldDefinitionInterface $field_definition): MapInputDefinition {
    $property_definitions = [];
    $typed_data_manager = \Drupal::service('typed_data_manager');

    /** @var \Drupal\Core\TypedData\ComplexDataDefinitionInterface $item_definition */
    $item_definition = $field_definition->getItemDefinition();

    // Create a detached item (not bound to an entity).
    /** @var \Drupal\Core\TypedData\ComplexDataInterface $field_item */
    $field_item = $typed_data_manager->create($item_definition);

    $item_properties = $field_item->getProperties();
    foreach ($item_definition->getPropertyDefinitions() as $property_name => $property_definition) {
      if ($property_definition->isComputed() || !isset($item_properties[$property_name])) {
        // Skip computed properties.
        continue;
      }
      $description = $property_definition->getDescription() ?? new TranslatableMarkup('The @property property of the field "@field".', [
        '@property' => $property_name,
        '@field' => $field_definition->getName(),
      ]);
      $property_definitions[$property_name] = new InputDefinition(
        data_type: $property_definition->getDataType(),
        label: $property_definition->getLabel(),
        description: $description,
        required: $property_definition->isRequired(),
        constraints: $property_definition->getConstraints()
      );
    }

    return new MapInputDefinition(
      label: new TranslatableMarkup('Value (@field_label)', ['@field_label' => $field_definition->getLabel()]),
      description: new TranslatableMarkup('The value(s) to set for the field "@field".', ['@field' => $field_definition->getName()]),
      required: $field_definition->isRequired(),
      multiple: $field_definition->getFieldStorageDefinition()->getCardinality() !== 1,
      property_definitions: $property_definitions
    );
  }

}
