<?php

declare(strict_types=1);

namespace Drupal\tool_entity\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the field type definitions tool.
 */
#[Tool(
  id: 'field_type_definitions',
  label: new TranslatableMarkup('Get field type definitions'),
  description: new TranslatableMarkup('Get definitions of all available field types on the site with their settings schemas'),
  operation: ToolOperation::Explain,
  output_definitions: [
    'field_types' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Field Types"),
      description: new TranslatableMarkup("Array of field types with their definitions")
    ),
  ],
)]
class FieldTypeDefinitions extends ToolBase {

  /**
   * The field type plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected FieldTypePluginManagerInterface $fieldTypePluginManager;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Encoder\DecoderInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');
    $instance->serializer = $container->get('serializer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    try {
      $field_types_data = [];
      $field_type_definitions = $this->fieldTypePluginManager->getDefinitions();

      foreach ($field_type_definitions as $field_type_id => $field_type_definition) {
        if ($field_type_definition['no_ui'] ?? FALSE) {
          continue;
        }
        $description = '';
        if (isset($field_type_definition['description'])) {
          if (is_array($field_type_definition['description'])) {
            foreach ($field_type_definition['description'] as $part) {
              $description .= (string) $part . '. ';
            }
            $description = trim($description);
          }
          else {
            $description = (string) $field_type_definition['description'];
          }
        }

        // Hide field types that are not used in the UI.
        $field_type_info = [
          'id' => $field_type_id,
          'label' => (string) $field_type_definition['label'],
          'description' => $description,
          'category' => isset($field_type_definition['category']) ? (string) $field_type_definition['category'] : '',
          'default_widget' => $field_type_definition['default_widget'] ?? NULL,
          'default_formatter' => $field_type_definition['default_formatter'] ?? NULL,
        ];

        // Add cardinality information if available.
        if (isset($field_type_definition['cardinality'])) {
          $field_type_info['cardinality'] = $field_type_definition['cardinality'];
        }
        $field_types_data[$field_type_id] = $field_type_info;
      }

      return ExecutableResult::success($this->t('Found @count field types', [
        '@count' => count($field_types_data),
      ]), ['field_types' => $field_types_data]);
    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving field types: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to view field information.
    // Using the same permission as entity type administration.
    $result = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
