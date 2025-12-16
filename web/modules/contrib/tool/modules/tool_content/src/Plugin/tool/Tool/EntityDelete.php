<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity delete tool.
 */
#[Tool(
  id: 'entity_delete',
  label: new TranslatableMarkup('Delete entity'),
  description: new TranslatableMarkup('Delete an entity by type and ID.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'entity' => new InputDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity to save.")
    ),
  ],
  output_definitions: [
    'deleted_entity_info' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Deleted Entity Info"),
      description: new TranslatableMarkup("Information about the deleted entity.")
    ),
  ],
)]
class EntityDelete extends ToolBase {

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
    $entity = $values['entity'];
    // Store entity info before deletion.
    $entity_info = [
      'id' => $entity->id(),
      'type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
      'uuid' => $entity->uuid(),
    ];
    try {
      // Delete the entity.
      $entity->delete();
      return ExecutableResult::success($this->t('Successfully deleted @type entity "@label" (ID: @id)', [
        '@type' => $entity_info['type'],
        '@label' => $entity_info['label'],
        '@id' => $entity_info['id'],
      ]), ['deleted_entity_info' => $entity_info]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error deleting @type entity with ID @id: @message', [
        '@type' => $entity_info['type'],
        '@id' => $entity_info['id'],
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access_result = $values['entity']->access('delete', $account, TRUE);
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
