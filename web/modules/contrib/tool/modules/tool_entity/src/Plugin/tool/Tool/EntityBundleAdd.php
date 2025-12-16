<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Drupal\tool\TypedData\MapInputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity bundle add tool.
 */
#[Tool(
  id: 'entity_bundle_add',
  label: new TranslatableMarkup('Add bundle'),
  description: new TranslatableMarkup('Adds a new bundle for a given entity type.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type to add a bundle for."),
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle ID'),
      description: new TranslatableMarkup('The machine name of the bundle to create.'),
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('The human-readable label for the bundle.'),
    ),
    'properties' => new MapInputDefinition(
      label: new TranslatableMarkup('Properties'),
      description: new TranslatableMarkup('Additional properties to set on the bundle configuration entity.'),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'properties' => ['entity_type_id'],
  ],
)]
class EntityBundleAdd extends ToolBase implements ContainerFactoryPluginInterface, InputDefinitionRefinerInterface {

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
    $instance->typedDataManager = $container->get('typed_data_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle_id, 'label' => $label, 'properties' => $properties] = $values;

    try {
      // Get the entity type definition.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      if (!$entity_type) {
        return ExecutableResult::failure($this->t('Entity type @type does not exist', [
          '@type' => $entity_type_id,
        ]));
      }

      // Check if the entity type supports bundles.
      $bundle_entity_type = $entity_type->getBundleEntityType();
      if (!$bundle_entity_type) {
        return ExecutableResult::failure($this->t('Entity type @type does not support bundles', [
          '@type' => $entity_type_id,
        ]));
      }

      // Check if bundle already exists.
      $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
      if (isset($bundles[$bundle_id])) {
        return ExecutableResult::failure($this->t('Bundle @bundle already exists for entity type @type', [
          '@bundle' => $bundle_id,
          '@type' => $entity_type_id,
        ]));
      }

      // Get the bundle entity type definition.
      $bundle_entity_type_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);

      // Prepare the bundle values.
      $bundle_values = [
        $bundle_entity_type_definition->getKey('id') => $bundle_id,
        $bundle_entity_type_definition->getKey('label') => $label,
      ];

      // Add any additional properties.
      if (!empty($properties)) {
        $bundle_values = array_merge($bundle_values, $properties);
      }

      // Create the bundle entity.
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      $bundle_entity = $bundle_storage->create($bundle_values);
      $bundle_entity->save();

      return ExecutableResult::success($this->t('Successfully created bundle @bundle for entity type @type', [
        '@bundle' => $label,
        '@type' => $entity_type_id,
      ]));

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error creating bundle: @message', [
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
    if ($name === 'properties') {
      // Get the bundle entity type for the given entity type.
      $entity_type = $this->entityTypeManager->getDefinition($values['entity_type_id'], FALSE);
      if ($entity_type && $bundle_entity_type = $entity_type->getBundleEntityType()) {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $bundle_entity_definition */
        $bundle_entity_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);
        $definition = EntityBundleDefinition::getPropertiesInputDefinition($bundle_entity_definition);
      }
    }
    return $definition;
  }

}
