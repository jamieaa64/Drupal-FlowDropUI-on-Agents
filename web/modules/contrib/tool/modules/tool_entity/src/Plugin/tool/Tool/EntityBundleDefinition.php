<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity bundle list tool.
 */
#[Tool(
  id: 'entity_bundle_definition',
  label: new TranslatableMarkup('Get bundle definition'),
  description: new TranslatableMarkup('Returns the bundle definition and properties schema for a given entity type.'),
  operation: ToolOperation::Explain,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type ID"),
      description: new TranslatableMarkup("The machine name of the entity type."),
      constraints: [
        'PluginExists' => [
          'manager' => 'entity_type.manager',
          // @todo Constrain to only entities that support bundles.
          'interface' => ContentEntityInterface::class,
        ],
      ],
    ),
  ],
  output_definitions: [
    'bundle_definition' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Bundle Definition"),
      required: FALSE,
      description: new TranslatableMarkup("Schema describing the bundle configuration properties."),
    ),
  ],
)]
class EntityBundleDefinition extends ToolBase implements ContainerFactoryPluginInterface {

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
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Encoder\DecoderInterface|\Symfony\Component\Serializer\Normalizer\NormalizerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->typedConfigManager = $container->get('config.typed');
    $instance->serializer = $container->get('serializer');
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
      // Get the bundle entity type if it exists.
      $bundle_entity_type = $entity_type->getBundleEntityType();
      // @todo Move to constraint.
      if (!$bundle_entity_type) {
        return ExecutableResult::failure($this->t('Entity type @type does not support bundles', [
          '@type' => $entity_type_id,
        ]));
      }
      // Generate bundle definition schema.
      $bundle_definition = $this->getBundleDefinition($entity_type);

      return ExecutableResult::success($this->t('Successfully retrieved bundle definition for entity type @type', [
        '@type' => $entity_type_id,
      ]), [
        'bundle_definition' => $bundle_definition,
      ]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving bundles: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Get the bundle definition schema for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   *
   * @return array
   *   Array describing the bundle configuration schema.
   */
  protected function getBundleDefinition($entity_type): array {
    $definition['description'] = $this->t(
      'Schema for @bundle bundle configuration',
       ['@bundle' => $entity_type->getBundleLabel()]
    );
    $bundle_entity_type = $entity_type->getBundleEntityType();
    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $bundle_entity_definition */
    $bundle_entity_definition = $this->entityTypeManager->getDefinition($bundle_entity_type);
    $properties_definition = self::getPropertiesInputDefinition($bundle_entity_definition);
    $definition = [
      'description' => $this->t("Schema for '@bundle' bundle configuration", ['@bundle' => $entity_type->getBundleLabel()]),
      'keys' => array_filter(
        $bundle_entity_definition->getKeys(),
        static fn($key, $name) => !empty($key) && $name !== $key,
        ARRAY_FILTER_USE_BOTH
      ),
      'links' => $bundle_entity_definition->getLinkTemplates(),
      'properties_schema' => $this->serializer->normalize($properties_definition, 'json_schema'),
    ];

    return $definition;
  }

  /**
   * Get the properties input definition for a bundle config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $bundle_definition
   *   The bundle entity type definition.
   *
   * @return \Drupal\tool\TypedData\MapInputDefinition
   *   The properties input definition.
   */
  public static function getPropertiesInputDefinition(ConfigEntityTypeInterface $bundle_definition): MapInputDefinition {
    // @todo Add translation support.
    $id_key = $bundle_definition->getKey('id');
    $label_key = $bundle_definition->getKey('label');
    $typed_config_manager = \Drupal::service('config.typed');
    $properties = [];
    $config_id = $bundle_definition->getConfigPrefix() . '.*';
    // @todo Consider adding defaults for common entity keys.
    if ($typed_config_manager->hasConfigSchema($config_id)) {
      $definition = $typed_config_manager->getDefinition($config_id);
      foreach ($definition['mapping'] as $name => $config_schema) {
        // @todo Come back to a few of these.
        $excluded_keys = [
          'uuid',
          '_core',
          'dependencies',
          'third_party_settings',
          'revision_translation_affected',
          $id_key,
          $label_key,
        ];
        if (in_array($name, $excluded_keys, TRUE)) {
          continue;
        }
        $properties[$name] = InputDefinition::fromConfigSchema($config_schema);
      }
    }

    if ($bundle_definition->hasFormClasses()) {
      $forms = $bundle_definition->getHandlerClasses()['form'];
      if (isset($forms['add'])) {
        $op = 'add';
      }
      elseif (isset($forms['default'])) {
        $op = 'default';
      }
      else {
        $op = array_key_first($forms);
      }
      $bundle_storage = \Drupal::entityTypeManager()
        ->getStorage($bundle_definition->id());
      $bundle = $bundle_storage->create([]);
      $form = \Drupal::service('entity.form_builder')->getForm($bundle, $op);
      $elements = self::getMatchingFormProperties($form, array_keys($properties));
      foreach (array_intersect_key($properties, $elements) as $key => $value) {
        $form_element = $elements[$key];
        $properties[$key]->setLabel($form_element['#title'] ?? $properties[$key]->getLabel());
        $properties[$key]->setDescription($form_element['#description'] ?? $properties[$key]->getDescription());
        if (!empty($form_element['#options'])) {
          $properties[$key]->addConstraint('Choice', array_keys($form_element['#options']));
          $description = trim((string) $properties[$key]->getDescription(), '. ') . '.';
          $description .= ' Allowed values: ' . json_encode($form_element['#options'], JSON_FORCE_OBJECT);
          $properties[$key]->setDescription($description);
        }
        if (!empty($form_element['#required'])) {
          $properties[$key]->setRequired(TRUE);
        }
        if (!empty($form_element['#default_value'])) {
          $default_value = $form_element['#default_value'];
          if (is_array($default_value) || is_object($default_value)) {
            $default_value = json_decode(json_encode($default_value), TRUE);
          }
          $properties[$key]->setDefaultValue($default_value);

          $schema[$key]['description'] = (string) $form_element['#title'] ?? '';
          if (!empty($form_element['#description'])) {
            if (empty($schema[$key]['description'])) {
              $schema[$key]['description'] = (string) $form_element['#description'];
            }
            else {
              $schema[$key]['description'] .= ': ' . (string) $form_element['#description'];
            }
          }
        }
      }
    }
    return new MapInputDefinition(
      label: new TranslatableMarkup('Properties'),
      description: new TranslatableMarkup('Additional properties to set on the bundle configuration entity.'),
      required: FALSE,
      property_definitions: $properties,
    );
  }

  /**
   * Recursively get matching form properties.
   *
   * @param array $form
   *   The form array.
   * @param array $properties
   *   The list of property names to match.
   */
  public static function getMatchingFormProperties(array $form, array $properties) {
    $matched = [];
    foreach (Element::getVisibleChildren($form) as $key) {
      if (in_array($key, $properties, TRUE)) {
        $matched[$key] = $form[$key];
      }
      elseif (Element::getVisibleChildren($form[$key])) {
        $child_matched = self::getMatchingFormProperties($form[$key], $properties);
        $matched = array_merge($matched, $child_matched);
      }
    }
    return $matched;
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

}
