<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Services;

use Drupal\ai_agents\Entity\AiAgent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\flowdrop_ai_provider\Exception\MappingException;
use Drupal\flowdrop_ai_provider\Exception\ValidationException;
use Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel;
use Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModelDefinition;
use Drupal\flowdrop_workflow\DTO\WorkflowDTO;
use Drupal\flowdrop_workflow\DTO\WorkflowEdgeDTO;
use Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Maps between FlowDrop workflows and AI Agent configurations.
 *
 * This service handles bidirectional conversion:
 * - Save: FlowDrop workflow → AI Agent config entities
 * - Load: AI Agent config entities → FlowDrop workflow
 *
 * @see \Drupal\ai_integration_eca_agents\Services\ModelMapper\ModelMapper
 */
class FlowDropAgentMapper implements FlowDropAgentMapperInterface {

  /**
   * The third-party settings key for FlowDrop metadata.
   */
  const THIRD_PARTY_KEY = 'flowdrop_ai_provider';

  /**
   * Node types that represent AI Agents.
   */
  const AGENT_NODE_TYPES = [
    'chat_model',
    'simple_agent',
    'ai_agent',
  ];

  /**
   * Node types that represent tools.
   */
  const TOOL_NODE_TYPES = [
    'tool',
    'http_request',
    'function_call',
  ];

  /**
   * Constructs a FlowDropAgentMapper.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager.
   * @param \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer
   *   The normalizer.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TypedDataManagerInterface $typedDataManager,
    protected NormalizerInterface $normalizer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function workflowToModel(WorkflowDTO $workflow): FlowDropAgentModel {
    // Build the model data array from workflow.
    $modelData = $this->buildModelDataFromWorkflow($workflow);

    // Create and validate the typed data model.
    return $this->fromPayload($modelData);
  }

  /**
   * {@inheritdoc}
   */
  public function workflowToAgentConfigs(WorkflowDTO $workflow): array {
    $configs = [];

    // Find all agent nodes in the workflow.
    $agentNodes = $this->findAgentNodes($workflow);

    if (empty($agentNodes)) {
      throw new MappingException('No agent nodes found in workflow');
    }

    // Convert each agent node to config.
    foreach ($agentNodes as $node) {
      $agentNodeId = $node->getId();
      $connectedTools = $this->extractToolsForAgent($workflow, $agentNodeId);

      $config = $this->nodeToAgentConfig($node, $connectedTools, $workflow);
      $agentId = $config['id'];
      $configs[$agentId] = $config;
    }

    // Handle multi-agent orchestration.
    if (count($agentNodes) > 1) {
      $configs = $this->configureOrchestration($configs, $workflow);
    }

    return $configs;
  }

  /**
   * {@inheritdoc}
   */
  public function nodeToAgentConfig(WorkflowNodeDTO $node, array $connectedTools, WorkflowDTO $workflow): array {
    $nodeConfig = $node->getConfig();
    $nodeId = $node->getId();

    // Generate agent ID from workflow ID and node ID.
    $agentId = $this->generateAgentId($workflow->getId(), $nodeId);

    // Build the tools array format expected by AI Agent entity.
    // Format: ['tool:http_request' => TRUE, 'tool:calculator' => TRUE]
    $tools = [];
    foreach ($connectedTools as $toolId) {
      $tools[$toolId] = TRUE;
    }

    // Build basic agent config.
    $config = [
      'id' => $agentId,
      'label' => $node->getLabel() ?: $workflow->getName(),
      'description' => $nodeConfig['description'] ?? $workflow->getDescription(),
      'system_prompt' => $nodeConfig['systemPrompt'] ?? $nodeConfig['system_prompt'] ?? '',
      'tools' => $tools,
      'orchestration_agent' => FALSE,
      'triage_agent' => FALSE,
      'max_loops' => (int) ($nodeConfig['maxLoops'] ?? $nodeConfig['max_loops'] ?? 3),
    ];

    // Add secured system prompt if present.
    if (!empty($nodeConfig['securedSystemPrompt'])) {
      $config['secured_system_prompt'] = $nodeConfig['securedSystemPrompt'];
    }

    // Add tool settings if present.
    if (!empty($nodeConfig['toolSettings'])) {
      $config['tool_settings'] = $nodeConfig['toolSettings'];
    }

    // Add tool usage limits if present.
    if (!empty($nodeConfig['toolUsageLimits'])) {
      $config['tool_usage_limits'] = $nodeConfig['toolUsageLimits'];
    }

    // Add default information tools if present.
    if (!empty($nodeConfig['defaultInformationTools'])) {
      $config['default_information_tools'] = $nodeConfig['defaultInformationTools'];
    }

    // Handle structured output settings.
    if (!empty($nodeConfig['structuredOutput'])) {
      $config['structured_output_enabled'] = TRUE;
      $config['structured_output_schema'] = is_array($nodeConfig['structuredOutput'])
        ? json_encode($nodeConfig['structuredOutput'])
        : $nodeConfig['structuredOutput'];
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function extractToolsForAgent(WorkflowDTO $workflow, string $agentNodeId): array {
    $tools = [];

    // Find tool nodes that are connected to this agent node.
    // Tools can be connected via:
    // 1. Direct edges from agent to tool nodes
    // 2. Tool nodes with parentNode set to this agent.
    foreach ($workflow->getNodes() as $node) {
      if (!$this->isToolNode($node)) {
        continue;
      }

      // Check if tool is connected to this agent via edges.
      $isConnected = FALSE;
      foreach ($workflow->getIncomingEdges($node->getId()) as $edge) {
        if ($edge->getSource() === $agentNodeId) {
          $isConnected = TRUE;
          break;
        }
      }

      // Also check parentNode metadata (for visually attached tools).
      $parentNode = $node->getMetadataValue('parentNode');
      if ($parentNode === $agentNodeId) {
        $isConnected = TRUE;
      }

      if ($isConnected) {
        $toolId = $this->extractToolId($node);
        if ($toolId) {
          $tools[] = $toolId;
        }
      }
    }

    return array_unique($tools);
  }

  /**
   * {@inheritdoc}
   */
  public function agentConfigsToWorkflow(array $agentIds): WorkflowDTO {
    $nodes = [];
    $edges = [];
    $positions = [];

    // Load all agents.
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $agents = $storage->loadMultiple($agentIds);

    if (empty($agents)) {
      throw new MappingException('No agents found for the given IDs');
    }

    $yOffset = 0;
    $agentNodeIdMap = [];

    foreach ($agents as $agentId => $agent) {
      // Load positions from third-party settings.
      $savedPositions = $this->loadPositions($agentId);

      // Convert agent to node.
      $agentNode = $this->agentConfigToNode($agent);
      $nodeId = $agentNode->getId();
      $agentNodeIdMap[$agentId] = $nodeId;

      // Apply saved position or calculate default.
      if (isset($savedPositions[$nodeId])) {
        $position = $savedPositions[$nodeId];
      }
      else {
        $position = ['x' => 300, 'y' => 100 + $yOffset];
        $yOffset += 200;
      }

      // Get raw data (frontend format) and update position.
      $nodeData = $agentNode->getRawData();
      $nodeData['position'] = $position;
      $nodes[$nodeId] = WorkflowNodeDTO::fromArray($nodeData);

      // Convert tools to tool nodes.
      $enabledTools = array_keys(array_filter($agent->get('tools') ?? []));
      $toolNodes = $this->toolsToToolNodes($enabledTools, $nodeId, $savedPositions);

      foreach ($toolNodes as $toolNode) {
        $nodes[$toolNode->getId()] = $toolNode;
      }

      // Merge saved positions.
      $positions = array_merge($positions, $savedPositions);
    }

    // Build edges between agents if there's orchestration.
    $edges = $this->buildAgentEdges($agents, $agentNodeIdMap);

    // Create workflow DTO.
    $firstAgentId = reset($agentIds);
    $firstAgent = $agents[$firstAgentId] ?? NULL;

    return WorkflowDTO::fromArray([
      'id' => $firstAgentId,
      'name' => $firstAgent ? $firstAgent->label() : 'AI Agent Workflow',
      'description' => $firstAgent ? $firstAgent->get('description') : '',
      'nodes' => array_map(fn($node) => $node->getRawData(), $nodes),
      'edges' => array_map(fn($edge) => $edge->getRawData(), $edges),
      'metadata' => [
        'agent_ids' => $agentIds,
        'positions' => $positions,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function agentConfigToNode(AiAgent $agent): WorkflowNodeDTO {
    $agentId = $agent->id();

    // Build node in FlowDrop frontend format.
    $nodeData = [
      'id' => 'agent_' . $agentId,
      'type' => 'universalNode',
      'data' => [
        'label' => $agent->label(),
        'config' => [
          'description' => $agent->get('description'),
          'systemPrompt' => $agent->get('system_prompt'),
          'securedSystemPrompt' => $agent->get('secured_system_prompt'),
          'maxLoops' => $agent->get('max_loops'),
          'orchestrationAgent' => $agent->get('orchestration_agent'),
          'triageAgent' => $agent->get('triage_agent'),
          'toolSettings' => $agent->get('tool_settings'),
          'toolUsageLimits' => $agent->get('tool_usage_limits'),
          'defaultInformationTools' => $agent->get('default_information_tools'),
          'structuredOutput' => $agent->get('structured_output_enabled')
            ? $agent->get('structured_output_schema')
            : NULL,
        ],
        'metadata' => [
          'id' => 'ai_agent',
          'name' => $agent->label(),
          'executor_plugin' => 'chat_model',
          'category' => 'models',
          'agent_id' => $agentId,
          'type' => 'agent',
          'supportedTypes' => ['agent'],
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
        ],
      ],
      'position' => ['x' => 300, 'y' => 100],
    ];

    return WorkflowNodeDTO::fromArray($nodeData);
  }

  /**
   * {@inheritdoc}
   */
  public function toolsToToolNodes(array $tools, string $parentAgentNodeId, array $positions = []): array {
    $nodes = [];
    $toolYOffset = 0;

    foreach ($tools as $toolId) {
      $nodeId = 'tool_' . str_replace([':', '.'], '_', $toolId);

      // Determine position.
      if (isset($positions[$nodeId])) {
        $position = $positions[$nodeId];
      }
      else {
        // Position tools below and to the right of agent node.
        $position = [
          'x' => 500,
          'y' => 150 + $toolYOffset,
        ];
        $toolYOffset += 80;
      }

      // Parse tool ID to get label.
      $toolParts = explode(':', $toolId);
      $toolLabel = end($toolParts);
      $toolLabel = ucwords(str_replace(['_', '-'], ' ', $toolLabel));

      // Build node in FlowDrop frontend format.
      $nodeData = [
        'id' => $nodeId,
        'type' => 'universalNode',
        'data' => [
          'label' => $toolLabel,
          'config' => [
            'tool_id' => $toolId,
          ],
          'metadata' => [
            'id' => $toolId,
            'name' => $toolLabel,
            'executor_plugin' => 'tool',
            'category' => 'tools',
            'tool_id' => $toolId,
            'parentNode' => $parentAgentNodeId,
            'type' => 'tool',
            'supportedTypes' => ['tool'],
            'icon' => 'mdi:tools',
            'color' => 'var(--color-ref-orange-500)',
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
                'description' => 'Tool result',
              ],
            ],
          ],
        ],
        'position' => $position,
      ];

      $nodes[$nodeId] = WorkflowNodeDTO::fromArray($nodeData);
    }

    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  public function storePositions(string $agentId, array $positions): void {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    /** @var \Drupal\ai_agents\Entity\AiAgent|null $agent */
    $agent = $storage->load($agentId);

    if (!$agent) {
      return;
    }

    $agent->setThirdPartySetting(self::THIRD_PARTY_KEY, 'positions', $positions);
    $agent->save();
  }

  /**
   * {@inheritdoc}
   */
  public function loadPositions(string $agentId): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    /** @var \Drupal\ai_agents\Entity\AiAgent|null $agent */
    $agent = $storage->load($agentId);

    if (!$agent) {
      return [];
    }

    return $agent->getThirdPartySetting(self::THIRD_PARTY_KEY, 'positions', []);
  }

  /**
   * {@inheritdoc}
   */
  public function validateWorkflowMapping(WorkflowDTO $workflow): array {
    $errors = [];

    // Check for at least one agent node.
    $agentNodes = $this->findAgentNodes($workflow);
    if (empty($agentNodes)) {
      $errors[] = 'Workflow must contain at least one agent node';
    }

    // Validate each agent node.
    foreach ($agentNodes as $node) {
      $config = $node->getConfig();

      // Check for required system prompt.
      if (empty($config['systemPrompt']) && empty($config['system_prompt'])) {
        $errors[] = sprintf('Agent node "%s" is missing system prompt', $node->getLabel());
      }
    }

    // Check workflow has a name.
    if (empty($workflow->getName())) {
      $errors[] = 'Workflow must have a name';
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fromPayload(array $payload): FlowDropAgentModel {
    $definition = FlowDropAgentModelDefinition::create('flowdrop_agent_model');

    /** @var \Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel $model */
    $model = $this->typedDataManager->create($definition, $payload);

    // Validate the model.
    $violations = $model->validate();
    if (count($violations) > 0) {
      throw ValidationException::fromViolations($violations);
    }

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function fromEntity(AiAgent $entity): FlowDropAgentModel {
    $data = [
      'model_id' => $entity->id(),
      'label' => $entity->label(),
      'description' => $entity->get('description') ?? '',
      'system_prompt' => $entity->get('system_prompt') ?? '',
      'orchestration_agent' => $entity->get('orchestration_agent') ?? FALSE,
      'triage_agent' => $entity->get('triage_agent') ?? FALSE,
      'max_loops' => $entity->get('max_loops') ?? 3,
      'tools' => [],
      'agents' => [],
      'edges' => [],
      'ui_metadata' => $entity->getThirdPartySetting(self::THIRD_PARTY_KEY, 'positions', []),
    ];

    // Convert tools array to list format.
    $tools = $entity->get('tools') ?? [];
    foreach (array_keys(array_filter($tools)) as $toolId) {
      $data['tools'][] = [
        'tool_id' => $toolId,
        'enabled' => TRUE,
      ];
    }

    $definition = FlowDropAgentModelDefinition::create('flowdrop_agent_model');

    /** @var \Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel $model */
    $model = $this->typedDataManager->create($definition, $data);

    return $model;
  }

  /**
   * Builds model data array from a workflow DTO.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow.
   *
   * @return array
   *   The model data array.
   */
  protected function buildModelDataFromWorkflow(WorkflowDTO $workflow): array {
    $agentNodes = $this->findAgentNodes($workflow);
    $firstAgent = reset($agentNodes);

    // Build agents array.
    $agents = [];
    foreach ($agentNodes as $node) {
      $agents[] = [
        'node_id' => $node->getId(),
        'label' => $node->getLabel(),
        'config' => $node->getConfig(),
      ];
    }

    // Build tools array.
    $tools = [];
    foreach ($workflow->getNodes() as $node) {
      if ($this->isToolNode($node)) {
        $tools[] = [
          'node_id' => $node->getId(),
          'tool_id' => $this->extractToolId($node),
          'parent_agent' => $node->getMetadataValue('parentNode'),
        ];
      }
    }

    // Build edges array.
    $edges = [];
    foreach ($workflow->getEdges() as $edge) {
      $edges[] = [
        'source' => $edge->getSource(),
        'target' => $edge->getTarget(),
        'source_handle' => $edge->getSourceHandle(),
        'target_handle' => $edge->getTargetHandle(),
      ];
    }

    // Build UI metadata.
    $positions = [];
    foreach ($workflow->getNodes() as $node) {
      $position = $node->getPosition();
      if ($position) {
        $positions[$node->getId()] = $position;
      }
    }

    return [
      'model_id' => $workflow->getId() ?: $this->generateModelId($workflow),
      'label' => $workflow->getName(),
      'description' => $workflow->getDescription(),
      'system_prompt' => $firstAgent ? ($firstAgent->getConfigValue('systemPrompt') ?? $firstAgent->getConfigValue('system_prompt') ?? '') : '',
      'agents' => $agents,
      'tools' => $tools,
      'edges' => $edges,
      'orchestration_agent' => count($agentNodes) > 1,
      'triage_agent' => FALSE,
      'max_loops' => $firstAgent ? (int) ($firstAgent->getConfigValue('maxLoops') ?? 3) : 3,
      'ui_metadata' => ['positions' => $positions],
    ];
  }

  /**
   * Finds all agent nodes in a workflow.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow.
   *
   * @return array<\Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO>
   *   Array of agent nodes.
   */
  protected function findAgentNodes(WorkflowDTO $workflow): array {
    $agents = [];
    foreach ($workflow->getNodes() as $node) {
      if ($this->isAgentNode($node)) {
        $agents[$node->getId()] = $node;
      }
    }
    return $agents;
  }

  /**
   * Checks if a node is an agent node.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO $node
   *   The node.
   *
   * @return bool
   *   TRUE if agent node.
   */
  protected function isAgentNode(WorkflowNodeDTO $node): bool {
    $typeId = $node->getTypeId();
    $category = $node->getCategory();

    // Check by type ID.
    if (in_array($typeId, self::AGENT_NODE_TYPES, TRUE)) {
      return TRUE;
    }

    // Check by category.
    if ($category === 'models' || $category === 'agents') {
      return TRUE;
    }

    // Check metadata.
    $metadata = $node->getMetadata();
    if (isset($metadata['is_agent']) && $metadata['is_agent']) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if a node is a tool node.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO $node
   *   The node.
   *
   * @return bool
   *   TRUE if tool node.
   */
  protected function isToolNode(WorkflowNodeDTO $node): bool {
    $typeId = $node->getTypeId();
    $category = $node->getCategory();

    // Check by type ID.
    if (in_array($typeId, self::TOOL_NODE_TYPES, TRUE)) {
      return TRUE;
    }

    // Check by category.
    if ($category === 'tools') {
      return TRUE;
    }

    // Check if it has a tool_id in config.
    $config = $node->getConfig();
    if (!empty($config['tool_id'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Extracts the tool ID from a tool node.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO $node
   *   The tool node.
   *
   * @return string|null
   *   The tool ID or NULL.
   */
  protected function extractToolId(WorkflowNodeDTO $node): ?string {
    $config = $node->getConfig();
    $metadata = $node->getMetadata();

    // Check config first.
    if (!empty($config['tool_id'])) {
      return $config['tool_id'];
    }

    // Check metadata.
    if (!empty($metadata['tool_id'])) {
      return $metadata['tool_id'];
    }

    // Use type ID as fallback with 'tool:' prefix.
    $typeId = $node->getTypeId();
    if ($typeId && !str_contains($typeId, ':')) {
      return 'tool:' . $typeId;
    }

    return $typeId ?: NULL;
  }

  /**
   * Generates an agent ID from workflow and node IDs.
   *
   * @param string $workflowId
   *   The workflow ID.
   * @param string $nodeId
   *   The node ID.
   *
   * @return string
   *   The generated agent ID.
   */
  protected function generateAgentId(string $workflowId, string $nodeId): string {
    // Clean and combine IDs.
    $workflowId = preg_replace('/[^a-z0-9_]/', '_', strtolower($workflowId));
    $nodeId = preg_replace('/[^a-z0-9_]/', '_', strtolower($nodeId));

    // Limit length.
    $combined = $workflowId . '_' . $nodeId;
    if (strlen($combined) > 64) {
      $combined = substr($workflowId, 0, 30) . '_' . substr($nodeId, 0, 30);
    }

    return $combined;
  }

  /**
   * Generates a model ID from workflow data.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow.
   *
   * @return string
   *   The generated model ID.
   */
  protected function generateModelId(WorkflowDTO $workflow): string {
    $name = $workflow->getName();
    if ($name) {
      return preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
    }
    return 'flowdrop_agent_' . substr(md5(serialize($workflow->getRawData())), 0, 8);
  }

  /**
   * Configures orchestration for multi-agent workflows.
   *
   * @param array $configs
   *   Array of agent configs.
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow.
   *
   * @return array
   *   Updated configs with orchestration settings.
   */
  protected function configureOrchestration(array $configs, WorkflowDTO $workflow): array {
    // Find the "root" agent (one with incoming edges from inputs).
    $rootNodes = $workflow->getRootNodes();
    $agentNodes = $this->findAgentNodes($workflow);

    $rootAgentNodeId = NULL;
    foreach ($rootNodes as $rootNode) {
      // Check if this root connects to an agent.
      foreach ($workflow->getOutgoingEdges($rootNode->getId()) as $edge) {
        if (isset($agentNodes[$edge->getTarget()])) {
          $rootAgentNodeId = $edge->getTarget();
          break 2;
        }
      }
    }

    // If we found a root agent, make it an orchestration agent.
    if ($rootAgentNodeId) {
      $rootAgentConfig = NULL;
      foreach ($configs as $agentId => &$config) {
        // Find the config that corresponds to the root node.
        if (str_contains($agentId, preg_replace('/[^a-z0-9_]/', '_', strtolower($rootAgentNodeId)))) {
          $config['orchestration_agent'] = TRUE;

          // Add other agents as tools (via ai_agents::ai_agent:: format).
          foreach ($configs as $subAgentId => $subConfig) {
            if ($subAgentId !== $agentId) {
              $config['tools']['ai_agents::ai_agent::' . $subAgentId] = TRUE;
            }
          }
          break;
        }
      }
    }

    return $configs;
  }

  /**
   * Builds edges between agent nodes based on orchestration.
   *
   * @param array $agents
   *   Array of AI Agent entities.
   * @param array $agentNodeIdMap
   *   Map of agent ID to node ID.
   *
   * @return array<\Drupal\flowdrop_workflow\DTO\WorkflowEdgeDTO>
   *   Array of edge DTOs.
   */
  protected function buildAgentEdges(array $agents, array $agentNodeIdMap): array {
    $edges = [];

    foreach ($agents as $agentId => $agent) {
      // Check if this agent uses other agents as tools.
      $tools = $agent->get('tools') ?? [];
      foreach (array_keys(array_filter($tools)) as $toolId) {
        // Check if it's an agent reference.
        if (str_starts_with($toolId, 'ai_agents::ai_agent::')) {
          $subAgentId = str_replace('ai_agents::ai_agent::', '', $toolId);
          if (isset($agentNodeIdMap[$subAgentId])) {
            $sourceNodeId = $agentNodeIdMap[$agentId];
            $targetNodeId = $agentNodeIdMap[$subAgentId];

            $edgeData = [
              'id' => 'edge_' . $agentId . '_to_' . $subAgentId,
              'source' => $sourceNodeId,
              'target' => $targetNodeId,
              'sourceHandle' => $sourceNodeId . '-output-agent',
              'targetHandle' => $targetNodeId . '-input-trigger',
            ];

            $edges[] = WorkflowEdgeDTO::fromArray($edgeData);
          }
        }
      }
    }

    return $edges;
  }

}
