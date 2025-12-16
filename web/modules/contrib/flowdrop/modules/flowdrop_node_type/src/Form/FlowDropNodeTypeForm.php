<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_type\Form;

use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flowdrop_node_type\Entity\FlowDropNodeType;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;
use Drupal\flowdrop_node_type\Service\FlowDropNodeTypeManager;
use Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FlowDrop Node Type form.
 */
final class FlowDropNodeTypeForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node type manager service.
   */
  protected FlowDropNodeTypeManager $nodeTypeManager;

  /**
   * The node executor plugin manager.
   */
  protected FlowDropNodeProcessorPluginManager $nodeProcessorPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->nodeTypeManager = $container->get('flowdrop_node_type.manager');
    $instance->nodeProcessorPluginManager = $container->get('flowdrop.node_processor_plugin_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    if (!$this->entity instanceof FlowDropNodeTypeInterface) {
      return $form;
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
      '#description' => $this->t('The human-readable name of this node type.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [FlowDropNodeType::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
      '#description' => $this->t('A unique machine-readable name for this node type.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('A brief description of what this node type does.'),
    ];

    // Tags.
    $form['tags_csv'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#default_value' => implode(', ', $this->entity->getTags()),
      '#description' => $this->t('Comma-separated tags for search and filtering. Example: input, text, user-input'),
      '#size' => 60,
      '#maxlength' => 500,
    ];

    // Load available categories using the service.
    $categories = $this->nodeTypeManager->getAvailableCategories();

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#default_value' => $this->entity->getCategory(),
      '#options' => $categories,
      '#required' => TRUE,
      '#description' => $this->t('The category this node type belongs to.'),
      '#empty_option' => $this->t('- Select a category -'),
    ];

    $form['icon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon'),
      '#default_value' => $this->entity->getIcon(),
      '#description' => $this->t('The icon identifier (e.g., mdi:cog, mdi:text).'),
    ];

    $form['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $this->entity->getColor(),
      '#description' => $this->t('The color for this node type.'),
    ];

    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->entity->getVersion(),
      '#description' => $this->t('The version of this node type (e.g., 1.0.0).'),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->isEnabled(),
      '#description' => $this->t('Whether this node type is enabled and available for use.'),
    ];

    // Get available FlowDropNode plugins.
    $available_plugins = $this->getAvailablePlugins();

    $form['executor_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Executor Plugin'),
      '#default_value' => $this->entity->getExecutorPlugin(),
      '#options' => $available_plugins,
      '#required' => TRUE,
      '#description' => $this->t('Select the FlowDropNode plugin that will handle this node type.'),
      '#empty_option' => $this->t('- Select a plugin -'),
      '#ajax' => [
        'callback' => '::updateConfigSchema',
        'wrapper' => 'config-wrapper',
        'method' => 'replace',
      ],
    ];

    // Configuration schema section.
    $form['config'] = [
      '#type' => 'details',
      '#title' => $this->t('Node Config'),
      '#open' => TRUE,
      '#prefix' => '<div id="config-wrapper">',
      '#suffix' => '</div>',
    ];

    $selected_plugin = $form_state->getValue('executor_plugin') ?: $this->entity->getExecutorPlugin();

    if ($selected_plugin && isset($available_plugins[$selected_plugin])) {
      // Get the plugin's config schema.
      $plugin_schema = $this->getPluginConfigSchema($selected_plugin);

      $form['config']['schema_info'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--status"><p>' .
        $this->t('Configuration schema loaded from plugin: @plugin', ['@plugin' => $available_plugins[$selected_plugin]]) .
        '</p></div>',
      ];

      // Configuration form wrapper.
      $form['config']['config_form'] = [
        '#type' => 'container',
        '#prefix' => '<div id="config-form-wrapper">',
        '#suffix' => '</div>',
      ];

      // Always show config form as editable.
      $form['config']['config_form'] += $this->buildConfigForm($plugin_schema, FALSE, []);

    }
    else {
      $form['config']['no_plugin_selected'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning"><p>' .
        $this->t('Please select an executor plugin to load its configuration schema.') .
        '</p></div>',
      ];
    }

    return $form;
  }

  /**
   * Get available FlowDropNode plugins.
   *
   * @return array
   *   Array of plugin IDs mapped to their labels.
   */
  protected function getAvailablePlugins(): array {
    $plugins = [];

    try {
      $definitions = $this->nodeProcessorPluginManager->getDefinitions();

      foreach ($definitions as $plugin_id => $definition) {
        $plugins[$plugin_id] = $definition['label']->render();
      }

      // Sort by label.
      asort($plugins);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error loading plugins: @error', ['@error' => $e->getMessage()]));
    }

    return $plugins;
  }

  /**
   * Get the config schema from a plugin.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return array
   *   The plugin's config schema.
   */
  protected function getPluginConfigSchema(string $plugin_id): array {
    try {
      $plugin = $this->nodeProcessorPluginManager->createInstance($plugin_id);
      return $plugin->getConfigSchema();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error loading schema for plugin @plugin: @error', [
        '@plugin' => $plugin_id,
        '@error' => $e->getMessage(),
      ]));
      return [];
    }
  }

  /**
   * AJAX callback to update the config schema section.
   */
  public function updateConfigSchema(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#config-wrapper', $form['config']));
    return $response;
  }

  /**
   * AJAX callback to update the config form section.
   */
  public function updateConfigForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Rebuild the config form.
    $selected_plugin = $form_state->getValue('executor_plugin');
    if ($selected_plugin) {
      $available_plugins = $this->getAvailablePlugins();
      if (isset($available_plugins[$selected_plugin])) {
        $plugin_schema = $this->getPluginConfigSchema($selected_plugin);
        $config_form = $this->buildConfigForm($plugin_schema, FALSE, []);
        $response->addCommand(new ReplaceCommand('#config-form-wrapper', $config_form));
      }
    }

    return $response;
  }

  /**
   * Build configuration form based on schema.
   *
   * @param array $schema
   *   The plugin's config schema.
   * @param bool $disabled
   *   Whether the form fields should be disabled (always FALSE now).
   * @param array $preserved_values
   *   Values to preserve (not used anymore).
   *
   * @return array
   *   Form elements for configuration.
   */
  protected function buildConfigForm(array $schema, bool $disabled = FALSE, array $preserved_values = []): array {
    $form = [];

    if (!$this->entity instanceof FlowDropNodeTypeInterface) {
      return $form;
    }

    $current_config = $this->entity->getConfig();

    if (isset($schema['properties'])) {
      $form['config_form_title'] = [
        '#type' => 'markup',
        '#markup' => '<h4>' . $this->t('Configuration Values') . '</h4>',
      ];

      foreach ($schema['properties'] as $property => $config) {
        // Always use current config values, fallback to plugin defaults.
        $default_value = $current_config[$property] ?? $config['default'] ?? NULL;
        $form[$property] = $this->buildConfigField($property, $config, $default_value, FALSE);
      }
    }

    return $form;
  }

  /**
   * Build a single configuration field based on schema.
   *
   * @param string $property
   *   The property name.
   * @param array $config
   *   The property configuration.
   * @param mixed $default_value
   *   The default value.
   * @param bool $disabled
   *   Whether the field should be disabled (always FALSE now).
   *
   * @return array
   *   Form element for the property.
   */
  protected function buildConfigField(string $property, array $config, $default_value, bool $disabled = FALSE): array {
    $field = [
      '#title' => $config['title'] ?? $property,
      '#description' => $config['description'] ?? '',
      '#default_value' => $default_value,
    ];

    if (!empty($config['format']) && $config['format'] === 'hidden') {
      return [
        '#type' => 'hidden',
        '#value' => $default_value,
      ];
    }

    $type = $config['type'] ?? 'string';
    if (!empty($config['readOnly'])) {
      $field['#disabled'] = TRUE;
    }
    switch ($type) {
      case 'boolean':
        $field['#type'] = 'checkbox';
        break;

      case 'integer':
        $field['#type'] = 'number';
        $field['#step'] = 1;
        if (isset($config['minimum'])) {
          $field['#min'] = $config['minimum'];
        }
        if (isset($config['maximum'])) {
          $field['#max'] = $config['maximum'];
        }
        break;

      case 'number':
        $field['#type'] = 'number';
        $field['#step'] = 'any';
        $field['#lang'] = 'en';
        if (isset($config['minimum'])) {
          $field['#min'] = $config['minimum'];
        }
        if (isset($config['maximum'])) {
          $field['#max'] = $config['maximum'];
        }
        break;

      case 'string':
        if (isset($config['enum'])) {
          $field['#type'] = 'select';
          $field['#options'] = array_combine($config['enum'], $config['enum']);
        }
        elseif (!empty($config['format']) && $config['format'] == 'multiline') {
          $field['#type'] = 'textarea';
        }
        else {
          $field['#type'] = 'textfield';
        }
        break;

      case 'object':
        $field['#type'] = 'textarea';
        $field['#rows'] = 3;
        $field['#description'] .= '<br/> ' . $this->t('Enter as JSON object.');
        $field['#default_value'] = json_encode($default_value);
        break;

      case 'array':
        $field['#type'] = 'textarea';
        $field['#rows'] = 3;
        $field['#description'] .= '<br/>' . $this->t('Enter as valid JSON array.');
        $field['#default_value'] = json_encode($default_value);
        break;

      default:
        $field['#type'] = 'textarea';
        $field['#rows'] = 3;
        $field['#description'] .= '<br/>' . $this->t('Enter as JSON.');
        break;
    }

    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    // Validate config form fields.
    $selected_plugin = $form_state->getValue('executor_plugin');
    if ($selected_plugin) {
      $plugin_schema = $this->getPluginConfigSchema($selected_plugin);
      $this->validateConfigForm($form_state, $plugin_schema, $form);
    }

    // Validate tags CSV.
    $tags_csv = $form_state->getValue('tags_csv');
    if (!empty($tags_csv)) {
      $tags = array_map('trim', explode(',', $tags_csv));
      // Remove empty tags.
      $tags = array_filter($tags);

      // Check for invalid characters in tags.
      foreach ($tags as $tag) {
        if (preg_match('/[^a-zA-Z0-9\-_]/', $tag)) {
          $form_state->setError($form['tags_csv'], $this->t('Tags can only contain letters, numbers, hyphens, and underscores. Invalid tag: @tag', ['@tag' => $tag]));
          break;
        }
      }
    }

    // Validate color format.
    $color = $form_state->getValue('color');
    if (!empty($color) && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
      $form_state->setError($form['color'], $this->t('Color must be a valid hex color (e.g., #007cba).'));
    }

    // Validate version format.
    $version = $form_state->getValue('version');
    if (!empty($version) && !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
      $form_state->setError($form['version'], $this->t('Version must be in semantic versioning format (e.g., 1.0.0).'));
    }

    // Validate config schema against plugin schema.
    $executor_plugin = $form_state->getValue('executor_plugin');
    $config_schema_json = $form_state->getValue('config_schema_json');

    if ($executor_plugin && !empty($config_schema_json)) {
      $this->validateConfigSchema($form_state, $executor_plugin, $config_schema_json, $form);
    }
  }

  /**
   * Validate configuration schema.
   */
  protected function validateConfigSchema($form_state, $executor_plugin, $config_schema_json, $form): void {

  }

  /**
   * Validate config form fields.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $plugin_schema
   *   The plugin schema.
   * @param array $form
   *   The form array.
   */
  protected function validateConfigForm(FormStateInterface $form_state, array $plugin_schema, array $form): void {
    if (!isset($plugin_schema['properties'])) {
      return;
    }

    foreach ($plugin_schema['properties'] as $property => $config) {
      $value = $form_state->getValue($property);

      // Skip validation for empty values unless required.
      if (empty($value) && !($config['required'] ?? FALSE)) {
        continue;
      }
      if (!empty($config['format']) && $config['format'] === 'hidden') {
        continue;
      }
      $type = $config['type'] ?? 'string';

      switch ($type) {
        case 'integer':
        case 'number':
          if (!is_numeric($value)) {
            $form_state->setError($form['config']['config_form'][$property], $this->t('@property must be a number.',
              [
                '@property' => $config['title'] ?? $property,
              ]));
          }
          else {
            $num_value = (float) $value;
            if (isset($config['minimum']) && $num_value < $config['minimum']) {
              $form_state->setError($form['config']['config_form'][$property], $this->t('@property must be at least @min.',
                [
                  '@property' => $config['title'] ?? $property,
                  '@min' => $config['minimum'],
                ]
              ));
            }
            if (isset($config['maximum']) && $num_value > $config['maximum']) {
              $form_state->setError($form['config']['config_form'][$property], $this->t('@property must be at most @max.',
                [
                  '@property' => $config['title'] ?? $property,
                  '@max' => $config['maximum'],
                ]
              ));
            }
          }
          break;

        case 'string':
          if (isset($config['enum']) && !in_array($value, $config['enum'])) {
            $form_state->setError($form['config']['config_form'][$property], $this->t('@property must be one of: @values',
              [
                '@property' => $config['title'] ?? $property,
                '@values' => implode(', ', $config['enum']),
              ]
            ));
          }
          break;

        case 'object':
        case 'array':
          if (!empty($value)) {
            json_decode($value, TRUE);
            if (json_last_error() !== JSON_ERROR_NONE) {
              $form_state->setError($form['config']['config_form'][$property], $this->t('Invalid JSON format: @error',
                [
                  '@error' => json_last_error_msg(),
                ]));
            }
          }
          break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    if (!$this->entity instanceof FlowDropNodeTypeInterface) {
      return;
    }

    // Set basic fields.
    $this->entity->setDescription($form_state->getValue('description'));
    $this->entity->setCategory($form_state->getValue('category'));
    $this->entity->setIcon($form_state->getValue('icon'));
    $this->entity->setColor($form_state->getValue('color'));
    $this->entity->setVersion($form_state->getValue('version'));
    $this->entity->setEnabled((bool) $form_state->getValue('enabled'));
    $this->entity->setExecutorPlugin($form_state->getValue('executor_plugin'));

    // Set config schema from form values.
    $config = [];
    $selected_plugin = $form_state->getValue('executor_plugin');
    if ($selected_plugin) {
      $plugin_schema = $this->getPluginConfigSchema($selected_plugin);
      if (isset($plugin_schema['properties'])) {
        foreach ($plugin_schema['properties'] as $property => $config_def) {
          $value = $form_state->getValue($property);
          if (!empty($config_def['format']) && $config_def['format'] === 'hidden') {
            $config[$property] = $value;
          }
          elseif ($value !== NULL && $value !== '') {
            // Process JSON fields.
            if (in_array($config_def['type'] ?? 'string', ['object', 'array'])) {
              if (!empty($value)) {
                $decoded = json_decode($value, TRUE);
                if (json_last_error() === JSON_ERROR_NONE) {
                  $config[$property] = $decoded;
                }
              }
            }
            else {
              $config[$property] = $value;
            }
          }
        }
      }
    }
    $this->entity->setConfig($config);

    // Set tags.
    $tags_csv = $form_state->getValue('tags_csv');
    if (!empty($tags_csv)) {
      $tags = array_map('trim', explode(',', $tags_csv));
      // Remove empty tags.
      $tags = array_filter($tags);
      $this->entity->setTags($tags);
    }
    else {
      $this->entity->setTags([]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];

    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new flowdrop node type %label.', $message_args),
        default => $this->t('Updated flowdrop node type %label.', $message_args),
      }
    );

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
