<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity list tool.
 */
#[Tool(
  id: 'entity_list',
  label: new TranslatableMarkup('List entities'),
  description: new TranslatableMarkup('List content entities with filtering, sorting, and field selection.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type to filter entities by."),
      constraints: [
        'PluginExists' => [
          'manager' => 'entity_type.manager',
          'interface' => ContentEntityInterface::class,
        ],
      ],
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The name of the bundle type to filter entities by."),
      required: FALSE
    ),
    'amount' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Amount"),
      description: new TranslatableMarkup("The amount of entities to fetch. 0 means all."),
      required: FALSE,
      default_value: 10
    ),
    'offset' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Offset"),
      description: new TranslatableMarkup("The offset of entities to fetch."),
      required: FALSE,
      default_value: 0
    ),
    'fields' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Fields"),
      description: new TranslatableMarkup("The fields to include in the output. Leave empty to include title, id, and bundle."),
      required: FALSE,
      multiple: TRUE,
    ),
    'sort_field' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Sort Field"),
      description: new TranslatableMarkup("The field to sort by."),
      required: FALSE
    ),
    'sort_order' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Sort Order"),
      description: new TranslatableMarkup("The sort order (ASC or DESC)."),
      required: FALSE,
      default_value: 'DESC',
      constraints: [
        'Choice' => [
          'choices' => ['ASC', 'DESC'],
        ],
      ],
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
    'fields' => ['entity_type_id', 'bundle'],
    'sort_field' => ['entity_type_id', 'bundle'],
  ],
  output_definitions: [
    'results' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Results"),
      description: new TranslatableMarkup("Array of entity data matching the criteria.")
    ),
  ],
)]
class EntityList extends ToolBase implements InputDefinitionRefinerInterface {

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
    [
      'entity_type_id' => $entity_type_id,
      'bundle' => $bundle,
      'amount' => $amount,
      'offset' => $offset,
      'fields' => $fields,
      'sort_field' => $sort_field,
      'sort_order' => $sort_order,
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
      $bundle_key = $entity_type_info->getKey('bundle');

      // Build the query.
      $query = $storage->getQuery();
      $query->accessCheck();

      // Apply sorting.
      if ($sort_field && $sort_order) {
        $query->sort($sort_field, $sort_order);
      }

      // Filter by bundle if specified.
      if ($bundle && $bundle_key) {
        $query->condition($bundle_key, $bundle);
      }
      $total_count_query = clone $query;

      // Apply range/limit.
      if ($amount > 0) {
        $query->range($offset, $amount);
      }

      // Execute query.
      $entity_ids = $query->execute();

      if (empty($entity_ids)) {
        return ExecutableResult::success($this->t('No entities found matching the criteria.'), [
          'entities' => [],
        ]);
      }

      /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
      $entities = $storage->loadMultiple($entity_ids);
      $entity_list = [];
      $access_denied_count = 0;

      foreach ($entities as $entity) {
        // Double-check access (query access check may not be sufficient)
        if (!$entity->access('view')) {
          $access_denied_count++;
          continue;
        }

        // Ensure it's a content entity.
        if (!$entity instanceof ContentEntityInterface) {
          continue;
        }

        $entity_data = [];

        if (!empty($fields)) {
          // Include only specified fields.
          foreach ($fields as $field) {
            if ($entity->hasField($field)) {
              // Check field-level access.
              if ($entity->get($field)->access('view')) {
                $entity_data[$field] = $this->getFieldValue($entity->get($field));
              }
            }
          }
        }

        // Add entity metadata.
        $entity_data['_metadata'] = [
          'id' => $entity->id(),
          'type' => $entity->getEntityTypeId(),
          'bundle' => $entity->bundle(),
          'label' => $entity->label(),
          'uuid' => $entity->uuid(),
        ];

        $entity_list[] = $entity_data;
      }

      $total_count = $total_count_query
        ->count()
        ->execute();

      $count = count($entity_list);
      if (($offset + $count) < $total_count) {
        $message = $this->t('Returned @count %entity_type_label(%entity_type_id) entities out of a total @total. This means that if you want to list the rest you should offset @offset and set amount to @remaining.', [
          '@count' => $count,
          '%entity_type_label' => $entity_type_info->getLabel(),
          '%entity_type_id' => $entity_type_id,
          '@total' => $total_count,
          '@offset' => $offset + $count,
          '@remaining' => $total_count - ($offset + $count),
        ]);
      }
      else {
        $message = $this->t('Returned @count %entity_type_label(%entity_type_id) entities out of a total @total', [
          '@count' => $count,
          '%entity_type_label' => $entity_type_info->getLabel(),
          '%entity_type_id' => $entity_type_id,
          '@total' => $total_count,
        ]);
      }

      if ($access_denied_count > 0) {
        $message = $this->t('@message (@denied entities denied access)', [
          '@message' => $message,
          '@denied' => $access_denied_count,
        ]);
      }

      return ExecutableResult::success($message, ['results' => $entity_list]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error listing entities: @message', [
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

  /**
   * {@inheritdoc}
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface {
    switch ($name) {
      case 'bundle':
        $definition->addConstraint('EntityBundleExists', $values['entity_type_id']);
        break;

      case 'fields':
      case 'sort_field':
        ['entity_type_id' => $entity_type_id, 'bundle' => $bundle] = $values;
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        $definition->addConstraint('Choice', [
          'choices' => array_keys($field_definitions),
        // 'multiple' => ($name === 'fields'),
        ]);

        break;
    }
    return $definition;
  }

}
