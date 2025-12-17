<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\flowdrop_ui_agents\Service\AgentWorkflowMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for FlowDrop workflow operations.
 *
 * Provides endpoints for loading workflows with different expansion modes.
 */
class WorkflowController extends ControllerBase {

  /**
   * The agent workflow mapper service.
   */
  protected AgentWorkflowMapper $agentWorkflowMapper;

  /**
   * Constructs the controller.
   */
  public function __construct(AgentWorkflowMapper $agentWorkflowMapper) {
    $this->agentWorkflowMapper = $agentWorkflowMapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_ui_agents.agent_workflow_mapper')
    );
  }

  /**
   * Gets workflow data for an agent with specified expansion mode.
   *
   * @param string $agent_id
   *   The AI Agent ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with workflow data.
   */
  public function getWorkflow(string $agent_id, Request $request): JsonResponse {
    $expansion = $request->query->get('expansion', 'expanded');

    // Validate expansion mode.
    $validModes = ['expanded', 'grouped', 'collapsed'];
    if (!in_array($expansion, $validModes, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => sprintf('Invalid expansion mode "%s". Valid modes: %s', $expansion, implode(', ', $validModes)),
      ], 400);
    }

    // Load the agent.
    $storage = $this->entityTypeManager()->getStorage('ai_agent');
    $agent = $storage->load($agent_id);

    if (!$agent) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => sprintf('Agent "%s" not found', $agent_id),
      ], 404);
    }

    try {
      // Convert agent to workflow with expansion mode.
      $workflow = $this->agentWorkflowMapper->agentToWorkflow($agent, $expansion);

      return new JsonResponse([
        'success' => TRUE,
        'data' => $workflow,
        'expansion' => $expansion,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to generate workflow: ' . $e->getMessage(),
      ], 500);
    }
  }

}
