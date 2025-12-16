<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity bundle list tool.
 */
#[Tool(
  id: 'entity_bundle_list',
  label: new TranslatableMarkup('List entity bundles'),
  description: new TranslatableMarkup('Returns a list of bundles and their settings for a given entity type.'),
  operation: ToolOperation::Explain,
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
  ],
  output_definitions: [
    'bundles' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Bundles"),
      description: new TranslatableMarkup("Array of bundles keyed by bundle name with their settings."),
    ),
  ],
)]
class EntityBundleList extends ToolBase implements ContainerFactoryPluginInterface {

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
   * The typed config manager service.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->typedConfigManager = $container->get('config.typed');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity_type_id' => $entity_type_id] = $values;

    try {
      // Get the entity type definition.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      if (!$entity_type) {
        return ExecutableResult::failure($this->t('Entity type @type does not exist', [
          '@type' => $entity_type_id,
        ]));
      }

      // Get bundle information.
      $bundle_info = $this->bundleInfo->getBundleInfo($entity_type_id);
      $bundles_data = [];
      foreach ($bundle_info as $bundle_id => $bundle_data) {
        $bundle_settings = [
          'id' => $bundle_id,
          'label' => (string) $bundle_data['label'],
        ];
        // If entity type has bundle entities, try to load the bundle entity
        // to get additional configuration.
        if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
          try {
            $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
            $bundle_entity = $bundle_storage->load($bundle_id);
            if ($bundle_entity) {
              $bundle_settings = $bundle_entity->toArray();
            }
          }
          catch (\Exception $e) {
            // Bundle entity loading failed, continue with basic info.
          }
        }
        $bundle_settings['allows_multiple_bundles'] = !empty($bundle_entity_type);
        // Add translatable flag if available.
        if (isset($bundle_data['translatable'])) {
          $bundle_settings['translatable'] = $bundle_data['translatable'];
        }

        $bundles_data[$bundle_id] = $bundle_settings;
      }

      return ExecutableResult::success($this->t('Found @count bundles for entity type @type', [
        '@count' => count($bundles_data),
        '@type' => $entity_type_id,
      ]), [
        'bundles' => $bundles_data,
      ]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving bundles: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, $return_as_object = FALSE): bool|AccessResultInterface {
    ['entity_type_id' => $entity_type_id] = $values;
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_entity_type = $entity_type->getBundleEntityType();
    if (!$bundle_entity_type) {
      $result = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
    }
    else {
      $bundle_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);
      if ($admin_permission = $bundle_definition->getAdminPermission()) {
        $result = AccessResult::allowedIfHasPermission($account, $admin_permission);
      }
      else {
        $result = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

}
