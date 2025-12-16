<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the field delete tool.
 */
#[Tool(
  id: 'field_delete',
  label: new TranslatableMarkup('Delete field from bundle'),
  description: new TranslatableMarkup('Deletes a field instance from a specific bundle. The field storage will remain unless no other bundles use it.'),
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
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The bundle to delete the field from (e.g., article, page).'),
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field Name'),
      description: new TranslatableMarkup('The machine name of the field to delete (e.g., field_tags).'),
    ),
  ],
  input_definition_refiners: [
    'bundle' => ['entity_type_id'],
    'field_name' => ['entity_type_id', 'bundle'],
  ],
)]
class FieldDelete extends ToolBase implements InputDefinitionRefinerInterface {

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
    [
      'entity_type_id' => $entity_type_id,
      'bundle' => $bundle,
      'field_name' => $field_name,
    ] = $values;

    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      // Load the field instance.
      $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);

      if (!$field) {
        return ExecutableResult::failure($this->t('Field @field does not exist on bundle @bundle of entity type @type', [
          '@field' => $field_name,
          '@bundle' => $bundle,
          '@type' => $entity_type_id,
        ]));
      }

      // Delete the field instance.
      $field->delete();

      return ExecutableResult::success($this->t('Successfully deleted field @field from bundle @bundle of entity type @type', [
        '@field' => $field_name,
        '@bundle' => $bundle,
        '@type' => $entity_type_id,
      ]), ['result' => TRUE]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error deleting field @field from bundle @bundle of entity type @type: @message', [
        '@field' => $field_name,
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
    // Check if user has permission to administer fields.
    $result = AccessResult::allowedIfHasPermission($account, 'administer ' . $values['entity_type_id'] . ' fields')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer site configuration'));

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

      case 'field_name':
        $definition->addConstraint('FieldExists', [
          'entityTypeId' => $values['entity_type_id'],
          'bundle' => $values['bundle'],
        ]);
        break;
    }
    return $definition;
  }

}
