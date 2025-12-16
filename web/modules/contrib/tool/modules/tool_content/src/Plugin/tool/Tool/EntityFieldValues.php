<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

/**
 * Plugin implementation of the entity field values tool.
 */
#[Tool(
  id: 'entity_field_values',
  label: new TranslatableMarkup('Get field values'),
  description: new TranslatableMarkup('Get field values from an entity with optional field filtering.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity' => new InputDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity to get field values from.")
    ),
    'fields' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Fields"),
      description: new TranslatableMarkup("Array of field names to retrieve. Leave empty to get all fields."),
      required: FALSE,
      multiple: TRUE
    ),
  ],
  output_definitions: [
    'field_values' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Field Values"),
      description: new TranslatableMarkup("Array of field values from the entity.")
    ),
  ],
)]
class EntityFieldValues extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity' => $entity, 'fields' => $fields] = $values + ['fields' => []];

    if (!$entity instanceof FieldableEntityInterface) {
      return ExecutableResult::failure($this->t('Invalid entity provided.'));
    }

    try {
      // Get all field definitions for this entity.
      $field_definitions = $entity->getFieldDefinitions();

      // If specific fields were requested, filter to those.
      if (!empty($fields)) {
        $fields_to_process = array_intersect_key($field_definitions, array_flip($fields));
      }
      else {
        $fields_to_process = $field_definitions;
      }

      $field_values = [];
      $access_denied_fields = [];

      foreach ($fields_to_process as $field_name => $field_definition) {
        // @todo deal with potential mismatch if account is passed to checkAccess
        // Check field-level access.
        if (!$entity->get($field_name)->access('view')) {
          $access_denied_fields[] = $field_name;
          continue;
        }

        // Get the field value.
        $field_items = $entity->get($field_name);

        if ($field_items->isEmpty()) {
          $field_values[$field_name] = NULL;
        }
        else {
          // Handle single vs multiple values.
          if ($field_definition->getFieldStorageDefinition()->getCardinality() === 1) {
            $field_values[$field_name] = $this->getFieldItemValue($field_items->first());
          }
          else {
            $field_values[$field_name] = [];
            foreach ($field_items as $field_item) {
              $field_values[$field_name][] = $this->getFieldItemValue($field_item);
            }
          }
        }
      }

      $message = $this->t('Retrieved @count field values from @type entity @id.', [
        '@count' => count($field_values),
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);

      if (!empty($access_denied_fields)) {
        $message .= $this->t('(@denied fields denied access: @fields)', [
          '@denied' => count($access_denied_fields),
          '@fields' => implode(', ', $access_denied_fields),
        ]);
      }

      return ExecutableResult::success($message, ['field_values' => $field_values]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving field values: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Extract the appropriate value from a field item.
   */
  private function getFieldItemValue($field_item) {
    if (!$field_item) {
      return NULL;
    }

    // Get the main properties of the field item.
    $properties = $field_item->getProperties();

    // For simple fields, try to get the 'value' property first.
    if (isset($properties['value'])) {
      return $field_item->value;
    }

    // For entity reference fields, get target_id.
    if (isset($properties['target_id'])) {
      return [
        'target_id' => $field_item->target_id,
        'entity' => $field_item->entity ? [
          'id' => $field_item->entity->id(),
          'label' => $field_item->entity->label(),
          'type' => $field_item->entity->getEntityTypeId(),
        ] : NULL,
      ];
    }

    // For complex fields, return all non-computed properties.
    $values = [];
    foreach ($properties as $property_name => $property) {
      if (!$property->getDataDefinition()->isComputed()) {
        $values[$property_name] = $property->getValue();
      }
    }

    return $values ?: $field_item->getValue();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access_result = $values['entity']->access('view', $account, TRUE);
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
