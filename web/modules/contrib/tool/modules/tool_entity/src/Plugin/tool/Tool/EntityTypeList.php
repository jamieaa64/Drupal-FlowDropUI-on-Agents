<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity type list tool.
 */
#[Tool(
  id: 'entity_type_list',
  label: new TranslatableMarkup('List entity types'),
  description: new TranslatableMarkup('Get a list of all content entity types with their bundles'),
  operation: ToolOperation::Explain,
  input_definitions: [],
  output_definitions: [
    'entity_types' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Entity Types"),
      description: new TranslatableMarkup("Array of content entity types with their bundles")
    ),
  ],
)]
class EntityTypeList extends ToolBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

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
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    try {
      $entity_types_data = [];
      $entity_type_definitions = $this->entityTypeManager->getDefinitions();

      foreach ($entity_type_definitions as $entity_type_id => $entity_type_definition) {
        // Only include content entities.
        if (!$entity_type_definition->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface')) {
          continue;
        }

        $entity_type_info = [
          'id' => $entity_type_id,
          'label' => (string) $entity_type_definition->getLabel(),
          'bundle_entity_type' => $entity_type_definition->getBundleEntityType(),
          'bundle_label' => (string) $entity_type_definition->getBundleLabel(),
          'keys' => $entity_type_definition->getKeys(),
          'bundles' => [],
        ];

        // Get bundle information.
        $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        // @todo Get translation info.
        foreach ($bundle_info as $bundle_id => $bundle_data) {
          $bundle_info_array = [
            'id' => $bundle_id,
            'label' => $bundle_data['label'],
          ];
          $entity_type_info['bundles'][$bundle_id] = $bundle_info_array;
        }

        $entity_types_data[$entity_type_id] = $entity_type_info;
      }

      return ExecutableResult::success($this->t('Found @count content entity types with property schemas', [
        '@count' => count($entity_types_data),
      ]), ['entity_types' => $entity_types_data]);
    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving entity types: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to view entity information.
    // @todo decide on access control
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
