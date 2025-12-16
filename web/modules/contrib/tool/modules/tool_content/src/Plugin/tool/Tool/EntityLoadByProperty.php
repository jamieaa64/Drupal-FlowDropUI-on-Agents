<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity load by property tool.
 */
#[Tool(
  id: 'entity_load_by_property',
  label: new TranslatableMarkup('Load entity by property'),
  description: new TranslatableMarkup('Load entities by matching a property/field value.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The entity type machine name (e.g., node, user, taxonomy_term)."),
    ),
    'property_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Property Name"),
      description: new TranslatableMarkup("The field or property name to search by."),
    ),
    'property_value' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Property Value"),
      description: new TranslatableMarkup("The value to match against the property."),
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("Optional bundle to filter by."),
      required: FALSE
    ),
    'limit' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Limit"),
      description: new TranslatableMarkup("Maximum number of entities to return. 0 means no limit."),
      required: FALSE,
      default_value: 10
    ),
  ],
  output_definitions: [
    'entities' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entities"),
      multiple: TRUE,
      description: new TranslatableMarkup("Array of entities matching the property value."),
    ),
  ],
)]
class EntityLoadByProperty extends ToolBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    [
      'entity_type_id' => $entity_type_id,
      'property_name' => $property_name,
      'property_value' => $property_value,
      'bundle' => $bundle,
      'limit' => $limit,
    ] = $values;

    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type_info = $this->entityTypeManager->getDefinition($entity_type_id);

      // Build the query.
      $query = $storage->getQuery();
      $query->accessCheck(TRUE);

      // Add property condition.
      $query->condition($property_name, $property_value);

      // Filter by bundle if specified.
      if ($bundle && $entity_type_info->getKey('bundle')) {
        $query->condition($entity_type_info->getKey('bundle'), $bundle);
      }

      // Apply limit.
      if ($limit > 0) {
        $query->range(0, $limit);
      }

      // Execute query.
      $entity_ids = $query->execute();

      if (empty($entity_ids)) {
        return ExecutableResult::success($this->t('No entities found with @property = "@value".', [
          '@property' => $property_name,
          '@value' => $property_value,
        ]), [
          'entities' => [],
        ]);
      }

      /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
      $entities = $storage->loadMultiple($entity_ids);
      $entity_list = [];
      $access_denied_count = 0;

      foreach ($entities as $entity) {
        // Check access.
        if (!$entity->access('view')) {
          $access_denied_count++;
          continue;
        }

        // Ensure it's a content entity.
        if (!$entity instanceof ContentEntityInterface) {
          continue;
        }

        $entity_data = [
          'id' => $entity->id(),
          'type' => $entity->getEntityTypeId(),
          'bundle' => $entity->bundle(),
          'label' => $entity->label(),
          'uuid' => $entity->uuid(),
          'matched_property' => $property_name,
          'matched_value' => $property_value,
        ];

        // Include the property that was matched.
        if ($entity->hasField($property_name)) {
          $field_access = $entity->get($property_name)->access('view');
          if ($field_access) {
            $entity_data[$property_name] = $this->getFieldValue($entity->get($property_name));
          }
        }

        $entity_list[] = $entity_data;
      }

      $message = $this->t('Found @count entities with @property = "@value"', [
        '@count' => count($entity_list),
        '@property' => $property_name,
        '@value' => $property_value,
      ]);

      if ($access_denied_count > 0) {
        $message .= $this->t('(@denied entities denied access)', [
          '@denied' => $access_denied_count,
        ]);
      }

      return ExecutableResult::success($message, ['entities' => $entity_list]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error loading entities by property: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Extract field value in a consistent format.
   */
  private function getFieldValue($field_items) {
    if ($field_items->isEmpty()) {
      return NULL;
    }

    $field_definition = $field_items->getFieldDefinition();
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();

    if ($cardinality === 1) {
      return $this->getFieldItemValue($field_items->first());
    }

    $values = [];
    foreach ($field_items as $field_item) {
      $values[] = $this->getFieldItemValue($field_item);
    }
    return $values;
  }

  /**
   * Extract the appropriate value from a field item.
   */
  private function getFieldItemValue($field_item) {
    if (!$field_item) {
      return NULL;
    }

    $properties = $field_item->getProperties();

    // For simple fields, try to get the 'value' property first.
    if (isset($properties['value'])) {
      return $field_item->value;
    }

    // For entity reference fields, get target_id and basic entity info.
    if (isset($properties['target_id'])) {
      $data = ['target_id' => $field_item->target_id];
      if ($field_item->entity) {
        $data['entity'] = [
          'id' => $field_item->entity->id(),
          'label' => $field_item->entity->label(),
          'type' => $field_item->entity->getEntityTypeId(),
        ];
      }
      return $data;
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
    // Basic permission check - more specific access is done per entity.
    // Allow if user has general access to view content.
    $access_result = AccessResult::allowedIfHasPermission($account, 'access content');
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
