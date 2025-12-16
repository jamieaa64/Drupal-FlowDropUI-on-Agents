<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\flowdrop_ai_provider\Services\ToolDataProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API controller for tool discovery and metadata.
 *
 * Provides REST endpoints for the FlowDrop UI to discover
 * available tools and their configuration schemas.
 */
final class ToolsController extends ControllerBase {

  /**
   * The tool data provider service.
   *
   * @var \Drupal\flowdrop_ai_provider\Services\ToolDataProviderInterface
   */
  protected ToolDataProviderInterface $toolDataProvider;

  /**
   * Constructs a ToolsController.
   *
   * @param \Drupal\flowdrop_ai_provider\Services\ToolDataProviderInterface $toolDataProvider
   *   The tool data provider service.
   */
  public function __construct(ToolDataProviderInterface $toolDataProvider) {
    $this->toolDataProvider = $toolDataProvider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_ai_provider.tool_data_provider'),
    );
  }

  /**
   * Get all available tools.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with all tools.
   */
  public function getTools(Request $request): JsonResponse {
    try {
      $viewMode = $request->query->get('view_mode', 'teaser');
      $tools = $this->toolDataProvider->getAvailableTools($viewMode);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $tools,
        'count' => count($tools),
        'message' => sprintf('Found %d tools', count($tools)),
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching tools: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch tools',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get tools grouped by category.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with tools grouped by category.
   */
  public function getToolsByCategory(): JsonResponse {
    try {
      $grouped = $this->toolDataProvider->getToolsByCategory();

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $grouped,
        'categories' => array_keys($grouped),
        'message' => sprintf('Found %d categories', count($grouped)),
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching tools by category: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch tools by category',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get a specific tool by ID.
   *
   * @param string $toolId
   *   The tool ID (URL-encoded, colons will be decoded).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with tool data.
   */
  public function getTool(string $toolId): JsonResponse {
    try {
      // URL decode the tool ID (handles : encoded as %3A).
      $toolId = urldecode($toolId);

      $tool = $this->toolDataProvider->getTool($toolId);

      if (!$tool) {
        throw new NotFoundHttpException('Tool not found');
      }

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $tool,
        'message' => 'Tool retrieved successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Tool not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching tool @id: @error',
        ['@id' => $toolId, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch tool',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get tool configuration schema.
   *
   * @param string $toolId
   *   The tool ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with tool configuration schema.
   */
  public function getToolSchema(string $toolId): JsonResponse {
    try {
      $toolId = urldecode($toolId);

      $tool = $this->toolDataProvider->getTool($toolId);
      if (!$tool) {
        throw new NotFoundHttpException('Tool not found');
      }

      $schema = $this->toolDataProvider->getToolConfigSchema($toolId);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => [
          'tool_id' => $toolId,
          'schema' => $schema,
        ],
        'message' => 'Tool schema retrieved successfully',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Tool not found',
        'message' => $e->getMessage(),
      ], Response::HTTP_NOT_FOUND);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching tool schema for @id: @error',
        ['@id' => $toolId, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch tool schema',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Validate tool configuration.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $toolId
   *   The tool ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with validation results.
   */
  public function validateToolConfig(Request $request, string $toolId): JsonResponse {
    try {
      $toolId = urldecode($toolId);

      $tool = $this->toolDataProvider->getTool($toolId);
      if (!$tool) {
        throw new NotFoundHttpException('Tool not found');
      }

      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new BadRequestHttpException('Invalid JSON: ' . json_last_error_msg());
      }

      $configuration = $data['configuration'] ?? $data['config'] ?? [];
      $errors = $this->toolDataProvider->validateToolConfig($toolId, $configuration);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => [
          'tool_id' => $toolId,
          'valid' => empty($errors),
          'errors' => $errors,
        ],
        'message' => empty($errors) ? 'Configuration is valid' : 'Configuration validation failed',
      ]);
    }
    catch (NotFoundHttpException $e) {
      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Tool not found',
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
        'Error validating tool config for @id: @error',
        ['@id' => $toolId, '@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Validation error',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Search tools by keyword.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with matching tools.
   */
  public function searchTools(Request $request): JsonResponse {
    try {
      $query = $request->query->get('q', '');

      if (strlen($query) < 2) {
        throw new BadRequestHttpException('Search query must be at least 2 characters');
      }

      $tools = $this->toolDataProvider->searchTools($query);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $tools,
        'query' => $query,
        'count' => count($tools),
        'message' => sprintf('Found %d matching tools', count($tools)),
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
        'Error searching tools: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Search failed',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get available AI Agents that can be used as sub-agents.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with available agents.
   */
  public function getAvailableAgents(Request $request): JsonResponse {
    try {
      $viewMode = $request->query->get('view_mode', 'teaser');
      $agents = $this->toolDataProvider->getAvailableAgents($viewMode);

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $agents,
        'count' => count($agents),
        'message' => sprintf('Found %d available agents', count($agents)),
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching available agents: @error',
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
   * Get the JSON schema for agent configuration.
   *
   * This can be used by LLMs or validation tools.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with agent schema.
   */
  public function getAgentSchema(): JsonResponse {
    try {
      $schema = $this->toolDataProvider->getAgentSchema();

      return $this->jsonResponse([
        'success' => TRUE,
        'data' => $schema,
        'message' => 'Agent schema retrieved successfully',
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop_ai_provider')->error(
        'Error fetching agent schema: @error',
        ['@error' => $e->getMessage()]
      );

      return $this->jsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch agent schema',
        'message' => $e->getMessage(),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
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
