<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Controller\Api;

use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for saving AI Assistants via FlowDrop.
 *
 * This mimics the behavior of AiAssistantForm::submitForm() to ensure
 * both the Assistant and its linked Agent are properly updated.
 */
class AssistantSaveController extends ControllerBase {

  /**
   * Saves an AI Assistant and its linked Agent from FlowDrop workflow data.
   *
   * @param string $assistant_id
   *   The AI Assistant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing workflow JSON.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function save(string $assistant_id, Request $request): JsonResponse {
    try {
      // Load the assistant.
      $assistant = $this->entityTypeManager()->getStorage('ai_assistant')->load($assistant_id);
      if (!$assistant) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Assistant not found: ' . $assistant_id,
        ], 404);
      }

      // Get the linked agent.
      $agentId = $assistant->get('ai_agent');
      if (!$agentId) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Assistant has no linked agent',
        ], 400);
      }

      $agent = $this->entityTypeManager()->getStorage('ai_agent')->load($agentId);
      if (!$agent) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Linked agent not found: ' . $agentId,
        ], 404);
      }

      // Parse the workflow data from request body.
      $content = $request->getContent();
      $workflowData = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON: ' . json_last_error_msg(),
        ], 400);
      }

      // Extract data from workflow.
      $metadata = $workflowData['metadata'] ?? [];
      $agentConfig = $metadata['agentConfig'] ?? [];
      $nodes = $workflowData['nodes'] ?? [];
      $edges = $workflowData['edges'] ?? [];

      // Find the main assistant node and extract its config.
      // The assistant config is stored in the node's data.config, not in metadata.
      $assistantNodeConfig = $this->findAssistantNodeConfig($nodes, $agentId);

      // Update the Agent (similar to AiAssistantForm::submitForm).
      $this->updateAgent($agent, $agentConfig, $assistantNodeConfig, $nodes, $edges);

      // Update the Assistant from the node config.
      $this->updateAssistant($assistant, $assistantNodeConfig);

      // Save both entities.
      $agent->save();
      $assistant->save();

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Assistant and agent saved successfully',
        'assistant_id' => $assistant->id(),
        'agent_id' => $agent->id(),
      ]);

    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Save failed: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Finds the main assistant node and extracts its config.
   */
  protected function findAssistantNodeConfig(array $nodes, string $agentId): array {
    $mainNodeId = 'agent_' . $agentId;

    foreach ($nodes as $node) {
      $nodeType = $node['data']['nodeType'] ?? '';
      $nodeId = $node['id'] ?? '';

      // Find the main assistant/agent node.
      if ($nodeId === $mainNodeId || $nodeType === 'assistant') {
        return $node['data']['config'] ?? [];
      }
    }

    return [];
  }

  /**
   * Updates the Agent entity from workflow data.
   *
   * This mirrors the logic in AiAssistantForm::submitForm() lines 537-604.
   */
  protected function updateAgent($agent, array $agentConfig, array $assistantNodeConfig, array $nodes, array $edges): void {
    // Update agent fields from agentConfig metadata.
    if (!empty($agentConfig['description'])) {
      $agent->set('description', $agentConfig['description']);
    }
    if (!empty($agentConfig['system_prompt'])) {
      $agent->set('system_prompt', $agentConfig['system_prompt']);
    }

    // Also check assistantNodeConfig for these fields (they may come from node panel).
    if (!empty($assistantNodeConfig['description'])) {
      $agent->set('description', $assistantNodeConfig['description']);
    }
    if (!empty($assistantNodeConfig['systemPrompt'])) {
      $agent->set('system_prompt', $assistantNodeConfig['systemPrompt']);
    }
    if (isset($assistantNodeConfig['maxLoops'])) {
      $agent->set('max_loops', (int) $assistantNodeConfig['maxLoops']);
    }
    elseif (isset($agentConfig['max_loops'])) {
      $agent->set('max_loops', (int) $agentConfig['max_loops']);
    }

    // Build a map of nodes by ID for quick lookup.
    $nodesById = [];
    foreach ($nodes as $node) {
      $nodesById[$node['id']] = $node;
    }

    // Find the main assistant/agent node ID.
    $mainNodeId = 'agent_' . $agent->id();

    // Find direct children of the main node using edges.
    // Only nodes directly connected FROM the main assistant node are its tools.
    $directChildNodeIds = [];
    foreach ($edges as $edge) {
      if ($edge['source'] === $mainNodeId) {
        $directChildNodeIds[] = $edge['target'];
      }
    }

    // Extract tools only from direct children of the main assistant node.
    $tools = [];
    $toolUsageLimits = $agent->get('tool_usage_limits') ?? [];

    foreach ($directChildNodeIds as $childNodeId) {
      if (!isset($nodesById[$childNodeId])) {
        continue;
      }

      $node = $nodesById[$childNodeId];
      $nodeType = $node['data']['nodeType'] ?? '';
      $nodeMetadata = $node['data']['metadata'] ?? [];
      $nodeConfig = $node['data']['config'] ?? [];

      // Handle sub-agent nodes (expanded or collapsed).
      // In expanded mode, sub-agents have nodeType 'agent' but ownerAgentId differs.
      // In collapsed mode, they have nodeType 'agent-collapsed'.
      if ($nodeType === 'agent' || $nodeType === 'agent-collapsed') {
        // Get the agent ID from various possible locations.
        $subAgentId = $nodeConfig['agent_id']
          ?? $nodeMetadata['ownerAgentId']
          ?? NULL;

        // For expanded agents, extract ID from node ID (agent_xxx).
        if (!$subAgentId && str_starts_with($childNodeId, 'agent_')) {
          $subAgentId = substr($childNodeId, 6);
        }
        // Or from subagent_ prefix.
        if (!$subAgentId && str_starts_with($childNodeId, 'subagent_')) {
          $subAgentId = substr($childNodeId, 9);
        }

        if ($subAgentId && $subAgentId !== $agent->id()) {
          $toolId = 'ai_agents::ai_agent::' . $subAgentId;
          $tools[$toolId] = TRUE;
        }
      }

      // Handle tool nodes (RAG).
      if ($nodeType === 'tool') {
        $toolId = $node['data']['toolId'] ?? $nodeConfig['tool_id'] ?? '';
        if ($toolId) {
          $tools[$toolId] = TRUE;

          // Handle RAG-specific settings.
          if ($toolId === 'ai_search:rag_search') {
            $toolUsageLimits['ai_search:rag_search'] = [
              'index' => [
                'values' => [$nodeConfig['index'] ?? ''],
                'action' => 'force_value',
                'hide_property' => 1,
              ],
              'amount' => [
                'values' => [$nodeConfig['amount'] ?? 5],
                'action' => 'force_value',
                'hide_property' => 1,
              ],
              'min_score' => [
                'values' => [$nodeConfig['min_score'] ?? 0.5],
                'action' => 'force_value',
                'hide_property' => 1,
              ],
            ];
          }
        }
      }
    }

    $agent->set('tools', $tools);
    $agent->set('tool_usage_limits', $toolUsageLimits);

    // Assistants always have orchestration_agent = TRUE.
    $agent->set('orchestration_agent', TRUE);
  }

  /**
   * Updates the Assistant entity from node config.
   *
   * Note: The node config uses camelCase keys (from JS), while the entity
   * uses snake_case. This method handles the mapping.
   */
  protected function updateAssistant(AiAssistant $assistant, array $nodeConfig): void {
    // Update assistant-specific fields if provided.
    // Map camelCase (from node config) to snake_case (entity fields).
    if (isset($nodeConfig['label'])) {
      $assistant->set('label', $nodeConfig['label']);
    }
    if (isset($nodeConfig['description'])) {
      $assistant->set('description', $nodeConfig['description']);
    }
    if (isset($nodeConfig['instructions'])) {
      $assistant->set('instructions', $nodeConfig['instructions']);
    }

    // History settings (camelCase from node).
    if (isset($nodeConfig['allowHistory'])) {
      $assistant->set('allow_history', $nodeConfig['allowHistory']);
    }
    if (isset($nodeConfig['historyContextLength'])) {
      $assistant->set('history_context_length', $nodeConfig['historyContextLength']);
    }

    // LLM settings (camelCase from node).
    if (isset($nodeConfig['llmProvider'])) {
      $assistant->set('llm_provider', $nodeConfig['llmProvider']);
    }
    if (isset($nodeConfig['llmModel'])) {
      $assistant->set('llm_model', $nodeConfig['llmModel']);
    }
    if (isset($nodeConfig['llmConfiguration'])) {
      $assistant->set('llm_configuration', $nodeConfig['llmConfiguration']);
    }

    // Other fields.
    if (isset($nodeConfig['errorMessage'])) {
      $assistant->set('error_message', $nodeConfig['errorMessage']);
    }
    if (isset($nodeConfig['roles'])) {
      $assistant->set('roles', $nodeConfig['roles']);
    }

    // Clear old action-based fields (assistants now use agents).
    $assistant->set('pre_action_prompt', '');
    $assistant->set('system_prompt', '');
  }

}
