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
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity load by ID tool.
 */
#[Tool(
  id: 'entity_load_by_id',
  label: new TranslatableMarkup('Load entity by ID'),
  description: new TranslatableMarkup('Load a content entity by its type and ID.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The entity type machine name (e.g., node, user, taxonomy_term)."),
      constraints: [
        "PluginExists" => [
          "manager" => "entity_type.manager",
          "interface" => ContentEntityInterface::class,
        ],
      ],
    ),
    'entity_id' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Entity ID"),
      description: new TranslatableMarkup("The numeric ID of the entity to load.")
    ),
  ],
  output_definitions: [
    'loaded_entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity that was loaded.")
    ),
  ],
)]
class EntityLoadById extends ToolBase {

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
    ['entity_type_id' => $entity_type_id, 'entity_id' => $entity_id] = $values;
    try {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);

      if ($entity) {
        return ExecutableResult::success($this->t('Successfully loaded @type entity with ID @id', [
          '@type' => $entity_type_id,
          '@id' => $entity_id,
        ]), ['loaded_entity' => $entity]);
      }
      else {
        return ExecutableResult::failure($this->t('No @type entity found with ID @id', [
          '@type' => $entity_type_id,
          '@id' => $entity_id,
        ]));
      }
    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error loading @type entity with ID @id: @message', [
        '@type' => $entity_type_id,
        '@id' => $entity_id,
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if the user has access to view the entity.
    $entity_type_id = $values['entity_type_id'] ?? NULL;
    $entity_id = $values['entity_id'] ?? NULL;

    if ($entity_type_id && $entity_id) {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
      if ($entity && $entity->access('view', $account, TRUE)) {
        return $return_as_object ? AccessResult::allowed() : AccessResult::allowed()->isAllowed();
      }
    }
    return $return_as_object ? AccessResult::forbidden() : AccessResult::forbidden()->isForbidden();
  }

}
