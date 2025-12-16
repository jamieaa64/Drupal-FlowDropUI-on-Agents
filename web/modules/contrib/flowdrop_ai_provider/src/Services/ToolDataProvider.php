<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\Tool\ToolDefinition;

/**
 * Provides tool data for the FlowDrop UI.
 *
 * This service provides information about available tools that can be
 * attached to AI Agents in the FlowDrop visual editor.
 *
 * @see \Drupal\ai_integration_eca_agents\Services\DataProvider\DataProvider
 */
class ToolDataProvider implements ToolDataProviderInterface {

  /**
   * Constructs a ToolDataProvider.
   *
   * @param \Drupal\tool\Tool\ToolManager $toolManager
   *   The tool plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ToolManager $toolManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAvailableTools(string $viewMode = 'teaser'): array {
    $tools = [];

    foreach ($this->toolManager->getDefinitions() as $pluginId => $definition) {
      // ToolDefinition is an object, not an array.
      if ($definition instanceof ToolDefinition) {
        $toolInfo = [
          'id' => $pluginId,
          'label' => (string) $definition->getLabel(),
          'description' => (string) $definition->getDescription(),
          'category' => (string) ($definition->get('category') ?? 'General'),
        ];
      }
      else {
        // Fallback for array-based definitions.
        $toolInfo = [
          'id' => $pluginId,
          'label' => (string) ($definition['label'] ?? $pluginId),
          'description' => (string) ($definition['description'] ?? ''),
          'category' => (string) ($definition['category'] ?? 'General'),
        ];
      }

      // Add full details in 'full' view mode.
      if ($viewMode === 'full') {
        try {
          $toolInfo['configuration'] = $this->getToolConfigSchema($pluginId);
        }
        catch (\Exception $e) {
          $toolInfo['configuration'] = [];
        }
      }

      $tools[] = $toolInfo;
    }

    // Sort by category then label.
    usort($tools, function ($a, $b) {
      $categoryCompare = strcmp($a['category'], $b['category']);
      return $categoryCompare !== 0 ? $categoryCompare : strcmp($a['label'], $b['label']);
    });

    return $tools;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolsByCategory(): array {
    $tools = $this->getAvailableTools('teaser');
    $grouped = [];

    foreach ($tools as $tool) {
      $category = $tool['category'];
      if (!isset($grouped[$category])) {
        $grouped[$category] = [];
      }
      $grouped[$category][] = $tool;
    }

    // Sort categories alphabetically.
    ksort($grouped);

    return $grouped;
  }

  /**
   * {@inheritdoc}
   */
  public function getTool(string $toolId): ?array {
    if (!$this->toolManager->hasDefinition($toolId)) {
      return NULL;
    }

    $definition = $this->toolManager->getDefinition($toolId);

    // ToolDefinition is an object, not an array.
    if ($definition instanceof ToolDefinition) {
      $toolInfo = [
        'id' => $toolId,
        'label' => (string) $definition->getLabel(),
        'description' => (string) $definition->getDescription(),
        'category' => (string) ($definition->get('category') ?? 'General'),
        'configuration' => $this->getToolConfigSchema($toolId),
      ];
    }
    else {
      $toolInfo = [
        'id' => $toolId,
        'label' => (string) ($definition['label'] ?? $toolId),
        'description' => (string) ($definition['description'] ?? ''),
        'category' => (string) ($definition['category'] ?? 'General'),
        'configuration' => $this->getToolConfigSchema($toolId),
      ];
    }

    // Try to get input/output definitions.
    try {
      $instance = $this->toolManager->createInstance($toolId);
      $toolInfo['inputs'] = $this->normalizeDefinitions($instance->getInputDefinitions());
      $toolInfo['outputs'] = $this->normalizeDefinitions($instance->getOutputDefinitions());
    }
    catch (\Exception $e) {
      $toolInfo['inputs'] = [];
      $toolInfo['outputs'] = [];
    }

    return $toolInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolConfigSchema(string $toolId): array {
    if (!$this->toolManager->hasDefinition($toolId)) {
      return [];
    }

    try {
      $plugin = $this->toolManager->createInstance($toolId);

      // Check if plugin has configuration form.
      if (!$plugin instanceof ConfigurableInterface && !$plugin instanceof PluginFormInterface) {
        return [];
      }

      // Get default configuration.
      $defaultConfig = [];
      if ($plugin instanceof ConfigurableInterface) {
        $defaultConfig = $plugin->defaultConfiguration();
      }

      // Build configuration form to extract field info.
      if ($plugin instanceof PluginFormInterface) {
        $formState = new FormState();
        $form = $plugin->buildConfigurationForm([], $formState);

        return $this->extractConfigFromForm($form, $defaultConfig);
      }

      return [];
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateToolConfig(string $toolId, array $configuration): array {
    $errors = [];

    if (!$this->toolManager->hasDefinition($toolId)) {
      $errors[] = sprintf('Tool "%s" does not exist', $toolId);
      return $errors;
    }

    try {
      $plugin = $this->toolManager->createInstance($toolId, $configuration);

      // Validate using plugin's form validation if available.
      if ($plugin instanceof PluginFormInterface) {
        $formState = new FormState();
        $formState->setValues($configuration);
        $form = $plugin->buildConfigurationForm([], $formState);
        $plugin->validateConfigurationForm($form, $formState);

        foreach ($formState->getErrors() as $field => $error) {
          $errors[] = sprintf('%s: %s', $field, (string) $error);
        }
      }
    }
    catch (\Exception $e) {
      $errors[] = $e->getMessage();
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableAgents(string $viewMode = 'teaser'): array {
    $agents = [];
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $allAgents = $storage->loadMultiple();

    foreach ($allAgents as $agent) {
      $agentInfo = [
        'id' => $agent->id(),
        'label' => $agent->label(),
        'description' => $agent->get('description') ?? '',
        // Tool ID format for using agents as tools.
        'tool_id' => 'ai_agents::ai_agent::' . $agent->id(),
      ];

      if ($viewMode === 'full') {
        $agentInfo['orchestration_agent'] = $agent->get('orchestration_agent') ?? FALSE;
        $agentInfo['triage_agent'] = $agent->get('triage_agent') ?? FALSE;
        $agentInfo['max_loops'] = $agent->get('max_loops') ?? 3;
        $agentInfo['tools'] = array_keys(array_filter($agent->get('tools') ?? []));
      }

      $agents[] = $agentInfo;
    }

    // Sort by label.
    usort($agents, fn($a, $b) => strcmp($a['label'], $b['label']));

    return $agents;
  }

  /**
   * {@inheritdoc}
   */
  public function getAgentSchema(): array {
    return [
      '$schema' => 'http://json-schema.org/draft-07/schema#',
      'type' => 'object',
      'required' => ['id', 'label', 'description', 'system_prompt'],
      'properties' => [
        'id' => [
          'type' => 'string',
          'pattern' => '^[a-z0-9_]+$',
          'description' => 'Unique identifier for the agent (lowercase letters, numbers, underscores only)',
        ],
        'label' => [
          'type' => 'string',
          'maxLength' => 255,
          'description' => 'Human-readable name for the agent',
        ],
        'description' => [
          'type' => 'string',
          'description' => 'Description used by triage agents to select this agent',
        ],
        'system_prompt' => [
          'type' => 'string',
          'description' => 'Core instructions for agent behavior',
        ],
        'secured_system_prompt' => [
          'type' => 'string',
          'description' => 'System prompt with secure instructions (can contain tokens)',
        ],
        'tools' => [
          'type' => 'object',
          'description' => 'Map of tool IDs to boolean enabled status',
          'additionalProperties' => [
            'type' => 'boolean',
          ],
        ],
        'tool_settings' => [
          'type' => 'object',
          'description' => 'Per-tool settings',
        ],
        'orchestration_agent' => [
          'type' => 'boolean',
          'default' => FALSE,
          'description' => 'If true, agent only picks other agents for work',
        ],
        'triage_agent' => [
          'type' => 'boolean',
          'default' => FALSE,
          'description' => 'If true, agent can pick other agents AND do own work',
        ],
        'max_loops' => [
          'type' => 'integer',
          'minimum' => 1,
          'maximum' => 100,
          'default' => 3,
          'description' => 'Maximum iterations before stopping',
        ],
        'structured_output_enabled' => [
          'type' => 'boolean',
          'default' => FALSE,
          'description' => 'Enable structured output format',
        ],
        'structured_output_schema' => [
          'type' => 'string',
          'description' => 'JSON schema for structured output',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function searchTools(string $query): array {
    $allTools = $this->getAvailableTools('teaser');
    $query = strtolower($query);

    return array_values(array_filter($allTools, function ($tool) use ($query) {
      $searchFields = [
        strtolower($tool['id']),
        strtolower($tool['label']),
        strtolower($tool['description']),
        strtolower($tool['category']),
      ];

      foreach ($searchFields as $field) {
        if (str_contains($field, $query)) {
          return TRUE;
        }
      }

      return FALSE;
    }));
  }

  /**
   * Extracts configuration schema from a form array.
   *
   * @param array $form
   *   The form array.
   * @param array $defaultConfig
   *   Default configuration values.
   *
   * @return array
   *   Configuration schema.
   */
  protected function extractConfigFromForm(array $form, array $defaultConfig): array {
    $config = [];

    foreach ($form as $key => $element) {
      // Skip non-element keys.
      if (str_starts_with($key, '#')) {
        continue;
      }

      if (!is_array($element) || !isset($element['#type'])) {
        continue;
      }

      $fieldConfig = [
        'config_id' => $key,
        'name' => (string) ($element['#title'] ?? $key),
      ];

      if (!empty($element['#description'])) {
        $fieldConfig['description'] = (string) $element['#description'];
      }

      if (!empty($element['#options'])) {
        $fieldConfig['options'] = array_keys($element['#options']);
      }

      if (isset($defaultConfig[$key])) {
        $fieldConfig['value_type'] = gettype($defaultConfig[$key]);
        $fieldConfig['default'] = $defaultConfig[$key];
      }

      if (!empty($element['#required'])) {
        $fieldConfig['required'] = TRUE;
      }

      $config[] = $fieldConfig;
    }

    return $config;
  }

  /**
   * Normalizes context definitions to simple array format.
   *
   * @param array $definitions
   *   Array of context definitions.
   *
   * @return array
   *   Normalized definitions.
   */
  protected function normalizeDefinitions(array $definitions): array {
    $normalized = [];

    foreach ($definitions as $name => $definition) {
      $normalized[] = [
        'name' => $name,
        'type' => method_exists($definition, 'getDataType') ? $definition->getDataType() : 'string',
        'label' => method_exists($definition, 'getLabel') ? (string) $definition->getLabel() : $name,
        'description' => method_exists($definition, 'getDescription') ? (string) $definition->getDescription() : '',
        'required' => method_exists($definition, 'isRequired') ? $definition->isRequired() : FALSE,
      ];
    }

    return $normalized;
  }

}
