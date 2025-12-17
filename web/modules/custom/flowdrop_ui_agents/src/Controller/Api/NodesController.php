<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\flowdrop_ui_agents\Service\AgentWorkflowMapper;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for FlowDrop node types (tools and agents).
 *
 * Provides endpoints for the FlowDrop sidebar to fetch available node types.
 */
class NodesController extends ControllerBase {

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
   * Gets all available nodes (tools and agents).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with all available nodes.
   */
  public function getNodes(): JsonResponse {
    $owner = $this->getModelOwner();

    // Get tools and agents.
    $tools = $this->agentWorkflowMapper->getAvailableTools($owner);
    $agents = $this->agentWorkflowMapper->getAvailableAgents($owner);

    // Merge all nodes.
    $nodes = array_merge($tools, $agents);

    return new JsonResponse([
      'success' => TRUE,
      'data' => $nodes,
      'count' => count($nodes),
      'message' => sprintf('Found %d nodes', count($nodes)),
    ]);
  }

  /**
   * Gets nodes grouped by category.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with nodes grouped by category.
   */
  public function getNodesByCategory(): JsonResponse {
    $owner = $this->getModelOwner();

    // Get tools grouped by category.
    $toolsByCategory = $this->agentWorkflowMapper->getToolsByCategory($owner);

    // Add agents as their own category.
    $agents = $this->agentWorkflowMapper->getAvailableAgents($owner);
    if (!empty($agents)) {
      $toolsByCategory['agents'] = $agents;
    }

    // Get list of categories.
    $categories = array_keys($toolsByCategory);
    sort($categories);

    return new JsonResponse([
      'success' => TRUE,
      'data' => $toolsByCategory,
      'categories' => $categories,
      'message' => sprintf('Found %d categories', count($categories)),
    ]);
  }

  /**
   * Gets nodes for a specific category.
   *
   * @param string $category
   *   The category name.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with nodes in the category.
   */
  public function getNodesForCategory(string $category): JsonResponse {
    $owner = $this->getModelOwner();

    // Get all categorized tools.
    $toolsByCategory = $this->agentWorkflowMapper->getToolsByCategory($owner);

    // Handle agents category specially.
    if ($category === 'agents') {
      $nodes = $this->agentWorkflowMapper->getAvailableAgents($owner);
    }
    else {
      $nodes = $toolsByCategory[$category] ?? [];
    }

    return new JsonResponse([
      'success' => TRUE,
      'data' => $nodes,
      'count' => count($nodes),
      'category' => $category,
    ]);
  }

  /**
   * Gets metadata for a specific node.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with node metadata.
   */
  public function getNodeMetadata(string $plugin_id): JsonResponse {
    $owner = $this->getModelOwner();

    // Search in tools first.
    $tools = $this->agentWorkflowMapper->getAvailableTools($owner);
    foreach ($tools as $tool) {
      if ($tool['id'] === $plugin_id || $tool['tool_id'] === $plugin_id) {
        return new JsonResponse([
          'success' => TRUE,
          'data' => $tool,
        ]);
      }
    }

    // Search in agents.
    $agents = $this->agentWorkflowMapper->getAvailableAgents($owner);
    foreach ($agents as $agent) {
      if ($agent['id'] === $plugin_id || ($agent['agent_id'] ?? '') === $plugin_id) {
        return new JsonResponse([
          'success' => TRUE,
          'data' => $agent,
        ]);
      }
    }

    return new JsonResponse([
      'success' => FALSE,
      'error' => sprintf('Node "%s" not found', $plugin_id),
    ], 404);
  }

  /**
   * Gets port configuration for the editor.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with port configuration.
   */
  public function getPortConfiguration(): JsonResponse {
    // Define data types that can connect to each other.
    return new JsonResponse([
      'success' => TRUE,
      'data' => [
        'dataTypes' => [
          'trigger' => [
            'name' => 'Trigger',
            'color' => '#ef4444',
            'canConnectTo' => ['trigger'],
          ],
          'string' => [
            'name' => 'String',
            'color' => '#3b82f6',
            'canConnectTo' => ['string', 'mixed', 'any'],
          ],
          'number' => [
            'name' => 'Number',
            'color' => '#22c55e',
            'canConnectTo' => ['number', 'mixed', 'any'],
          ],
          'boolean' => [
            'name' => 'Boolean',
            'color' => '#f59e0b',
            'canConnectTo' => ['boolean', 'mixed', 'any'],
          ],
          'tool' => [
            'name' => 'Tool',
            'color' => '#f97316',
            'canConnectTo' => ['tool'],
          ],
          'agent' => [
            'name' => 'Agent',
            'color' => '#8b5cf6',
            'canConnectTo' => ['agent', 'tool'],
          ],
          'mixed' => [
            'name' => 'Mixed',
            'color' => '#6b7280',
            'canConnectTo' => ['string', 'number', 'boolean', 'mixed', 'any'],
          ],
          'any' => [
            'name' => 'Any',
            'color' => '#6b7280',
            'canConnectTo' => ['string', 'number', 'boolean', 'mixed', 'any', 'tool', 'agent'],
          ],
        ],
      ],
    ]);
  }

  /**
   * Gets a dummy model owner for the API calls.
   *
   * The AgentWorkflowMapper methods need a ModelOwnerInterface, but for
   * the API we don't have a specific entity context. We create a minimal
   * implementation that satisfies the interface.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   *   A model owner instance.
   */
  protected function getModelOwner(): ModelOwnerInterface {
    // Get the ai_agents_agent model owner plugin.
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerManager $manager */
    $manager = \Drupal::service('plugin.manager.modeler_api.model_owner');
    return $manager->createInstance('ai_agents_agent');
  }

}
