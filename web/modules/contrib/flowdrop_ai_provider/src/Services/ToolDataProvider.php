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
        $label = (string) $definition->getLabel();
        $description = (string) $definition->getDescription();
        $category = (string) ($definition->get('category') ?? 'General');
      }
      else {
        // Fallback for array-based definitions.
        $label = (string) ($definition['label'] ?? $pluginId);
        $description = (string) ($definition['description'] ?? '');
        $category = (string) ($definition['category'] ?? 'General');
      }

      // Build FlowDrop-compatible node format.
      // Get base tool settings schema plus any plugin-specific config.
      $configSchema = $this->getToolSettingsSchema($pluginId);

      $toolInfo = [
        'id' => $pluginId,
        'name' => $label,
        'type' => 'tool',
        'supportedTypes' => ['tool'],
        'description' => $description,
        'category' => $this->transformCategoryToPlural($category),
        'icon' => 'mdi:tools',
        'color' => $this->getCategoryColor($category),
        'version' => '1.0.0',
        'enabled' => TRUE,
        'tags' => [$category],
        'executor_plugin' => 'tool:' . $pluginId,
        'inputs' => [
          [
            'id' => 'trigger',
            'name' => 'Trigger',
            'type' => 'input',
            'dataType' => 'trigger',
            'required' => FALSE,
            'description' => 'Trigger input',
          ],
        ],
        'outputs' => [
          [
            'id' => 'result',
            'name' => 'Result',
            'type' => 'output',
            'dataType' => 'mixed',
            'required' => FALSE,
            'description' => 'Tool execution result',
          ],
        ],
        'config' => [],
        'configSchema' => $configSchema,
      ];

      // Add full details in 'full' view mode.
      if ($viewMode === 'full') {
        try {
          $toolInfo['configSchema'] = $this->getToolConfigSchema($pluginId);
          // Get more detailed input/output definitions.
          $instance = $this->toolManager->createInstance($pluginId);
          $inputDefs = $this->normalizeDefinitions($instance->getInputDefinitions());
          $outputDefs = $this->normalizeDefinitions($instance->getOutputDefinitions());

          if (!empty($inputDefs)) {
            // Add trigger input first.
            $toolInfo['inputs'] = array_merge(
              [[
                'id' => 'trigger',
                'name' => 'Trigger',
                'type' => 'input',
                'dataType' => 'trigger',
                'required' => FALSE,
                'description' => 'Trigger input',
              ]],
              $this->convertToFlowDropPorts($inputDefs, 'input')
            );
          }
          if (!empty($outputDefs)) {
            $toolInfo['outputs'] = $this->convertToFlowDropPorts($outputDefs, 'output');
          }
        }
        catch (\Exception $e) {
          // Keep defaults on error.
        }
      }

      $tools[] = $toolInfo;
    }

    // Sort by category then name.
    usort($tools, function ($a, $b) {
      $categoryCompare = strcmp($a['category'], $b['category']);
      return $categoryCompare !== 0 ? $categoryCompare : strcmp($a['name'], $b['name']);
    });

    return $tools;
  }

  /**
   * Convert normalized definitions to FlowDrop port format.
   *
   * @param array $definitions
   *   Normalized definitions.
   * @param string $portType
   *   Port type ('input' or 'output').
   *
   * @return array
   *   FlowDrop port format.
   */
  protected function convertToFlowDropPorts(array $definitions, string $portType): array {
    $ports = [];
    foreach ($definitions as $def) {
      $ports[] = [
        'id' => $def['name'],
        'name' => $def['label'],
        'type' => $portType,
        'dataType' => $this->mapTypeToFlowDropType($def['type']),
        'required' => $def['required'] ?? FALSE,
        'description' => $def['description'] ?? '',
      ];
    }
    return $ports;
  }

  /**
   * Map PHP/Drupal type to FlowDrop data type.
   *
   * @param string $type
   *   The type string.
   *
   * @return string
   *   FlowDrop data type.
   */
  protected function mapTypeToFlowDropType(string $type): string {
    $mapping = [
      'string' => 'string',
      'text' => 'string',
      'integer' => 'number',
      'int' => 'number',
      'float' => 'number',
      'boolean' => 'boolean',
      'bool' => 'boolean',
      'array' => 'array',
      'list' => 'array',
      'object' => 'json',
      'map' => 'json',
      'any' => 'mixed',
    ];
    return $mapping[$type] ?? 'string';
  }

  /**
   * Transform singular category to plural form for FlowDrop API.
   *
   * @param string $category
   *   The singular category.
   *
   * @return string
   *   The plural category.
   */
  protected function transformCategoryToPlural(string $category): string {
    $mapping = [
      'trigger' => 'triggers',
      'input' => 'inputs',
      'output' => 'outputs',
      'model' => 'models',
      'prompt' => 'prompts',
      'processing' => 'processing',
      'logic' => 'logic',
      'data' => 'data',
      'helper' => 'helpers',
      'tool' => 'tools',
      'vectorstore' => 'vectorstores',
      'embedding' => 'embeddings',
      'memory' => 'memories',
      'agent' => 'agents',
      'bundle' => 'bundles',
      'General' => 'tools',
    ];
    return $mapping[$category] ?? strtolower($category) . 's';
  }

  /**
   * Get color for a category.
   *
   * @param string $category
   *   The category.
   *
   * @return string
   *   CSS color value.
   */
  protected function getCategoryColor(string $category): string {
    $colors = [
      'trigger' => 'var(--color-ref-red-500)',
      'input' => 'var(--color-ref-blue-500)',
      'output' => 'var(--color-ref-green-500)',
      'model' => 'var(--color-ref-purple-500)',
      'tool' => 'var(--color-ref-orange-500)',
      'agent' => 'var(--color-ref-cyan-500)',
      'General' => 'var(--color-ref-gray-500)',
    ];
    return $colors[$category] ?? 'var(--color-ref-gray-500)';
  }

  /**
   * {@inheritdoc}
   */
  public function getToolsByCategory(): array {
    $tools = $this->getAvailableTools('teaser');
    $grouped = [];

    foreach ($tools as $tool) {
      // Use the already-transformed plural category from the tool.
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
      $agentId = $agent->id();
      $label = $agent->label();
      $description = $agent->get('description') ?? '';

      // Build FlowDrop-compatible node format for agents.
      $agentInfo = [
        'id' => 'ai_agent_' . $agentId,
        'name' => $label,
        'type' => 'agent',
        'supportedTypes' => ['agent'],
        'description' => $description,
        'category' => 'agents',
        'icon' => 'mdi:robot',
        'color' => 'var(--color-ref-purple-500)',
        'version' => '1.0.0',
        'enabled' => TRUE,
        'tags' => ['agent'],
        'executor_plugin' => 'ai_agents::ai_agent::' . $agentId,
        'agent_id' => $agentId,
        'tool_id' => 'ai_agents::ai_agent::' . $agentId,
        'inputs' => [
          [
            'id' => 'trigger',
            'name' => 'Trigger',
            'type' => 'input',
            'dataType' => 'trigger',
            'required' => FALSE,
            'description' => 'Trigger input',
          ],
          [
            'id' => 'message',
            'name' => 'Message',
            'type' => 'input',
            'dataType' => 'string',
            'required' => FALSE,
            'description' => 'Input message',
          ],
        ],
        'outputs' => [
          [
            'id' => 'response',
            'name' => 'Response',
            'type' => 'output',
            'dataType' => 'string',
            'required' => FALSE,
            'description' => 'Agent response',
          ],
        ],
        'config' => [],
        'configSchema' => $this->getAgentConfigSchema(),
      ];

      if ($viewMode === 'full') {
        $agentInfo['orchestration_agent'] = $agent->get('orchestration_agent') ?? FALSE;
        $agentInfo['triage_agent'] = $agent->get('triage_agent') ?? FALSE;
        $agentInfo['max_loops'] = $agent->get('max_loops') ?? 3;
        $agentInfo['tools'] = array_keys(array_filter($agent->get('tools') ?? []));
      }

      $agents[] = $agentInfo;
    }

    // Sort by name.
    usort($agents, fn($a, $b) => strcmp($a['name'], $b['name']));

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
   * Gets the tool settings schema including per-tool settings.
   *
   * This includes the standard AI Agent tool settings (return_directly,
   * require_usage, etc.) plus any plugin-specific configuration.
   *
   * @param string $toolId
   *   The tool plugin ID.
   *
   * @return array
   *   The config schema for FlowDrop config panel.
   */
  protected function getToolSettingsSchema(string $toolId): array {
    // Start with base tool settings that apply to all tools.
    $schema = [
      [
        'config_id' => 'tool_id',
        'name' => 'Tool ID',
        'description' => 'The tool plugin ID (read-only)',
        'value_type' => 'string',
        'required' => TRUE,
        'default' => $toolId,
      ],
      [
        'config_id' => 'return_directly',
        'name' => 'Return Directly',
        'description' => 'Return tool result directly without LLM rewriting. Use for API responses or structured output.',
        'value_type' => 'boolean',
        'default' => FALSE,
      ],
      [
        'config_id' => 'require_usage',
        'name' => 'Require Usage',
        'description' => 'Remind the agent if it tries to output without using this tool first.',
        'value_type' => 'boolean',
        'default' => FALSE,
      ],
      [
        'config_id' => 'use_artifacts',
        'name' => 'Use Artifact Storage',
        'description' => 'Store large responses in artifacts instead of sending to AI. Reference with {{artifact:tool_name:index}}',
        'value_type' => 'boolean',
        'default' => FALSE,
      ],
      [
        'config_id' => 'description_override',
        'name' => 'Override Tool Description',
        'description' => 'Custom description sent to LLM instead of default. Leave empty to use default.',
        'value_type' => 'text',
        'default' => '',
      ],
      [
        'config_id' => 'progress_message',
        'name' => 'Progress Message',
        'description' => 'Message shown in UI while tool is executing.',
        'value_type' => 'string',
        'default' => '',
      ],
    ];

    // Add any tool-specific configuration from the plugin.
    $pluginSchema = $this->getToolConfigSchema($toolId);
    if (!empty($pluginSchema)) {
      $schema = array_merge($schema, $pluginSchema);
    }

    return $schema;
  }

  /**
   * Gets the configuration schema for agent nodes.
   *
   * @return array
   *   The config schema for FlowDrop config panel.
   */
  protected function getAgentConfigSchema(): array {
    return [
      [
        'config_id' => 'label',
        'name' => 'Label',
        'description' => 'Human-readable name for the agent',
        'value_type' => 'string',
        'required' => TRUE,
      ],
      [
        'config_id' => 'description',
        'name' => 'Description',
        'description' => 'Description used by triage agents to select this agent',
        'value_type' => 'string',
        'required' => TRUE,
      ],
      [
        'config_id' => 'systemPrompt',
        'name' => 'System Prompt',
        'description' => 'Core instructions for agent behavior',
        'value_type' => 'text',
        'required' => TRUE,
      ],
      [
        'config_id' => 'maxLoops',
        'name' => 'Max Loops',
        'description' => 'Maximum iterations before stopping (1-100)',
        'value_type' => 'integer',
        'default' => 3,
      ],
      [
        'config_id' => 'orchestrationAgent',
        'name' => 'Orchestration Agent',
        'description' => 'If true, agent only picks other agents for work (cannot do its own tasks)',
        'value_type' => 'boolean',
        'default' => FALSE,
      ],
      [
        'config_id' => 'triageAgent',
        'name' => 'Triage Agent',
        'description' => 'If true, agent can pick other agents AND do its own work',
        'value_type' => 'boolean',
        'default' => FALSE,
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
