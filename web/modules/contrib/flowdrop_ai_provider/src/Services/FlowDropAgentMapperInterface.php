<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Services;

use Drupal\ai_agents\Entity\AiAgent;
use Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel;
use Drupal\flowdrop_workflow\DTO\WorkflowDTO;
use Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO;

/**
 * Interface for mapping between FlowDrop workflows and AI Agent configs.
 *
 * This service provides bidirectional transformation between:
 * - FlowDrop workflow data structures (nodes, edges, positions)
 * - AI Agent configuration entities
 *
 * Key responsibilities:
 * - Save: Convert FlowDrop canvas → AI Agent configs
 * - Load: Convert AI Agent configs → FlowDrop canvas
 * - Position storage: Preserve UI metadata in agent third-party settings
 *
 * @see \Drupal\ai_integration_eca_agents\Services\ModelMapper\ModelMapper
 */
interface FlowDropAgentMapperInterface {

  /**
   * Converts a FlowDrop workflow to AI Agent configuration data.
   *
   * This is the main entry point for the "Save" operation.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The FlowDrop workflow to convert.
   *
   * @return \Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel
   *   The typed data model representing the agent configuration.
   *
   * @throws \Drupal\flowdrop_ai_provider\Exception\MappingException
   *   If the workflow cannot be mapped to agent configuration.
   */
  public function workflowToModel(WorkflowDTO $workflow): FlowDropAgentModel;

  /**
   * Converts a FlowDrop workflow to multiple AI Agent config arrays.
   *
   * For multi-agent workflows, each agent node becomes a separate
   * AI Agent config entity.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The FlowDrop workflow to convert.
   *
   * @return array
   *   Array of AI Agent config arrays, keyed by agent ID.
   */
  public function workflowToAgentConfigs(WorkflowDTO $workflow): array;

  /**
   * Converts a single FlowDrop agent node to AI Agent config.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO $node
   *   The FlowDrop node representing an agent.
   * @param array $connectedTools
   *   Array of tool node IDs connected to this agent.
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The full workflow for context (edges, other nodes).
   *
   * @return array
   *   AI Agent config array suitable for entity creation.
   */
  public function nodeToAgentConfig(WorkflowNodeDTO $node, array $connectedTools, WorkflowDTO $workflow): array;

  /**
   * Extracts tool IDs from tool nodes attached to an agent.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The FlowDrop workflow.
   * @param string $agentNodeId
   *   The ID of the agent node.
   *
   * @return array
   *   Array of tool IDs (e.g., ['ai_agent:http_request', 'ai_agent:calculator']).
   */
  public function extractToolsForAgent(WorkflowDTO $workflow, string $agentNodeId): array;

  /**
   * Converts AI Agent configs back to a FlowDrop workflow.
   *
   * This is the main entry point for the "Load" operation.
   *
   * @param array $agentIds
   *   Array of AI Agent IDs to load.
   *
   * @return \Drupal\flowdrop_workflow\DTO\WorkflowDTO
   *   The FlowDrop workflow DTO.
   */
  public function agentConfigsToWorkflow(array $agentIds): WorkflowDTO;

  /**
   * Converts a single AI Agent config to a FlowDrop node.
   *
   * @param \Drupal\ai_agents\Entity\AiAgent $agent
   *   The AI Agent entity.
   *
   * @return \Drupal\flowdrop_workflow\DTO\WorkflowNodeDTO
   *   The FlowDrop node DTO.
   */
  public function agentConfigToNode(AiAgent $agent): WorkflowNodeDTO;

  /**
   * Converts agent tools to FlowDrop tool nodes.
   *
   * @param array $tools
   *   Array of tool IDs from agent config.
   * @param string $parentAgentNodeId
   *   The ID of the parent agent node.
   * @param array $positions
   *   Optional array of saved positions for tools.
   *
   * @return array
   *   Array of WorkflowNodeDTO objects for tool nodes.
   */
  public function toolsToToolNodes(array $tools, string $parentAgentNodeId, array $positions = []): array;

  /**
   * Stores UI positions for an agent in third-party settings.
   *
   * @param string $agentId
   *   The AI Agent ID.
   * @param array $positions
   *   Array of positions keyed by node ID: ['node_id' => ['x' => 100, 'y' => 200]].
   */
  public function storePositions(string $agentId, array $positions): void;

  /**
   * Loads UI positions for an agent from third-party settings.
   *
   * @param string $agentId
   *   The AI Agent ID.
   *
   * @return array
   *   Array of positions keyed by node ID.
   */
  public function loadPositions(string $agentId): array;

  /**
   * Validates that a workflow can be mapped to agent config.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow to validate.
   *
   * @return array
   *   Array of validation errors, empty if valid.
   */
  public function validateWorkflowMapping(WorkflowDTO $workflow): array;

  /**
   * Creates TypedData model from raw payload array.
   *
   * Similar to ECA's ModelMapper::fromPayload().
   *
   * @param array $payload
   *   Raw array data to convert.
   *
   * @return \Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel
   *   The typed data model.
   *
   * @throws \Drupal\flowdrop_ai_provider\Exception\ValidationException
   *   If the payload fails validation.
   */
  public function fromPayload(array $payload): FlowDropAgentModel;

  /**
   * Converts an AI Agent entity to TypedData model.
   *
   * Similar to ECA's ModelMapper::fromEntity().
   *
   * @param \Drupal\ai_agents\Entity\AiAgent $entity
   *   The AI Agent entity.
   *
   * @return \Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel
   *   The typed data model.
   */
  public function fromEntity(AiAgent $entity): FlowDropAgentModel;

}
