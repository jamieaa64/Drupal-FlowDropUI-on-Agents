<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Service to parse FlowDrop workflow JSON and convert to Modeler API Components.
 *
 * This handles the SAVE direction: FlowDrop workflow -> Modeler API Components.
 */
class WorkflowParser {

  /**
   * The parsed workflow data.
   */
  protected array $data = [];

  /**
   * The model owner for creating components.
   */
  protected ?ModelOwnerInterface $owner = NULL;

  /**
   * Constructs the WorkflowParser service.
   */
  public function __construct(
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Parses FlowDrop workflow JSON string.
   *
   * @param string $json
   *   The JSON string from FlowDrop.
   *
   * @return array
   *   Parsed workflow data.
   */
  public function parse(string $json): array {
    if (empty($json)) {
      return $this->getEmptyWorkflow();
    }

    $data = json_decode($json, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->loggerFactory->get('flowdrop_ui_agents')->error(
        'Failed to parse FlowDrop JSON: @error',
        ['@error' => json_last_error_msg()]
      );
      return $this->getEmptyWorkflow();
    }

    $this->data = $data;
    return $data;
  }

  /**
   * Sets the model owner for component creation.
   */
  public function setOwner(ModelOwnerInterface $owner): void {
    $this->owner = $owner;
  }

  /**
   * Converts parsed workflow data to Modeler API Components.
   *
   * @param array $data
   *   The parsed workflow data.
   *
   * @return \Drupal\modeler_api\Component[]
   *   Array of Modeler API Component objects.
   */
  public function toComponents(array $data): array {
    if ($this->owner === NULL) {
      $this->loggerFactory->get('flowdrop_ui_agents')->error(
        'Cannot create components without model owner'
      );
      return [];
    }

    $components = [];
    $nodes = $data['nodes'] ?? [];
    $edges = $data['edges'] ?? [];

    // The primary agent ID is the workflow's ID - this is the entity being edited.
    $primaryAgentId = $data['id'] ?? '';

    // Build edge map for finding successors.
    $edgeMap = $this->buildEdgeMap($edges);

    // Track which tools belong to the primary agent.
    $primaryAgentTools = [];

    foreach ($nodes as $node) {
      $nodeType = $node['data']['nodeType'] ?? 'unknown';
      $nodeMetadata = $node['data']['metadata'] ?? [];
      $ownerAgentId = $nodeMetadata['ownerAgentId'] ?? '';

      switch ($nodeType) {
        case 'agent':
          // Only create a START component for the PRIMARY agent.
          // Sub-agents (expanded from the primary) should be treated as
          // SUBPROCESS references, not modified.
          if ($ownerAgentId === $primaryAgentId || $ownerAgentId === '') {
            // This is the primary agent being edited.
            $component = $this->createAgentComponent($node, $edgeMap, $data);
            if ($component) {
              $components[] = $component;
            }
          }
          else {
            // This is an expanded sub-agent - create a SUBPROCESS reference.
            // Don't modify it, just reference it as a tool.
            $primaryAgentTools[] = 'ai_agents::ai_agent::' . $ownerAgentId;
          }
          break;

        case 'agent-collapsed':
          // Collapsed agents are sub-agent references.
          $subAgentId = $node['data']['config']['agent_id'] ?? $nodeMetadata['id'] ?? '';
          if ($subAgentId && str_starts_with($subAgentId, 'ai_agents::ai_agent::')) {
            $primaryAgentTools[] = $subAgentId;
          }
          elseif ($subAgentId) {
            $primaryAgentTools[] = 'ai_agents::ai_agent::' . $subAgentId;
          }
          break;

        case 'tool':
          // Only add tools that belong to the primary agent.
          if ($ownerAgentId === $primaryAgentId || $ownerAgentId === '') {
            $component = $this->createToolComponent($node, $edgeMap);
            if ($component) {
              $components[] = $component;
            }
          }
          break;
      }
    }

    // Add link components for edges.
    foreach ($edges as $edge) {
      $component = $this->createLinkComponent($edge);
      if ($component) {
        $components[] = $component;
      }
    }

    // Store sub-agent references for later processing.
    $this->data['_subAgentTools'] = $primaryAgentTools;

    return $components;
  }

  /**
   * Creates an agent component from a FlowDrop node.
   *
   * The configuration keys must match what AI Agents ModelOwner expects:
   * - agent_id: The agent machine name
   * - label: Human-readable name
   * - description: Description for triage agents
   * - system_prompt: Main instructions
   * - max_loops: Max iterations (integer)
   * - orchestration_agent: Boolean
   * - triage_agent: Boolean
   */
  protected function createAgentComponent(array $node, array $edgeMap, array $workflowData): ?Component {
    $nodeId = $node['id'];
    $config = $node['data']['config'] ?? [];
    $nodeMetadata = $node['data']['metadata'] ?? [];
    $workflowMetadata = $workflowData['metadata']['agentConfig'] ?? [];

    // Get the agent ID from the node's ownerAgentId (set during load).
    // This is critical for multi-agent workflows where sub-agents are expanded.
    // Each agent node must use its OWN ID, not the workflow's top-level ID.
    $agentId = $nodeMetadata['ownerAgentId']
      ?? $config['agent_id']
      ?? $workflowData['id']
      ?? $nodeId;

    // Build agent configuration matching AiAgentForm::defaultConfigMetadata('agent_id').
    $agentConfig = [
      'agent_id' => $agentId,
      'label' => $config['label'] ?? $workflowData['label'] ?? $workflowData['name'] ?? 'Agent',
      'description' => $config['description'] ?? $workflowMetadata['description'] ?? '',
      'system_prompt' => $config['systemPrompt'] ?? $workflowMetadata['system_prompt'] ?? '',
      'max_loops' => (int) ($config['maxLoops'] ?? $workflowMetadata['max_loops'] ?? 3),
      'orchestration_agent' => (bool) ($config['orchestrationAgent'] ?? $workflowMetadata['orchestration_agent'] ?? FALSE),
      'triage_agent' => (bool) ($config['triageAgent'] ?? $workflowMetadata['triage_agent'] ?? FALSE),
      // Required by AI Agents but we don't expose in UI yet.
      'secured_system_prompt' => '[ai_agent:agent_instructions]',
      'default_information_tools' => '',
      'structured_output_enabled' => FALSE,
      'structured_output_schema' => '',
    ];

    // Find connected tools (successors).
    $successors = [];
    $incomingEdges = $edgeMap['incoming'][$nodeId] ?? [];
    foreach ($incomingEdges as $edge) {
      $successors[] = new ComponentSuccessor($edge['source'], '');
    }

    return new Component(
      $this->owner,
      $nodeId,
      Api::COMPONENT_TYPE_START,
      $agentId,  // Plugin ID should be the agent ID.
      $agentConfig['label'],
      $agentConfig,
      $successors,
    );
  }

  /**
   * Creates a tool component from a FlowDrop node.
   */
  protected function createToolComponent(array $node, array $edgeMap): ?Component {
    $nodeId = $node['id'];
    $config = $node['data']['config'] ?? [];
    $toolId = $node['data']['toolId'] ?? $config['toolId'] ?? '';

    if (empty($toolId)) {
      return NULL;
    }

    // Build tool configuration.
    $toolConfig = [
      'return_directly' => $config['returnDirectly'] ?? FALSE,
    ];

    // Find successors (what this tool connects to).
    $successors = [];
    $outgoingEdges = $edgeMap['outgoing'][$nodeId] ?? [];
    foreach ($outgoingEdges as $edge) {
      $successors[] = new ComponentSuccessor($edge['target'], '');
    }

    return new Component(
      $this->owner,
      $nodeId,
      Api::COMPONENT_TYPE_ELEMENT,
      $toolId,
      $config['label'] ?? $toolId,
      $toolConfig,
      $successors,
    );
  }

  /**
   * Creates a link component from a FlowDrop edge.
   */
  protected function createLinkComponent(array $edge): ?Component {
    return new Component(
      $this->owner,
      $edge['id'],
      Api::COMPONENT_TYPE_LINK,
      '',
      '',
      [
        'source' => $edge['source'],
        'target' => $edge['target'],
      ],
      [],
    );
  }

  /**
   * Builds a map of edges for quick lookup.
   */
  protected function buildEdgeMap(array $edges): array {
    $map = [
      'incoming' => [],
      'outgoing' => [],
    ];

    foreach ($edges as $edge) {
      $source = $edge['source'];
      $target = $edge['target'];

      $map['outgoing'][$source][] = $edge;
      $map['incoming'][$target][] = $edge;
    }

    return $map;
  }

  /**
   * Returns empty workflow structure.
   */
  protected function getEmptyWorkflow(): array {
    return [
      'id' => '',
      'label' => '',
      'nodes' => [],
      'edges' => [],
      'metadata' => [],
    ];
  }

}
