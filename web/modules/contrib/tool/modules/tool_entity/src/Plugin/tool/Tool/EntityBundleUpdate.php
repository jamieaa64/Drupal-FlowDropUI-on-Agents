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
 * Plugin implementation of the entity bundle update tool.
 */
#[Tool(
  id: 'entity_bundle_update',
  label: new TranslatableMarkup('Update bundle'),
  description: new TranslatableMarkup('Updates an existing bundle for a given entity type.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type."),
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle ID'),
      description: new TranslatableMarkup('The machine name of the bundle to update.'),
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('The human-readable label for the bundle.'),
      required: FALSE,
    ),
    'properties' => new MapInputDefinition(
      label: new TranslatableMarkup('Properties'),
      description: new TranslatableMarkup('Additional properties to update on the bundle configuration entity.'),
      required: FALSE,
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
    'properties' => ['entity_type_id'],
  ],
)]
class EntityBundleUpdate extends ToolBase implements ContainerFactoryPluginInterface, InputDefinitionRefinerInterface {

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

      // Check if bundle exists.
      $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
      if (!isset($bundles[$bundle_id])) {
        return ExecutableResult::failure($this->t('Bundle @bundle does not exist for entity type @type', [
          '@bundle' => $bundle_id,
          '@type' => $entity_type_id,
        ]));
      }

      // Get the bundle entity type definition.
      $bundle_entity_type_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);

      // Load the existing bundle entity.
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = $bundle_storage->load($bundle_id);

      if (!$bundle_entity) {
        return ExecutableResult::failure($this->t('Bundle entity @bundle could not be loaded', [
          '@bundle' => $bundle_id,
        ]));
      }

      // Update the label if provided.
      if ($label !== NULL) {
        $label_key = $bundle_entity_type_definition->getKey('label');
        if ($label_key) {
          $bundle_entity->set($label_key, $label);
        }
      }

      // Update any additional properties.
      if (!empty($properties)) {
        foreach ($properties as $property_name => $property_value) {
          $bundle_entity->set($property_name, $property_value);
        }
      }

      // Save the bundle entity.
      $bundle_entity->save();

      return ExecutableResult::success($this->t('Successfully updated bundle @bundle for entity type @type', [
        '@bundle' => $label ?? $bundle_id,
        '@type' => $entity_type_id,
      ]));

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error updating bundle: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, $return_as_object = FALSE): bool|AccessResultInterface {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle_id] = $values;

    try {
      // Get the entity type definition.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
      if (!$entity_type) {
        $result = AccessResult::forbidden('Entity type does not exist');
        return $return_as_object ? $result : $result->isAllowed();
      }

      // Get the bundle entity type.
      $bundle_entity_type = $entity_type->getBundleEntityType();
      if (!$bundle_entity_type) {
        $result = AccessResult::forbidden('Entity type does not support bundles');
        return $return_as_object ? $result : $result->isAllowed();
      }

      // Load the bundle entity to check update access.
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
      $bundle_entity = $bundle_storage->load($bundle_id);

      if (!$bundle_entity) {
        $result = AccessResult::forbidden('Bundle entity does not exist');
        return $return_as_object ? $result : $result->isAllowed();
      }

      // Check if user has permission to update the bundle entity.
      $access_result = $bundle_entity->access('update', $account, TRUE);

      return $return_as_object ? $access_result : $access_result->isAllowed();
    }
    catch (\Exception $e) {
      $result = AccessResult::forbidden('Error checking access');
      return $return_as_object ? $result : $result->isAllowed();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refineInputDefinition(string $name, InputDefinitionInterface $definition, array $values): InputDefinitionInterface {
    switch ($name) {
      case 'bundle':
        $definition->addConstraint('EntityBundleExists', $values['entity_type_id']);
        break;

      case 'properties':
        $entity_type = $this->entityTypeManager->getDefinition($values['entity_type_id'], FALSE);
        if ($entity_type && $bundle_entity_type = $entity_type->getBundleEntityType()) {
          $bundle_entity_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);
          /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $bundle_entity_definition */
          $definition = EntityBundleDefinition::getPropertiesInputDefinition($bundle_entity_definition);
          foreach ($definition->getPropertyDefinitions() as $property_definition) {
            $property_definition->setRequired(FALSE);
          }
        }
        break;
    }
    return $definition;
  }

}
