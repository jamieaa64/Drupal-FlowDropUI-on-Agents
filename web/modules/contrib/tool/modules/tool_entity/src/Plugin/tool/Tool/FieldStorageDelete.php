<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the field storage delete tool.
 */
#[Tool(
  id: 'field_storage_delete',
  label: new TranslatableMarkup('Delete field from entity type'),
  description: new TranslatableMarkup('Deletes an existing field storage configuration(and all instances) from an entity type.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
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
      description: new TranslatableMarkup('The machine name of the field to delete (e.g., field_tags).'),
    ),
  ],
)]
class FieldStorageDelete extends ToolBase implements ContainerFactoryPluginInterface {

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
    ['entity_type_id' => $entity_type_id, 'field_name' => $field_name] = $values;

    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      // Load the field storage.
      $field_storage = FieldStorageConfig::loadByName($entity_type_id, $field_name);

      if (!$field_storage) {
        return ExecutableResult::failure($this->t('Field storage @field does not exist for entity type @type', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
        ]));
      }

      // Delete the field storage (this will also delete all field instances).
      $field_storage->delete();

      return ExecutableResult::success($this->t('Successfully deleted field storage @field from entity type @type', [
        '@field' => $field_name,
        '@type' => $entity_type_id,
      ]), ['result' => TRUE]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error deleting field storage @field from entity type @type: @message', [
        '@field' => $field_name,
        '@type' => $entity_type_id,
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

}
