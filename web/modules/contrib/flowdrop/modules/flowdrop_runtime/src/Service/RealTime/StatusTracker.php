<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\RealTime;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop_runtime\DTO\RealTime\ExecutionStatus;
use Drupal\flowdrop_runtime\DTO\RealTime\NodeStatus;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Tracks execution and node statuses in memory for real-time updates.
 */
class StatusTracker {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  /**
   * In-memory storage for execution statuses.
   *
   * @var array
   */
  private array $executionStatuses = [];

  /**
   * In-memory storage for node statuses.
   *
   * @var array
   */
  private array $nodeStatuses = [];

  public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Initialize status tracking for a new execution.
   */
  public function initializeExecution(string $executionId, array $workflow): void {
    $nodes = $workflow['nodes'] ?? [];
    $this->executionStatuses[$executionId] = [
      'status' => 'initialized',
      'workflow_id' => $workflow['id'] ?? '',
      'node_count' => count($nodes),
      'start_time' => time(),
      'end_time' => NULL,
      'total_execution_time' => NULL,
      'error' => NULL,
    ];

    $this->nodeStatuses[$executionId] = [];
    foreach ($nodes as $node) {
      $this->nodeStatuses[$executionId][$node['id']] = [
        'status' => 'pending',
        'node_id' => $node['id'],
        'node_type' => $node['type'] ?? '',
        'start_time' => NULL,
        'end_time' => NULL,
        'execution_time' => NULL,
        'error' => NULL,
        'output' => NULL,
      ];
    }

    $this->logger->debug('Initialized status tracking for execution @id', [
      '@id' => $executionId,
    ]);
  }

  /**
   * Update execution status.
   */
  public function updateExecutionStatus(ExecutionStatus $status): void {
    $executionId = $status->getExecutionId();

    if (!isset($this->executionStatuses[$executionId])) {
      $this->logger->warning('Attempted to update status for unknown execution @id', [
        '@id' => $executionId,
      ]);
      return;
    }

    $this->executionStatuses[$executionId]['status'] = $status->getStatus();
    $this->executionStatuses[$executionId]['data'] = $status->getData();
    $this->executionStatuses[$executionId]['timestamp'] = $status->getTimestamp();

    if ($status->getStatus() === 'completed' || $status->getStatus() === 'failed') {
      $this->executionStatuses[$executionId]['end_time'] = $status->getTimestamp();
      if ($this->executionStatuses[$executionId]['start_time']) {
        $this->executionStatuses[$executionId]['total_execution_time'] =
          $status->getTimestamp() - $this->executionStatuses[$executionId]['start_time'];
      }
    }

    $this->logger->debug('Updated execution status: @id = @status', [
      '@id' => $executionId,
      '@status' => $status->getStatus(),
    ]);
  }

  /**
   * Update node status.
   */
  public function updateNodeStatus(NodeStatus $status): void {
    $executionId = $status->getExecutionId();
    $nodeId = $status->getNodeId();

    if (!isset($this->nodeStatuses[$executionId][$nodeId])) {
      $this->logger->warning('Attempted to update status for unknown node @execution_id:@node_id', [
        '@execution_id' => $executionId,
        '@node_id' => $nodeId,
      ]);
      return;
    }

    $this->nodeStatuses[$executionId][$nodeId]['status'] = $status->getStatus();
    $this->nodeStatuses[$executionId][$nodeId]['data'] = $status->getData();
    $this->nodeStatuses[$executionId][$nodeId]['timestamp'] = $status->getTimestamp();

    if ($status->getStatus() === 'running') {
      $this->nodeStatuses[$executionId][$nodeId]['start_time'] = $status->getTimestamp();
    }
    elseif (in_array($status->getStatus(), ['completed', 'failed'])) {
      $this->nodeStatuses[$executionId][$nodeId]['end_time'] = $status->getTimestamp();
      if ($this->nodeStatuses[$executionId][$nodeId]['start_time']) {
        $this->nodeStatuses[$executionId][$nodeId]['execution_time'] =
          $status->getTimestamp() - $this->nodeStatuses[$executionId][$nodeId]['start_time'];
      }
    }

    $this->logger->debug('Updated node status: @execution_id:@node_id = @status', [
      '@execution_id' => $executionId,
      '@node_id' => $nodeId,
      '@status' => $status->getStatus(),
    ]);
  }

  /**
   * Get execution status for polling.
   */
  public function getExecutionStatus(string $executionId): array {
    return $this->executionStatuses[$executionId] ?? [];
  }

  /**
   * Get node statuses for an execution.
   */
  public function getNodeStatuses(string $executionId): array {
    return $this->nodeStatuses[$executionId] ?? [];
  }

  /**
   * Clear status data for an execution.
   */
  public function clearExecution(string $executionId): void {
    unset($this->executionStatuses[$executionId]);
    unset($this->nodeStatuses[$executionId]);

    $this->logger->debug('Cleared status data for execution @id', [
      '@id' => $executionId,
    ]);
  }

}
