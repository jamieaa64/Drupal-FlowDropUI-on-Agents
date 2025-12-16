<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

/**
 * Plugin implementation of the entity save tool.
 */
#[Tool(
  id: 'entity_save',
  label: new TranslatableMarkup('Save entity'),
  description: new TranslatableMarkup('Save an entity.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity' => new InputDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity to save.")
    ),
  ],
  output_definitions: [
    'saved_entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Saved Entity"),
      description: new TranslatableMarkup("The entity that was saved.")
    ),
  ],
)]
class EntitySave extends ToolBase {

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
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity] = $values;
    try {
      $op = $entity->isNew() ? $this->t('created') : $this->t('updated');
      $entity->save();
      return ExecutableResult::success($this->t('Successfully @op @type entity with ID @id', [
        '@op' => $op,
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]), ['saved_entity' => $entity]);
    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error saving @type entity: @message', [
        '@type' => $entity->getEntityTypeId(),
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    ['entity' => $entity] = $values;
    if ($entity->isNew()) {
      /**
       * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
       */
      $access_handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'access');
      $access_result = $access_handler->createAccess($entity->bundle(), $account, [], TRUE);
    }
    else {
      $access_result = $entity->access('update', $account, TRUE);
    }
    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
