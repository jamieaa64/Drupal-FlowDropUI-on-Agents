<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the field set value tool.
 */
#[Tool(
  id: 'entity_bundle_delete',
  label: new TranslatableMarkup('Delete bundle'),
  description: new TranslatableMarkup('Delete the entity bundle by entity type ID and bundle machine name.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type."),
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle machine name (e.g., article, page).")
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
  ],
)]
final class EntityBundleDelete extends ToolBase implements InputDefinitionRefinerInterface {


  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle] = $values;
    try {
      $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      // Check that the entity type exists.
      // We cannot currently use constraints here because there is no constraint
      // specific to checking for entity types that allow bundles.
      if (!$entity_type_definition) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }
      $bundle_entity_type = $entity_type_definition->getBundleEntityType();
      if (!$bundle_entity_type) {
        return ExecutableResult::failure($this->t('Entity type @type does not support bundles', [
          '@type' => $entity_type_id,
        ]));
      }

      // Check if content exists for this bundle.
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $bundle_key = $entity_type_definition->getKey('bundle');

      if ($bundle_key) {
        $query = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition($bundle_key, $bundle)
          ->count();
        $count = $query->execute();

        if ($count > 0) {
          return ExecutableResult::failure($this->t('Cannot delete bundle "@bundle" of entity type "@type" because @count content item(s) exist. Delete all content of this bundle type first.', [
            '@bundle' => $bundle,
            '@type' => $entity_type_id,
            '@count' => $count,
          ]));
        }
      }

      // Load the existing bundle entity.
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = $bundle_storage->load($bundle);
      $bundle_entity->delete();
      return ExecutableResult::success($this->t('Successfully deleted bundle "@bundle" of entity type "@type".', [
        '@bundle' => $bundle,
        '@type' => $entity_type_id,
      ]), ['result' => TRUE]);
    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error deleting bundle "@bundle" of entity type "@type": @message', [
        '@bundle' => $bundle,
        '@type' => $entity_type_id,
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, $return_as_object = FALSE): bool|AccessResultInterface {
    $entity_type = $this->entityTypeManager->getDefinition($values['entity_type_id']);
    $bundle_entity_type = $entity_type->getBundleEntityType();
    if (!$bundle_entity_type) {
      $result = AccessResult::forbidden('Entity type does not support bundles');
      return $return_as_object ? $result : $result->isAllowed();
    }
    $bundle_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);
    if ($admin_permission = $bundle_definition->getAdminPermission()) {
      $result = AccessResult::allowedIfHasPermission($account, $admin_permission);
    }
    else {
      $result = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface {
    switch ($name) {
      case 'bundle':
        $definition->addConstraint('EntityBundleExists', $values['entity_type_id']);
        break;
    }
    return $definition;
  }

}
