<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager;
use Drupal\flowdrop_runtime\Service\Monitoring\ExecutionMonitor;

/**
 * REST API controller for real-time status endpoints.
 */
class StatusController extends ControllerBase {

  public function __construct(
    private readonly RealTimeManager $realTimeManager,
    private readonly ExecutionMonitor $executionMonitor,
  ) {}

  /**
   * Create controller instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The controller instance.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_runtime.real_time_manager'),
      $container->get('flowdrop_runtime.execution_monitor')
    );
  }

  /**
   * Get execution status for polling.
   *
   * @param string $executionId
   *   The execution ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with execution status.
   */
  public function getExecutionStatus(string $executionId): JsonResponse {
    try {
      $status = $this->realTimeManager->getExecutionStatus($executionId);

      if (empty($status)) {
        return new JsonResponse([
          'error' => 'Execution not found',
          'execution_id' => $executionId,
        ], 404);
      }

      return new JsonResponse([
        'execution_id' => $executionId,
        'status' => $status,
      ]);

    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
        'execution_id' => $executionId,
      ], 500);
    }
  }

  /**
   * Get node statuses for an execution.
   *
   * @param string $executionId
   *   The execution ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with node statuses.
   */
  public function getNodeStatuses(string $executionId): JsonResponse {
    try {
      $nodeStatuses = $this->realTimeManager->getNodeStatuses($executionId);

      return new JsonResponse([
        'execution_id' => $executionId,
        'node_statuses' => $nodeStatuses,
      ]);

    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
        'execution_id' => $executionId,
      ], 500);
    }
  }

  /**
   * Get detailed execution information.
   *
   * @param string $executionId
   *   The execution ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with detailed execution info.
   */
  public function getExecutionDetails(string $executionId): JsonResponse {
    try {
      $executionStatus = $this->realTimeManager->getExecutionStatus($executionId);
      $nodeStatuses = $this->realTimeManager->getNodeStatuses($executionId);

      if (empty($executionStatus)) {
        return new JsonResponse([
          'error' => 'Execution not found',
          'execution_id' => $executionId,
        ], 404);
      }

      // Calculate metrics.
      $totalNodes = count($nodeStatuses);
      $completedNodes = 0;
      $failedNodes = 0;
      $totalExecutionTime = 0;

      foreach ($nodeStatuses as $nodeStatus) {
        if ($nodeStatus['status'] === 'completed') {
          $completedNodes++;
          $totalExecutionTime += $nodeStatus['execution_time'] ?? 0;
        }
        elseif ($nodeStatus['status'] === 'failed') {
          $failedNodes++;
        }
      }

      return new JsonResponse([
        'execution_id' => $executionId,
        'execution_status' => $executionStatus,
        'node_statuses' => $nodeStatuses,
        'metrics' => [
          'total_nodes' => $totalNodes,
          'completed_nodes' => $completedNodes,
          'failed_nodes' => $failedNodes,
          'pending_nodes' => $totalNodes - $completedNodes - $failedNodes,
          'total_execution_time' => $totalExecutionTime,
        ],
        'timestamp' => time(),
      ]);

    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
        'execution_id' => $executionId,
      ], 500);
    }
  }

  /**
   * Get system health status.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with system health information.
   */
  public function getHealth(): JsonResponse {
    try {
      $healthStatus = [
        'status' => 'healthy',
        'timestamp' => time(),
        'version' => '1.0.0',
        'services' => [
          'real_time_manager' => 'operational',
          'execution_monitor' => 'operational',
          'data_flow_manager' => 'operational',
          'error_handler' => 'operational',
        ],
        'system' => [
          'memory_usage' => memory_get_usage(TRUE),
          'memory_limit' => ini_get('memory_limit'),
          'php_version' => PHP_VERSION,
          'drupal_version' => \Drupal::VERSION,
        ],
      ];

      return new JsonResponse($healthStatus);

    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => time(),
      ], 500);
    }
  }

  /**
   * Get system performance metrics.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with system metrics.
   */
  public function getSystemMetrics(): JsonResponse {
    try {
      $metrics = $this->executionMonitor->getPerformanceMetrics();

      return new JsonResponse([
        'metrics' => $metrics,
        'timestamp' => time(),
        'period' => 'current',
      ]);

    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
        'timestamp' => time(),
      ], 500);
    }
  }

}
