<?php

declare(strict_types=1);

namespace Drupal\tool_content\Plugin\tool\Tool;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field\FieldConfigInterface;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the field definitions by entity tool.
 */
#[Tool(
  id: 'entity_field_value_definitions',
  label: new TranslatableMarkup('Get field value definitions'),
  description: new TranslatableMarkup('Get base field and configurable field value schema for an entity type and bundle.'),
  operation: ToolOperation::Explain,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The entity type ID (e.g., node, user, taxonomy_term)."),
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Bundle"),
      description: new TranslatableMarkup("The bundle name (e.g., article, page for nodes).")
    ),
  ],
  output_definitions: [
    'base_field_definitions' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Base Field Definitions"),
      description: new TranslatableMarkup("Array of entity base field definitions with value schema.")
    ),
    'field_definitions' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Field Definitions"),
      description: new TranslatableMarkup("Array of bundle specific field definitions with value schema.")
    ),
  ],
)]
class EntityFieldValueDefinitions extends ToolBase {

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Encoder\DecoderInterface|\Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $serializer;

  /**
   * The content translation manager service.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|null
   */
  protected ?ContentTranslationManagerInterface $contentTranslationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->serializer = $container->get('serializer');
    $instance->contentTranslationManager = $container->has('content_translation.manager') ? $container->get('content_translation.manager') : NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity_type_id' => $entity_type_id, 'bundle' => $bundle] = $values;
    // @todo Simplify with normalizer.
    try {
      // Validate entity type exists.
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return ExecutableResult::failure($this->t('Entity type "@type" does not exist.', [
          '@type' => $entity_type_id,
        ]));
      }

      // Validate bundle exists.
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      if (!isset($bundle_info[$bundle])) {
        return ExecutableResult::failure($this->t('Bundle "@bundle" does not exist for entity type "@type".', [
          '@bundle' => $bundle,
          '@type' => $entity_type_id,
        ]));
      }
      // Get base field definitions.
      $base_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      $base_field_info = [];
      $base_field_input_definition = EntityStub::getBaseFieldInputDefinition($entity_type_id);
      foreach ($base_field_definitions as $field_name => $field_definition) {
        $base_field_info[$field_name] = $this->getFieldInfo($field_definition, $entity_type_id, $bundle);
        if ($property_definition = $base_field_input_definition->getPropertyDefinition($field_name)) {
          $base_field_info[$field_name]['value_schema'] = $this->serializer->normalize($property_definition, 'json_schema');
        }
      }

      // Get field definitions.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      $field_definitions = array_diff_key($field_definitions, $base_field_definitions);

      $field_info = [];
      foreach ($field_definitions as $field_name => $field_definition) {
        if (!$field_definition instanceof FieldConfigInterface) {
          // Skip if not a valid field definition.
          continue;
        }
        $field_info[$field_name] = $this->getFieldInfo($field_definition, $entity_type_id, $bundle);
        $field_input_definition = FieldSetValue::getFieldInputDefinition($field_definition);
        // Stub version of entity to get default field values.
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->create(['type' => $bundle]);
        $field_input_definition->setDefaultValue($field_definition->getDefaultValue($entity));
        $field_info[$field_name]['value_schema'] = $this->serializer->normalize($field_input_definition, 'json_schema');
      }

      return ExecutableResult::success($this->t('Found @base_count base field definitions and @count field definitions for @type:@bundle', [
        '@base_count' => count($base_field_info),
        '@count' => count($field_info),
        '@type' => $entity_type_id,
        '@bundle' => $bundle,
      ]), [
        'base_field_definitions' => $base_field_info,
        'field_definitions' => $field_info,
      ]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving field definitions: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Get field information including metadata and example values.
   */
  private function getFieldInfo(FieldDefinitionInterface $field_definition, string $entity_type_id, string $bundle): array {
    $field_type = $field_definition->getType();
    $settings = $field_definition->getSettings();

    $info = [
      'name' => $field_definition->getName(),
      'label' => $field_definition->getLabel(),
      'description' => $field_definition->getDescription(),
      'type' => $field_type,
      'required' => $field_definition->isRequired(),
      'cardinality' => $field_definition->getFieldStorageDefinition()->getCardinality(),
      'settings' => $settings,
    ];

    // Add translatable information if content translation is enabled.
    if ($this->moduleHandler->moduleExists('content_translation') && $this->contentTranslationManager) {
      $info['translatable'] = $this->isFieldTranslatable($field_definition, $entity_type_id, $bundle);
    }
    return $info;
  }

  /**
   * Check if a field is translatable.
   */
  private function isFieldTranslatable(FieldDefinitionInterface $field_definition, string $entity_type_id, string $bundle): bool {
    // Check if translation is enabled for this entity type and bundle.
    if (!$this->contentTranslationManager->isEnabled($entity_type_id, $bundle)) {
      return FALSE;
    }

    // Check if the field is translatable.
    return $field_definition->isTranslatable();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // @todo Come back to.
    return $return_as_object ? AccessResult::allowed() : AccessResult::allowed()->isAllowed();
  }

}
