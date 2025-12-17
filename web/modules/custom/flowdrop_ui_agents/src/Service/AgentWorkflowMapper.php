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
   * Layout constants for node positioning.
   */
  protected const LAYOUT = [
    'agentX' => 100,           // Main agent starts at left
    'agentY' => 150,           // Vertical center-ish
    'toolOffsetX' => 350,      // Tools are 350px to the right of their agent
    'toolSpacingY' => 120,     // Vertical spacing between tools
    'subAgentOffsetX' => 700,  // Sub-agents further right
    'nodeWidth' => 250,        // Approximate node width for calculations
  ];

  /**
   * Converts an AI Agent entity to FlowDrop workflow format.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $agent
   *   The AI Agent entity.
   * @param string $expansionMode
   *   How to display sub-agents: 'expanded', 'grouped', or 'collapsed'.
   * @param int $depth
   *   Current recursion depth (internal use).
   * @param int $maxDepth
   *   Maximum recursion depth to prevent infinite loops.
   *
   * @return array
   *   FlowDrop workflow data structure.
   */
  public function agentToWorkflow(ConfigEntityInterface $agent, string $expansionMode = 'expanded', int $depth = 0, int $maxDepth = 3): array {
    assert($agent instanceof AiAgent);

    $nodes = [];
    $edges = [];
    $savedPositions = $this->loadPositions($agent);

    // Calculate base X position based on depth (main agent at left, sub-agents to right)
    $baseX = self::LAYOUT['agentX'] + ($depth * self::LAYOUT['subAgentOffsetX']);
    $baseY = self::LAYOUT['agentY'];

    // Create the main agent node.
    $agentNodeId = 'agent_' . $agent->id();
    $agentNode = $this->createAgentNode($agent, $agentNodeId);
    $agentNode['data']['metadata']['ownerAgentId'] = $agent->id();

    // Use saved position or calculate default
    $agentNode['position'] = $savedPositions[$agentNodeId] ?? ['x' => $baseX, 'y' => $baseY];
    $nodes[] = $agentNode;

    // Get tools and create tool nodes.
    $tools = $agent->get('tools') ?? [];
    $toolSettings = $agent->get('tool_settings') ?? [];

    // Separate regular tools from sub-agents for positioning
    $regularTools = [];
    $subAgents = [];

    foreach ($tools as $toolId => $enabled) {
      if (!$enabled) {
        continue;
      }
      if (str_starts_with($toolId, 'ai_agents::ai_agent::')) {
        $subAgents[] = $toolId;
      }
      else {
        $regularTools[] = $toolId;
      }
    }

    // Position regular tools to the right of the agent, stacked vertically
    $toolX = $baseX + self::LAYOUT['toolOffsetX'];
    $toolY = $baseY - ((count($regularTools) - 1) * self::LAYOUT['toolSpacingY'] / 2);

    $toolIndex = 0;
    foreach ($regularTools as $toolId) {
      $toolNodeId = 'tool_' . $agent->id() . '_' . $toolIndex;
      $toolPosition = $savedPositions[$toolNodeId] ?? ['x' => $toolX, 'y' => $toolY];

      $toolNode = $this->createToolNode($toolId, $toolNodeId, $toolSettings[$toolId] ?? [], $toolPosition, $toolIndex);

      if ($toolNode) {
        $toolNode['data']['metadata']['ownerAgentId'] = $agent->id();
        $nodes[] = $toolNode;

        // Create edge from agent to tool
        $edges[] = [
          'id' => "edge_{$agentNodeId}_to_{$toolNodeId}",
          'source' => $agentNodeId,
          'target' => $toolNodeId,
          'sourceHandle' => "{$agentNodeId}-output-tools",
          'targetHandle' => "{$toolNodeId}-input-tool",
          'type' => 'default',
          'data' => ['dataType' => 'tool'],
        ];
      }

      $toolY += self::LAYOUT['toolSpacingY'];
      $toolIndex++;
    }

    // Position sub-agents further to the right
    $subAgentIndex = 0;
    foreach ($subAgents as $toolId) {
      $subAgentId = str_replace('ai_agents::ai_agent::', '', $toolId);

      if ($expansionMode === 'collapsed' || $depth >= $maxDepth) {
        // Collapsed mode: show as single node with badge
        $subAgentNodeId = 'subagent_' . $subAgentId;
        $subAgentY = $baseY + ($subAgentIndex * self::LAYOUT['toolSpacingY']);
        $subAgentPosition = $savedPositions[$subAgentNodeId] ?? [
          'x' => $baseX + self::LAYOUT['subAgentOffsetX'],
          'y' => $subAgentY,
        ];

        $collapsedNode = $this->createCollapsedAgentNode($subAgentId, $subAgentNodeId, $subAgentPosition, $subAgentIndex);
        if ($collapsedNode) {
          $collapsedNode['data']['metadata']['ownerAgentId'] = $agent->id();
          $nodes[] = $collapsedNode;

          // Create edge from parent agent to collapsed sub-agent
          $edges[] = [
            'id' => "edge_{$agentNodeId}_to_{$subAgentNodeId}",
            'source' => $agentNodeId,
            'target' => $subAgentNodeId,
            'sourceHandle' => "{$agentNodeId}-output-tools",
            'targetHandle' => "{$subAgentNodeId}-input-tool",
            'type' => 'default',
            'data' => ['dataType' => 'agent'],
          ];
        }
      }
      elseif ($expansionMode === 'grouped') {
        // Grouped mode: show sub-agent in a visual container
        $subAgentData = $this->loadSubAgentGrouped($subAgentId, $agentNodeId, $depth + 1, $maxDepth, $savedPositions);
        $nodes = array_merge($nodes, $subAgentData['nodes']);
        $edges = array_merge($edges, $subAgentData['edges']);
      }
      else {
        // Expanded mode: recursively load sub-agent
        $subAgentData = $this->loadSubAgentExpanded($subAgentId, $agentNodeId, $expansionMode, $depth + 1, $maxDepth, $savedPositions);
        $nodes = array_merge($nodes, $subAgentData['nodes']);
        $edges = array_merge($edges, $subAgentData['edges']);
      }

      $subAgentIndex++;
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
        'expansionMode' => $expansionMode,
      ],
    ];
  }

  /**
   * Loads a sub-agent in expanded mode (flat hierarchy).
   */
  protected function loadSubAgentExpanded(string $subAgentId, string $parentNodeId, string $expansionMode, int $depth, int $maxDepth, array $positions): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $subAgent = $storage->load($subAgentId);

    if (!$subAgent) {
      return ['nodes' => [], 'edges' => []];
    }

    // Recursively get sub-agent workflow
    $subWorkflow = $this->agentToWorkflow($subAgent, $expansionMode, $depth, $maxDepth);

    // Create edge from parent to sub-agent
    $subAgentNodeId = 'agent_' . $subAgentId;
    $edges = $subWorkflow['edges'];
    $edges[] = [
      'id' => "edge_{$parentNodeId}_to_{$subAgentNodeId}",
      'source' => $parentNodeId,
      'target' => $subAgentNodeId,
      'sourceHandle' => "{$parentNodeId}-output-tools",
      'targetHandle' => "{$subAgentNodeId}-input-trigger",
      'type' => 'default',
      'data' => ['dataType' => 'agent'],
    ];

    return [
      'nodes' => $subWorkflow['nodes'],
      'edges' => $edges,
    ];
  }

  /**
   * Loads a sub-agent in grouped mode (visual container).
   */
  protected function loadSubAgentGrouped(string $subAgentId, string $parentNodeId, int $depth, int $maxDepth, array $positions): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $subAgent = $storage->load($subAgentId);

    if (!$subAgent) {
      return ['nodes' => [], 'edges' => []];
    }

    // Get sub-agent workflow in expanded mode first
    $subWorkflow = $this->agentToWorkflow($subAgent, 'grouped', $depth, $maxDepth);

    // Calculate group position based on depth
    $groupX = self::LAYOUT['agentX'] + ($depth * self::LAYOUT['subAgentOffsetX']);
    $groupY = self::LAYOUT['agentY'] - 50;  // Slightly above to account for padding

    // Create group container node
    $groupNodeId = 'group_' . $subAgentId;
    $groupNode = [
      'id' => $groupNodeId,
      'type' => 'group',
      'position' => $positions[$groupNodeId] ?? ['x' => $groupX, 'y' => $groupY],
      'data' => [
        'label' => $subAgent->label(),
      ],
      'style' => [
        'width' => 600,
        'height' => 350,
      ],
    ];

    // Add parentId to all sub-agent nodes for visual grouping
    foreach ($subWorkflow['nodes'] as &$node) {
      $node['parentId'] = $groupNodeId;
      $node['extent'] = 'parent';
    }

    // Prepend group node
    array_unshift($subWorkflow['nodes'], $groupNode);

    // Create edge from parent to group
    $edges = $subWorkflow['edges'];
    $edges[] = [
      'id' => "edge_{$parentNodeId}_to_{$groupNodeId}",
      'source' => $parentNodeId,
      'target' => $groupNodeId,
      'sourceHandle' => "{$parentNodeId}-output-tools",
      'targetHandle' => "{$groupNodeId}-input-trigger",
      'type' => 'default',
      'data' => ['dataType' => 'agent'],
    ];

    return [
      'nodes' => $subWorkflow['nodes'],
      'edges' => $edges,
    ];
  }

  /**
   * Creates a collapsed agent node (single node with badge).
   */
  protected function createCollapsedAgentNode(string $subAgentId, string $nodeId, ?array $position, int $index): ?array {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $subAgent = $storage->load($subAgentId);

    if (!$subAgent) {
      return NULL;
    }

    // Count tools in sub-agent
    $tools = $subAgent->get('tools') ?? [];
    $toolCount = count(array_filter($tools));

    // Calculate default position if not provided
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
        'label' => $subAgent->label() . " [{$toolCount} tools]",
        'nodeType' => 'agent-collapsed',
        'config' => [
          'agent_id' => $subAgentId,
          'tool_count' => $toolCount,
        ],
        'metadata' => [
          'id' => 'ai_agents::ai_agent::' . $subAgentId,
          'name' => $subAgent->label(),
          'description' => $subAgent->get('description') ?? '',
          'isCollapsedAgent' => TRUE,
          'type' => 'agent',
          'supportedTypes' => ['agent', 'simple', 'default'],
          'icon' => 'mdi:robot-outline',
          'color' => 'var(--color-ref-purple-300)',
          'inputs' => [
            [
              'id' => 'tool',
              'name' => 'Tool',
              'type' => 'input',
              'dataType' => 'tool',
              'required' => FALSE,
              'description' => 'Tool connection from parent agent',
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
        ],
      ],
    ];
  }

  /**
   * Creates a FlowDrop node for the main agent.
   * Note: Position is set by the caller, not here.
   */
  protected function createAgentNode(AiAgent $agent, string $nodeId): array {
    return [
      'id' => $nodeId,
      'type' => 'universalNode',
      'position' => ['x' => 0, 'y' => 0],  // Will be overridden by caller
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
          'supportedTypes' => ['agent', 'simple', 'default'],
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
            'supportedTypes' => ['tool', 'simple', 'default'],
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
