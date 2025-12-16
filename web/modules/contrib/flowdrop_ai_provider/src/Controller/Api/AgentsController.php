<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\flowdrop_ai_provider\Exception\MappingException;
use Drupal\flowdrop_ai_provider\Exception\ValidationException;
use Drupal\flowdrop_ai_provider\Services\AgentRepositoryInterface;
use Drupal\flowdrop_ai_provider\Services\FlowDropAgentMapperInterface;
use Drupal\flowdrop_workflow\DTO\WorkflowDTO;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API controller for AI Agent management via FlowDrop.
 *
 * Provides REST endpoints for:
 * - CRUD operations on AI Agents
 * - Converting FlowDrop workflows to/from AI Agent configs
 * - Position storage for UI metadata
 */
final class AgentsController extends ControllerBase {

  /**
   * The agent repository service.
   *
   * @var \Drupal\flowdrop_ai_provider\Services\AgentRepositoryInterface
   */
  protected AgentRepositoryInterface $agentRepository;

  /**
   * The FlowDrop agent mapper service.
   *
   * @var \Drupal\flowdrop_ai_provider\Services\FlowDropAgentMapperInterface
   */
  protected FlowDropAgentMapperInterface $agentMapper;

  /**
   * Constructs an AgentsController.
   *
   * @param \Drupal\flowdrop_ai_provider\Services\AgentRepositoryInterface $agentRepository
   *   The agent repository service.
   * @param \Drupal\flowdrop_ai_provider\Services\FlowDropAgentMapperInterface $agentMapper
   *   The FlowDrop agent mapper service.
   */
  public function __construct(
    AgentRepositoryInterface $agentRepository,
    FlowDropAgentMapperInterface $agentMapper,
  ) {
    $this->agentRepository = $agentRepository;
    $this->agentMapper = $agentMapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_ai_provider.agent_repository'),
      $container->get('flowdrop_ai_provider.agent_mapper'),
    );
  }

  /**
   * Get all AI Agents.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with all agents.
   */
  public function getAgents(Request $request): JsonResponse {
    try {
      $filters = [];

      // Parse filter parameters.
      if ($request->query->has('type')) {
        $filters['type'] = $request->query->get('type');
      }
      if ($request->query->has('created_by_flowdrop')) {
        $filters['created_by_flowdrop'] = filter_var(
          $request->query->get('created_by_flowdrop'),
          FILTER_VALIDATE_BOOLEAN
        );
      }

      $agents = $this->agentRepository->getAll($filters);

      $data = [];
      foreach ($agents as $agent) {
        $data[] = $this->formatAgentResponse($agent);
      }

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $data,
        'count' => count($data),
        'message' => sprintf('Found %d agents', count($data)),
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching agents: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch agents',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get a specific AI Agent.
   *
   * @param string $id
   *   The agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with agent data.
   */
  public function getAgent(string $id): JsonResponse {
    try {
      $agent = $this->agentRepository->load($id);

      if (!$agent) {
        throw new NotFoundHttpException('Agent not found');
      }

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $this->formatAgentResponse($agent, 'full'),
        'message' => 'Agent retrieved successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Agent not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching agent @id: @error',
        ['@id' => $id, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch agent',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Create a new AI Agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with created agent.
   */
  public function createAgent(Request $request): JsonResponse {
    try {
      $data = $this->parseJsonRequest($request);

      // Validate required fields.
      $this->validateAgentData($data);

      $agent = $this->agentRepository->build($data);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $this->formatAgentResponse($agent, 'full'),
        'message' => 'Agent created successfully',
      ], Response::HTTP_CREATED);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (ValidationException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Validation failed',
        'message' => $e->formatViolations(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error creating agent: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to create agent',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Update an existing AI Agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $id
   *   The agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with updated agent.
   */
  public function updateAgent(Request $request, string $id): JsonResponse {
    try {
      // Check agent exists.
      if (!$this->agentRepository->exists($id)) {
        throw new NotFoundHttpException('Agent not found');
      }

      $data = $this->parseJsonRequest($request);

      // Validate required fields.
      $this->validateAgentData($data, TRUE);

      $agent = $this->agentRepository->build($data, TRUE, $id);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $this->formatAgentResponse($agent, 'full'),
        'message' => 'Agent updated successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Agent not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (ValidationException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Validation failed',
        'message' => $e->formatViolations(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error updating agent @id: @error',
        ['@id' => $id, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to update agent',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Delete an AI Agent.
   *
   * @param string $id
   *   The agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response confirming deletion.
   */
  public function deleteAgent(string $id): JsonResponse {
    try {
      if (!$this->agentRepository->delete($id)) {
        throw new NotFoundHttpException('Agent not found');
      }

      return $this->jsonResponse([
        'success' => TRUE,
        'message' => 'Agent deleted successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Agent not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error deleting agent @id: @error',
        ['@id' => $id, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to delete agent',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Convert a FlowDrop workflow to AI Agent configs and save.
   *
   * This is the main "Save" endpoint for FlowDrop UI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing workflow JSON.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with created/updated agents.
   */
  public function saveWorkflow(Request $request): JsonResponse {
    try {
      $data = $this->parseJsonRequest($request);

      // Create WorkflowDTO from request data.
      $workflow = WorkflowDTO::fromArray($data);

      // Validate the workflow can be mapped.
      $errors = $this->agentMapper->validateWorkflowMapping($workflow);
      if (!empty($errors)) {
        throw ValidationException::fromMessages($errors);
      }

      // Convert workflow to agent configs.
      $agentConfigs = $this->agentMapper->workflowToAgentConfigs($workflow);

      // Save all agents.
      $agents = $this->agentRepository->buildMultiple($agentConfigs);

      // Format response.
      $agentData = [];
      foreach ($agents as $id => $agent) {
        $agentData[$id] = $this->formatAgentResponse($agent, 'full');
      }

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => [
          'agents' => $agentData,
          'count' => count($agents),
        ],
        'message' => sprintf('%d agent(s) saved successfully', count($agents)),
      ], Response::HTTP_CREATED);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (ValidationException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Validation failed',
        'message' => $e->formatViolations(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (MappingException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Mapping failed',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error saving workflow: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to save workflow',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Load AI Agent(s) as a FlowDrop workflow.
   *
   * This is the main "Load" endpoint for FlowDrop UI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with workflow data.
   */
  public function loadWorkflow(Request $request): JsonResponse {
    try {
      // Get agent IDs from query or body.
      $agentIds = [];

      if ($request->query->has('agents')) {
        $agentIds = explode(',', $request->query->get('agents'));
      }
      elseif ($request->getContent()) {
        $data = $this->parseJsonRequest($request);
        $agentIds = $data['agents'] ?? [];
      }

      if (empty($agentIds)) {
        throw new BadRequestHttpException('No agent IDs provided');
      }

      // Convert agents to workflow.
      $workflow = $this->agentMapper->agentConfigsToWorkflow($agentIds);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $this->workflowToArray($workflow),
        'message' => 'Workflow loaded successfully',
      ]);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error loading workflow: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to load workflow',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get agent as FlowDrop workflow format.
   *
   * @param string $id
   *   The agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with workflow data.
   */
  public function getAgentAsWorkflow(string $id): JsonResponse {
    try {
      if (!$this->agentRepository->exists($id)) {
        throw new NotFoundHttpException('Agent not found');
      }

      $workflow = $this->agentMapper->agentConfigsToWorkflow([$id]);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $this->workflowToArray($workflow),
        'message' => 'Agent loaded as workflow',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Agent not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error loading agent @id as workflow: @error',
        ['@id' => $id, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to load agent as workflow',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Validate agent data without saving.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with validation results.
   */
  public function validateAgent(Request $request): JsonResponse {
    try {
      $data = $this->parseJsonRequest($request);

      $errors = $this->agentRepository->validate($data);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => [
          'valid' => empty($errors),
          'errors' => $errors,
        ],
        'message' => empty($errors) ? 'Validation passed' : 'Validation failed',
      ]);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Validation error',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Validate a workflow mapping without saving.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with validation results.
   */
  public function validateWorkflow(Request $request): JsonResponse {
    try {
      $data = $this->parseJsonRequest($request);

      $workflow = WorkflowDTO::fromArray($data);
      $errors = $this->agentMapper->validateWorkflowMapping($workflow);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => [
          'valid' => empty($errors),
          'errors' => $errors,
        ],
        'message' => empty($errors) ? 'Workflow is valid for agent mapping' : 'Workflow validation failed',
      ]);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Validation error',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Store UI positions for an agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $id
   *   The agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response confirming positions stored.
   */
  public function storePositions(Request $request, string $id): JsonResponse {
    try {
      if (!$this->agentRepository->exists($id)) {
        throw new NotFoundHttpException('Agent not found');
      }

      $data = $this->parseJsonRequest($request);
      $positions = $data['positions'] ?? [];

      $this->agentMapper->storePositions($id, $positions);

      return $this->jsonResponse([
        'success' => TRUE,
        'message' => 'Positions stored successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Agent not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (BadRequestHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Invalid request',
        'message' => $e->getMessage(),
      ], Response::HTTP_BAD_REQUEST);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error storing positions for agent @id: @error',
        ['@id' => $id, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to store positions',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Load UI positions for an agent.
   *
   * @param string $id
   *   The agent ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with positions.
   */
  public function loadPositions(string $id): JsonResponse {
    try {
      if (!$this->agentRepository->exists($id)) {
        throw new NotFoundHttpException('Agent not found');
      }

      $positions = $this->agentMapper->loadPositions($id);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => [
          'positions' => $positions,
        ],
        'message' => 'Positions loaded successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Agent not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error loading positions for agent @id: @error',
        ['@id' => $id, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to load positions',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Parse JSON from request body.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Parsed JSON data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If JSON is invalid.
   */
  protected function parseJsonRequest(Request $request): array {
    $content = $request->getContent();
    $data = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new BadRequestHttpException('Invalid JSON: ' . json_last_error_msg());
    }

    return $data ?? [];
  }

  /**
   * Validate agent data.
   *
   * @param array $data
   *   The agent data.
   * @param bool $isUpdate
   *   Whether this is an update (some fields optional).
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If required fields are missing.
   */
  protected function validateAgentData(array $data, bool $isUpdate = FALSE): void {
    if (!$isUpdate) {
      if (empty($data['label'])) {
        throw new BadRequestHttpException('Agent label is required');
      }
    }

    if (isset($data['label']) && strlen($data['label']) > 255) {
      throw new BadRequestHttpException('Agent label too long (max 255 characters)');
    }
  }

  /**
   * Format an agent entity for API response.
   *
   * @param \Drupal\ai_agents\Entity\AiAgent $agent
   *   The agent entity.
   * @param string $viewMode
   *   The view mode: 'teaser' or 'full'.
   *
   * @return array
   *   Formatted agent data.
   */
  protected function formatAgentResponse($agent, string $viewMode = 'teaser'): array {
    $data = [
      'id' => $agent->id(),
      'label' => $agent->label(),
      'description' => $agent->get('description') ?? '',
      'orchestration_agent' => (bool) ($agent->get('orchestration_agent') ?? FALSE),
      'triage_agent' => (bool) ($agent->get('triage_agent') ?? FALSE),
    ];

    if ($viewMode === 'full') {
      $data['system_prompt'] = $agent->get('system_prompt') ?? '';
      $data['secured_system_prompt'] = $agent->get('secured_system_prompt') ?? '';
      $data['max_loops'] = $agent->get('max_loops') ?? 3;
      $data['tools'] = $agent->get('tools') ?? [];
      $data['tool_settings'] = $agent->get('tool_settings') ?? [];
      $data['tool_usage_limits'] = $agent->get('tool_usage_limits') ?? [];
      $data['structured_output_enabled'] = (bool) ($agent->get('structured_output_enabled') ?? FALSE);
      $data['structured_output_schema'] = $agent->get('structured_output_schema') ?? '';

      // Include UI positions if available.
      $thirdPartySettings = $agent->getThirdPartySetting('flowdrop_ai_provider', 'positions', []);
      if (!empty($thirdPartySettings)) {
        $data['ui_positions'] = $thirdPartySettings;
      }
    }

    return $data;
  }

  /**
   * Converts a WorkflowDTO to array format for JSON response.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow DTO.
   *
   * @return array
   *   The workflow as an array.
   */
  protected function workflowToArray(WorkflowDTO $workflow): array {
    $nodes = [];
    foreach ($workflow->getNodes() as $node) {
      $nodes[] = [
        'id' => $node->getId(),
        'typeId' => $node->getTypeId(),
        'label' => $node->getLabel(),
        'config' => $node->getConfig(),
        'metadata' => $node->getMetadata(),
        'position' => $node->getPosition(),
        'inputs' => $node->getInputs(),
        'outputs' => $node->getOutputs(),
      ];
    }

    $edges = [];
    foreach ($workflow->getEdges() as $edge) {
      $edges[] = [
        'id' => $edge->getId(),
        'source' => $edge->getSource(),
        'target' => $edge->getTarget(),
        'sourceHandle' => $edge->getSourceHandle(),
        'targetHandle' => $edge->getTargetHandle(),
        'isTrigger' => $edge->isTrigger(),
        'branchName' => $edge->getBranchName(),
      ];
    }

    return [
      'id' => $workflow->getId(),
      'name' => $workflow->getName(),
      'label' => $workflow->getName(),
      'description' => $workflow->getDescription(),
      'nodes' => $nodes,
      'edges' => $edges,
      'metadata' => $workflow->getMetadata(),
    ];
  }

  /**
   * Create a JsonResponse with standard headers.
   *
   * @param array $data
   *   The response data.
   * @param int $status
   *   The HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  protected function jsonResponse(array $data, int $status = Response::HTTP_OK): JsonResponse {
    return new JsonResponse($data, $status, [
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
    ]);
  }

}
