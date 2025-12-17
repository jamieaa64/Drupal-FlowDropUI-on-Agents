<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Service;

use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\Entity\AiAgent;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
          'category' => 'agents',
          'color' => '#3b82f6',
          'inputs' => [
            [
              'id' => 'tools',
              'name' => 'Tools',
              'type' => 'input',
              'dataType' => 'tool',
              'description' => 'Tools available to this agent',
            ],
          ],
          'outputs' => [
            [
              'id' => 'response',
              'name' => 'Response',
              'type' => 'output',
              'dataType' => 'string',
              'description' => 'Agent response output',
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

      // Calculate default position if not provided.
      if ($position === NULL) {
        $position = [
          'x' => 100,
          'y' => 100 + ($index * 120),
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
            'toolId' => $toolId,
            'label' => $label,
            'returnDirectly' => $settings['return_directly'] ?? FALSE,
            'requireUsage' => $settings['require_usage'] ?? FALSE,
            'descriptionOverride' => $settings['description_override'] ?? '',
          ],
          'metadata' => [
            'id' => $toolId,
            'name' => $label,
            'description' => $description,
            'category' => 'tools',
            'color' => '#f59e0b',
            'inputs' => [],
            'outputs' => [
              [
                'id' => 'capability',
                'name' => 'Capability',
                'type' => 'output',
                'dataType' => 'tool',
                'description' => 'Tool capability for agent',
              ],
            ],
            'configSchema' => $this->getToolConfigSchema(),
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
      $name = $definition['label'] ?? $definition['name'] ?? $id;
      $description = $definition['description'] ?? '';

      $tools[] = [
        'id' => $id,
        'name' => (string) $name,
        'description' => (string) $description,
        'category' => $this->getToolCategory($id),
        'type' => 'tool',
        'color' => '#f59e0b',
        'metadata' => [
          'pluginId' => $id,
          'nodeType' => 'tool',
        ],
      ];
    }

    // Sort by name.
    usort($tools, fn($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

    return $tools;
  }

  /**
   * Determines the category for a tool based on its ID.
   */
  protected function getToolCategory(string $toolId): string {
    // Extract category from tool ID prefix.
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
    return 'General';
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
   */
  protected function getToolConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'returnDirectly' => [
          'type' => 'boolean',
          'title' => 'Return Directly',
          'description' => 'Return tool result directly without LLM processing',
          'default' => FALSE,
        ],
        'requireUsage' => [
          'type' => 'boolean',
          'title' => 'Require Usage',
          'description' => 'Agent must use this tool at least once',
          'default' => FALSE,
        ],
        'descriptionOverride' => [
          'type' => 'string',
          'title' => 'Description Override',
          'description' => 'Custom description for this tool (overrides default)',
        ],
      ],
    ];
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
