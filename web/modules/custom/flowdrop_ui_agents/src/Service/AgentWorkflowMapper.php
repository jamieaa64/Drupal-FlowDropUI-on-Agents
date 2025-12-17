<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Service;

use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\Entity\AiAgent;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Service to map between AI Agent entities and FlowDrop workflow format.
 *
 * This service provides bidirectional conversion:
 * - LOAD: AI Agent config entity -> FlowDrop workflow (nodes/edges)
 * - SAVE: FlowDrop workflow -> AI Agent config updates
 */
class AgentWorkflowMapper {

  /**
   * Category to plural form mapping.
   */
  protected const CATEGORY_PLURAL_MAP = [
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

  /**
   * Category color mapping.
   */
  protected const CATEGORY_COLORS = [
    'trigger' => 'var(--color-ref-red-500)',
    'input' => 'var(--color-ref-blue-500)',
    'output' => 'var(--color-ref-green-500)',
    'model' => 'var(--color-ref-purple-500)',
    'tool' => 'var(--color-ref-orange-500)',
    'agent' => 'var(--color-ref-cyan-500)',
    'General' => 'var(--color-ref-gray-500)',
    'Search' => 'var(--color-ref-blue-500)',
    'Content' => 'var(--color-ref-green-500)',
    'User' => 'var(--color-ref-purple-500)',
  ];

  /**
   * Constructs the AgentWorkflowMapper service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Converts an AI Agent entity to FlowDrop workflow format.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $agent
   *   The AI Agent entity.
   *
   * @return array
   *   FlowDrop workflow data structure.
   */
  public function agentToWorkflow(ConfigEntityInterface $agent): array {
    assert($agent instanceof AiAgent);

    $nodes = [];
    $edges = [];

    // Create the main agent node.
    $agentNodeId = 'agent_' . $agent->id();
    $nodes[] = $this->createAgentNode($agent, $agentNodeId);

    // Get tools and create tool nodes.
    $tools = $agent->get('tools') ?? [];
    $toolSettings = $agent->get('tool_settings') ?? [];
    $positions = $this->loadPositions($agent);

    $toolIndex = 0;
    foreach ($tools as $toolId => $enabled) {
      if (!$enabled) {
        continue;
      }

      $toolNodeId = 'tool_' . $toolIndex;
      $toolNode = $this->createToolNode($toolId, $toolNodeId, $toolSettings[$toolId] ?? [], $positions[$toolNodeId] ?? NULL, $toolIndex);

      if ($toolNode) {
        $nodes[] = $toolNode;

        // Create edge from tool to agent (tool provides capability to agent).
        $edges[] = [
          'id' => "edge_{$toolNodeId}_to_{$agentNodeId}",
          'source' => $toolNodeId,
          'target' => $agentNodeId,
          'sourceHandle' => "{$toolNodeId}-output-capability",
          'targetHandle' => "{$agentNodeId}-input-tools",
          'type' => 'default',
          'data' => [
            'dataType' => 'tool',
          ],
        ];
      }

      $toolIndex++;
    }

    // Load positions for agent node if saved.
    if (isset($positions[$agentNodeId])) {
      $nodes[0]['position'] = $positions[$agentNodeId];
    }

    return [
      'id' => $agent->id(),
      'label' => $agent->label(),
      'nodes' => $nodes,
      'edges' => $edges,
      'metadata' => [
        'agentConfig' => [
          'system_prompt' => $agent->get('system_prompt') ?? '',
          'description' => $agent->get('description') ?? '',
          'max_loops' => $agent->get('max_loops') ?? 3,
          'orchestration_agent' => $agent->get('orchestration_agent') ?? FALSE,
          'triage_agent' => $agent->get('triage_agent') ?? FALSE,
        ],
      ],
    ];
  }

  /**
   * Creates a FlowDrop node for the main agent.
   */
  protected function createAgentNode(AiAgent $agent, string $nodeId): array {
    return [
      'id' => $nodeId,
      'type' => 'universalNode',
      'position' => ['x' => 400, 'y' => 100],
      'data' => [
        'nodeId' => $nodeId,
        'label' => $agent->label(),
        'nodeType' => 'agent',
        'config' => [
          'label' => $agent->label(),
          'description' => $agent->get('description') ?? '',
          'systemPrompt' => $agent->get('system_prompt') ?? '',
          'maxLoops' => $agent->get('max_loops') ?? 3,
          'orchestrationAgent' => $agent->get('orchestration_agent') ?? FALSE,
          'triageAgent' => $agent->get('triage_agent') ?? FALSE,
        ],
        'metadata' => [
          'id' => 'ai_agent',
          'name' => 'AI Agent',
          'type' => 'agent',
          'supportedTypes' => ['agent'],
          'category' => 'agents',
          'icon' => 'mdi:robot',
          'color' => 'var(--color-ref-purple-500)',
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
            [
              'id' => 'tools',
              'name' => 'Tools',
              'type' => 'input',
              'dataType' => 'tool',
              'required' => FALSE,
              'description' => 'Tools available to this agent',
            ],
          ],
          'outputs' => [
            [
              'id' => 'response',
              'name' => 'Response',
              'type' => 'output',
              'dataType' => 'string',
              'required' => FALSE,
              'description' => 'Agent response output',
            ],
            [
              'id' => 'tools',
              'name' => 'Tools',
              'type' => 'output',
              'dataType' => 'tool',
              'required' => FALSE,
              'description' => 'Tools output for chaining',
            ],
          ],
          'configSchema' => $this->getAgentConfigSchema(),
        ],
      ],
    ];
  }

  /**
   * Creates a FlowDrop node for a tool.
   */
  protected function createToolNode(string $toolId, string $nodeId, array $settings, ?array $position, int $index): ?array {
    try {
      $plugin = $this->functionCallPluginManager->createInstance($toolId);
      $definition = $plugin->getPluginDefinition();

      // Cast to string to handle TranslatableMarkup objects.
      $label = (string) ($definition['label'] ?? $definition['name'] ?? $toolId);
      $description = (string) ($definition['description'] ?? '');
      $category = $this->getToolCategory($toolId);

      // Calculate default position if not provided.
      if ($position === NULL) {
        $position = [
          'x' => 100,
          'y' => 100 + ($index * 100),
        ];
      }

      return [
        'id' => $nodeId,
        'type' => 'universalNode',
        'position' => $position,
        'data' => [
          'nodeId' => $nodeId,
          'label' => $label,
          'nodeType' => 'tool',
          'toolId' => $toolId,
          'config' => [
            'tool_id' => $toolId,
            'label' => $label,
            'return_directly' => $settings['return_directly'] ?? FALSE,
            'require_usage' => $settings['require_usage'] ?? FALSE,
            'use_artifacts' => $settings['use_artifacts'] ?? FALSE,
            'description_override' => $settings['description_override'] ?? '',
            'progress_message' => $settings['progress_message'] ?? '',
          ],
          'metadata' => [
            'id' => $toolId,
            'name' => $label,
            'description' => $description,
            'type' => 'tool',
            'supportedTypes' => ['tool'],
            'category' => $this->transformCategoryToPlural($category),
            'icon' => 'mdi:tools',
            'color' => $this->getCategoryColor($category),
            'tool_id' => $toolId,
            'inputs' => [
              [
                'id' => 'tool',
                'name' => 'Tool',
                'type' => 'input',
                'dataType' => 'tool',
                'required' => FALSE,
                'description' => 'Tool connection from agent',
              ],
            ],
            'outputs' => [
              [
                'id' => 'tool',
                'name' => 'Tool',
                'type' => 'output',
                'dataType' => 'tool',
                'required' => FALSE,
                'description' => 'Tool output',
              ],
            ],
            'configSchema' => $this->getToolConfigSchema($toolId),
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('flowdrop_ui_agents')->warning(
        'Could not create tool node for @tool: @error',
        ['@tool' => $toolId, '@error' => $e->getMessage()]
      );
      return NULL;
    }
  }

  /**
   * Gets available tools for the sidebar.
   *
   * Returns tools in FlowDrop node format so they can be dragged directly
   * onto the canvas.
   */
  public function getAvailableTools(ModelOwnerInterface $owner): array {
    $tools = [];

    // Get all function call plugins (tools).
    $definitions = $this->functionCallPluginManager->getDefinitions();

    foreach ($definitions as $id => $definition) {
      // Skip agent wrappers (ai_agents::ai_agent::*).
      if (str_starts_with($id, 'ai_agents::ai_agent::')) {
        continue;
      }

      // Cast to string to handle TranslatableMarkup objects.
      $label = (string) ($definition['label'] ?? $definition['name'] ?? $id);
      $description = (string) ($definition['description'] ?? '');
      $category = $this->getToolCategory($id);

      // Get tool-specific config schema.
      $configSchema = $this->getToolSettingsSchema($id);

      $tools[] = [
        'id' => $id,
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
        'executor_plugin' => 'tool:' . $id,
        'tool_id' => $id,
        'inputs' => [
          [
            'id' => 'tool',
            'name' => 'Tool',
            'type' => 'input',
            'dataType' => 'tool',
            'required' => FALSE,
            'description' => 'Tool connection from agent',
          ],
        ],
        'outputs' => [
          [
            'id' => 'tool',
            'name' => 'Tool',
            'type' => 'output',
            'dataType' => 'tool',
            'required' => FALSE,
            'description' => 'Tool output',
          ],
        ],
        'config' => [],
        'configSchema' => $configSchema,
      ];
    }

    // Sort by category then name.
    usort($tools, function ($a, $b) {
      $categoryCompare = strcmp($a['category'], $b['category']);
      return $categoryCompare !== 0 ? $categoryCompare : strcmp($a['name'], $b['name']);
    });

    return $tools;
  }

  /**
   * Gets available agents for the sidebar (other than current).
   */
  public function getAvailableAgents(ModelOwnerInterface $owner): array {
    $agents = [];
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $allAgents = $storage->loadMultiple();

    foreach ($allAgents as $agent) {
      $agentId = $agent->id();
      $label = $agent->label();
      $description = $agent->get('description') ?? '';

      $agents[] = [
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
    }

    // Sort by name.
    usort($agents, fn($a, $b) => strcmp($a['name'], $b['name']));

    return $agents;
  }

  /**
   * Gets tools grouped by category for sidebar display.
   */
  public function getToolsByCategory(ModelOwnerInterface $owner): array {
    $tools = $this->getAvailableTools($owner);
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
   * Determines the category for a tool based on its ID.
   */
  protected function getToolCategory(string $toolId): string {
    // Check for known tool prefixes first.
    if (str_starts_with($toolId, 'ai_agent:')) {
      $toolName = substr($toolId, 9);
      if (str_contains($toolName, 'content')) {
        return 'Content';
      }
      if (str_contains($toolName, 'user')) {
        return 'User';
      }
      if (str_contains($toolName, 'search')) {
        return 'Search';
      }
    }

    // Try to get category from plugin definition.
    try {
      $plugin = $this->functionCallPluginManager->createInstance($toolId);
      $definition = $plugin->getPluginDefinition();
      if (!empty($definition['category'])) {
        return (string) $definition['category'];
      }
    }
    catch (\Exception $e) {
      // Fall through to default.
    }

    return 'General';
  }

  /**
   * Transforms singular category to plural form for FlowDrop API.
   */
  protected function transformCategoryToPlural(string $category): string {
    return self::CATEGORY_PLURAL_MAP[$category]
      ?? self::CATEGORY_PLURAL_MAP[strtolower($category)]
      ?? strtolower($category) . 's';
  }

  /**
   * Gets CSS color for a category.
   */
  protected function getCategoryColor(string $category): string {
    return self::CATEGORY_COLORS[$category]
      ?? self::CATEGORY_COLORS[strtolower($category)]
      ?? 'var(--color-ref-orange-500)';
  }

  /**
   * Returns JSON Schema for agent node configuration.
   */
  protected function getAgentConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'label' => [
          'type' => 'string',
          'title' => 'Label',
          'description' => 'Human-readable name for the agent',
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Description used by triage agents to select this agent',
        ],
        'systemPrompt' => [
          'type' => 'string',
          'format' => 'textarea',
          'title' => 'System Prompt',
          'description' => 'Core instructions for agent behavior',
        ],
        'maxLoops' => [
          'type' => 'integer',
          'title' => 'Max Loops',
          'description' => 'Maximum iterations before stopping (1-100)',
          'default' => 3,
        ],
        'orchestrationAgent' => [
          'type' => 'boolean',
          'title' => 'Orchestration Agent',
          'description' => 'If true, agent only picks other agents for work',
          'default' => FALSE,
        ],
        'triageAgent' => [
          'type' => 'boolean',
          'title' => 'Triage Agent',
          'description' => 'If true, agent can pick other agents AND do its own work',
          'default' => FALSE,
        ],
      ],
      'required' => ['label', 'description', 'systemPrompt'],
    ];
  }

  /**
   * Returns JSON Schema for tool node configuration.
   *
   * @param string $toolId
   *   Optional tool ID for plugin-specific config.
   */
  protected function getToolConfigSchema(string $toolId = ''): array {
    $properties = [
      'tool_id' => [
        'type' => 'string',
        'title' => 'Tool ID',
        'description' => 'The tool plugin ID (read-only)',
        'default' => $toolId,
        'format' => 'hidden',
      ],
      'return_directly' => [
        'type' => 'boolean',
        'title' => 'Return Directly',
        'description' => 'Return tool result directly without LLM rewriting. Use for API responses or structured output.',
        'default' => FALSE,
      ],
      'require_usage' => [
        'type' => 'boolean',
        'title' => 'Require Usage',
        'description' => 'Remind the agent if it tries to output without using this tool first.',
        'default' => FALSE,
      ],
      'use_artifacts' => [
        'type' => 'boolean',
        'title' => 'Use Artifact Storage',
        'description' => 'Store large responses in artifacts instead of sending to AI. Reference with {{artifact:tool_name:index}}',
        'default' => FALSE,
      ],
      'description_override' => [
        'type' => 'string',
        'format' => 'textarea',
        'title' => 'Override Tool Description',
        'description' => 'Custom description sent to LLM instead of default. Leave empty to use default.',
        'default' => '',
      ],
      'progress_message' => [
        'type' => 'string',
        'title' => 'Progress Message',
        'description' => 'Message shown in UI while tool is executing.',
        'default' => '',
      ],
    ];

    // Add plugin-specific config if we can get it.
    if (!empty($toolId)) {
      $pluginConfig = $this->getToolPluginConfigSchema($toolId);
      foreach ($pluginConfig as $key => $schema) {
        $properties[$key] = $schema;
      }
    }

    return [
      'type' => 'object',
      'properties' => $properties,
      'required' => ['tool_id'],
    ];
  }

  /**
   * Gets config schema from a tool plugin if available.
   */
  protected function getToolPluginConfigSchema(string $toolId): array {
    try {
      $plugin = $this->functionCallPluginManager->createInstance($toolId);

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

        return $this->extractConfigSchemaFromForm($form, $defaultConfig);
      }
    }
    catch (\Exception $e) {
      // Return empty on error.
    }

    return [];
  }

  /**
   * Extracts JSON Schema from a Drupal form array.
   */
  protected function extractConfigSchemaFromForm(array $form, array $defaultConfig): array {
    $schema = [];

    foreach ($form as $key => $element) {
      // Skip non-element keys.
      if (str_starts_with($key, '#')) {
        continue;
      }

      if (!is_array($element) || !isset($element['#type'])) {
        continue;
      }

      $fieldSchema = [
        'title' => (string) ($element['#title'] ?? $key),
      ];

      if (!empty($element['#description'])) {
        $fieldSchema['description'] = (string) $element['#description'];
      }

      // Map Drupal form type to JSON Schema type.
      $type = $element['#type'];
      switch ($type) {
        case 'checkbox':
          $fieldSchema['type'] = 'boolean';
          break;

        case 'number':
          $fieldSchema['type'] = 'number';
          break;

        case 'select':
          $fieldSchema['type'] = 'string';
          if (!empty($element['#options'])) {
            $fieldSchema['enum'] = array_keys($element['#options']);
          }
          break;

        case 'textarea':
          $fieldSchema['type'] = 'string';
          $fieldSchema['format'] = 'textarea';
          break;

        default:
          $fieldSchema['type'] = 'string';
      }

      // Set default from form or plugin config.
      if (isset($element['#default_value'])) {
        $fieldSchema['default'] = $element['#default_value'];
      }
      elseif (isset($defaultConfig[$key])) {
        $fieldSchema['default'] = $defaultConfig[$key];
      }

      $schema[$key] = $fieldSchema;
    }

    return $schema;
  }

  /**
   * Gets the tool settings schema including per-tool settings.
   *
   * This is used for the sidebar tool definitions.
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
        'description' => 'Return tool result directly without LLM rewriting.',
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
        'description' => 'Store large responses in artifacts instead of sending to AI.',
        'value_type' => 'boolean',
        'default' => FALSE,
      ],
      [
        'config_id' => 'description_override',
        'name' => 'Override Tool Description',
        'description' => 'Custom description sent to LLM instead of default.',
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

    return $schema;
  }

  /**
   * Loads saved positions from agent third-party settings.
   */
  protected function loadPositions(AiAgent $agent): array {
    return $agent->getThirdPartySetting('flowdrop_ui_agents', 'positions', []);
  }

  /**
   * Saves positions to agent third-party settings.
   */
  public function savePositions(AiAgent $agent, array $positions): void {
    $agent->setThirdPartySetting('flowdrop_ui_agents', 'positions', $positions);
  }

}
