<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
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
 * Plugin implementation of the entity revision add tool.
 */
#[Tool(
  id: 'entity_revision_add',
  label: new TranslatableMarkup('Add new entity revision'),
  description: new TranslatableMarkup('Adds a new unsaved revision of an existing entity.  This should always be used when editing existing content entities prior to saving.'),
  operation: ToolOperation::Transform,
  input_definitions: [
    'entity' => new InputDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity to create a new revision for."),
    ),
    'revision_log' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Revision Log Message"),
      description: new TranslatableMarkup("Description of the changes made in this revision."),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'revised_entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Revised Entity"),
      description: new TranslatableMarkup("The entity with the new revision created.")
    ),
  ],
)]
class EntityRevisionAdd extends ToolBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected Time $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity' => $entity] = $values;
    $revision_log = $values['revision_log'] ?? '';

    // Validate that the entity is a content entity.
    if (!($entity instanceof ContentEntityInterface)) {
      return ExecutableResult::failure($this->t('The provided entity must be a content entity.'));
    }

    // Check that the entity is not new (already saved).
    if ($entity->isNew()) {
      return ExecutableResult::failure($this->t('Cannot create a revision of a new entity. Save the entity first.'));
    }

    try {
      // Create a new revision.
      $entity->setNewRevision();

      // Set revision metadata if the entity supports it.
      if (method_exists($entity, 'setRevisionCreationTime')) {
        $entity->setRevisionCreationTime($this->time->getRequestTime());
      }

      if (method_exists($entity, 'setRevisionUserId')) {
        $entity->setRevisionUserId($this->currentUser->id());
      }

      if (method_exists($entity, 'setRevisionLogMessage') && !empty($revision_log)) {
        $entity->setRevisionLogMessage($revision_log);
      }

      $message = $this->t('Successfully created new unsaved revision for @type entity @id.', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);

      if (!empty($revision_log)) {
        $message = $this->t('Successfully created new unsaved revision for @type entity @id with log: "@log".', [
          '@type' => $entity->getEntityTypeId(),
          '@id' => $entity->id(),
          '@log' => $revision_log,
        ]);
      }

      return ExecutableResult::success($message, ['revised_entity' => $entity]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error creating revision for @type entity @id: @message', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $entity = $values['entity'] ?? NULL;
    if (!($entity instanceof ContentEntityInterface)) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    // Check if user can update the entity.
    $update_access = $entity->access('update', $account, TRUE);
    if (!$update_access->isAllowed()) {
      return $return_as_object ? $update_access : FALSE;
    }

    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
