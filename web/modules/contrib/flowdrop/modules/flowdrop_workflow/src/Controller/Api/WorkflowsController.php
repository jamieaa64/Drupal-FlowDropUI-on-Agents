<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\flowdrop_workflow\FlowDropWorkflowInterface;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API controller for workflow management.
 */
final class WorkflowsController extends ControllerBase {

  /**
   * Get all workflows.
   */
  public function getWorkflows(Request $request): JsonResponse {
    try {
      // Get query parameters.
      $search = $request->query->get('search');
      $limit = (int) ($request->query->get('limit') ?? 50);
      $offset = (int) ($request->query->get('offset') ?? 0);

      // Validate parameters.
      $limit = max(1, min(100, $limit));
      $offset = max(0, $offset);

      // Get workflows from entity storage.
      $storage = $this->entityTypeManager()->getStorage('flowdrop_workflow');
      $query = $storage->getQuery();

      if ($search) {
        $query = $query->condition('label', '%' . $search . '%', 'LIKE');
      }

      $ids = $query
        ->sort('changed', 'DESC')
        ->accessCheck(FALSE)
        ->range($offset, $limit)
        ->execute();

      $workflows = $storage->loadMultiple($ids);

      $data = [];
      foreach ($workflows as $workflow) {
        if ($workflow instanceof FlowDropWorkflowInterface) {
          $data[] = [
            'id' => $workflow->id(),
            'name' => $workflow->label(),
            'description' => $workflow->getDescription(),
            'nodes' => $workflow->getNodes(),
            'edges' => $workflow->getEdges(),
            'metadata' => $workflow->getMetadata(),
            'created' => $workflow->getCreated(),
            'changed' => $workflow->getChanged(),
            'uid' => $workflow->getUid(),
          ];
        }
      }

      $response = [
        'success' => TRUE,
        'data' => $data,
        'message' => sprintf('Found %d workflows', count($data)),
      ];

      return new JsonResponse($response, 200, [
        'X-Total-Count' => count($data),
        'X-Page-Size' => $limit,
        'X-Page-Offset' => $offset,
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
      ]);

    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop')->error('Error fetching workflows: @error', ['@error' => $e->getMessage()]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch workflows',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Create a new workflow.
   */
  public function createWorkflow(Request $request): JsonResponse {
    try {
      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new BadRequestHttpException('Invalid JSON data');
      }

      // Validate required fields.
      if (empty($data['label'])) {
        throw new BadRequestHttpException('Workflow label is required');
      }

      if (strlen($data['label']) > 255) {
        throw new BadRequestHttpException('Workflow label too long (max 255 characters)');
      }

      // Create workflow entity.
      $workflow = $this->entityTypeManager->getStorage('flowdrop_workflow')->create([
        'id' => $data['id'] ?? $this->generateWorkflowId(),
        'label' => trim($data['name']),
        'description' => trim($data['description'] ?? ''),
        'nodes' => $data['nodes'] ?? [],
        'edges' => $data['edges'] ?? [],
        'metadata' => $data['metadata'] ?? [],
        'uid' => $this->currentUser()->id(),
      ]);

      if (!$workflow instanceof FlowDropWorkflowInterface) {
        throw new EntityMalformedException("Workflow creation failed");
      }

      $workflow->save();

      $response = [
        'success' => TRUE,
        'data' => [
          'id' => $workflow->id(),
          'name' => $workflow->label(),
          'description' => $workflow->getDescription(),
          'nodes' => $workflow->getNodes(),
          'edges' => $workflow->getEdges(),
          'metadata' => $workflow->getMetadata(),
          'created' => $workflow->getCreated(),
          'changed' => $workflow->getChanged(),
          'uid' => $workflow->getUid(),
        ],
        'message' => 'Workflow created successfully',
      ];

      return new JsonResponse($response, 201, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
      ]);

    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop')->error('Error creating workflow: @error', ['@error' => $e->getMessage()]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to create workflow',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get a specific workflow by ID.
   */
  public function getWorkflow(Request $request, string $id): JsonResponse {
    try {
      // Get workflow from entity storage.
      $workflow = $this->entityTypeManager()
        ->getStorage('flowdrop_workflow')
        ->load($id);

      if (!$workflow instanceof FlowDropWorkflowInterface) {
        throw new NotFoundHttpException('Workflow not found');
      }

      $response = [
        'success' => TRUE,
        'data' => [
          'id' => $workflow->id(),
          'name' => $workflow->label(),
          'description' => $workflow->getDescription(),
          'nodes' => $workflow->getNodes(),
          'edges' => $workflow->getEdges(),
          'metadata' => $workflow->getMetadata(),
          'created' => $workflow->getCreated(),
          'changed' => $workflow->getChanged(),
          'uid' => $workflow->getUid(),
        ],
        'message' => 'Workflow retrieved successfully',
      ];

      return new JsonResponse($response, 200, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
      ]);

    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop')->error('Error fetching workflow: @error', ['@error' => $e->getMessage()]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to fetch workflow',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Update an existing workflow.
   */
  public function updateWorkflow(Request $request, string $id): JsonResponse {
    try {
      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new BadRequestHttpException('Invalid JSON data');
      }

      // Get existing workflow.
      $workflow = $this->entityTypeManager()
        ->getStorage('flowdrop_workflow')
        ->load($id);

      if (!$workflow instanceof FlowDropWorkflowInterface) {
        throw new NotFoundHttpException('Workflow not found');
      }
      // Validate required fields.
      if (empty($data['name'])) {
        throw new BadRequestHttpException('Workflow name is required');
      }

      if (strlen($data['name']) > 255) {
        throw new BadRequestHttpException('Workflow name too long (max 255 characters)');
      }

      // Update workflow fields.
      if (isset($data['name'])) {
        $workflow->setLabel($data['name']);
      }
      if (isset($data['description'])) {
        $workflow->setDescription($data['description']);
      }

      if (isset($data['nodes'])) {
        $workflow->setNodes($data['nodes']);
      }
      if (isset($data['edges'])) {
        $workflow->setEdges($data['edges']);
      }
      if (isset($data['metadata'])) {
        $workflow->setMetadata($data['metadata']);
      }

      $workflow->save();

      $response = [
        'success' => TRUE,
        'data' => [
          'id' => $workflow->id(),
          'label' => $workflow->label(),
          'description' => $workflow->getDescription(),
          'nodes' => $workflow->getNodes(),
          'edges' => $workflow->getEdges(),
          'metadata' => $workflow->getMetadata(),
          'created' => $workflow->getCreated(),
          'changed' => $workflow->getChanged(),
          'uid' => $workflow->getUid(),
        ],
        'message' => 'Workflow updated successfully',
      ];

      return new JsonResponse($response, 200, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
      ]);

    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop')->error('Error updating workflow: @error', ['@error' => $e->getMessage()]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to update workflow',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Delete a workflow.
   */
  public function deleteWorkflow(Request $request, string $id): JsonResponse {
    try {
      // Get existing workflow.
      $workflow = $this->entityTypeManager()
        ->getStorage('flowdrop_workflow')
        ->load($id);

      if (!$workflow) {
        throw new NotFoundHttpException('Workflow not found');
      }

      // Delete the workflow.
      $workflow->delete();

      $response = [
        'success' => TRUE,
        'message' => 'Workflow deleted successfully',
      ];

      return new JsonResponse($response, 200, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
      ]);

    }
    catch (\Exception $e) {
      $this->getLogger('flowdrop')->error('Error deleting workflow: @error', ['@error' => $e->getMessage()]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to delete workflow',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Generate a unique workflow ID.
   *
   * @return string
   *   A unique workflow ID.
   */
  protected function generateWorkflowId(): string {
    return 'workflow_' . date('Y-m-d-H-i-s') . '_' . uniqid();
  }

  /**
   * Implementation pending method stub.
   */
  protected function implementationPending(): JsonResponse {
    throw new NotFoundException();
  }

}
