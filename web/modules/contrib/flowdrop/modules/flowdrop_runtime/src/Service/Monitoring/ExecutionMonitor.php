<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Monitoring;

use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Monitors workflow execution performance and metrics.
 */
class ExecutionMonitor {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  /**
   * Active monitoring sessions.
   *
   * @var array<string, array>
   */
  private array $activeSessions = [];

  /**
   * Performance metrics cache.
   *
   * @var array<string, array>
   */
  private array $metricsCache = [];

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Start monitoring an execution.
   *
   * @param string $executionId
   *   The execution ID to monitor.
   * @param array $context
   *   Additional context for monitoring.
   */
  public function startMonitoring(string $executionId, array $context = []): void {
    $this->logger->info('Started monitoring execution @id', [
      '@id' => $executionId,
      'context' => $context,
    ]);

    $session = [
      'execution_id' => $executionId,
      'start_time' => microtime(TRUE),
      'start_memory' => memory_get_usage(TRUE),
      'start_peak_memory' => memory_get_peak_usage(TRUE),
      'context' => $context,
      'metrics' => [
        'node_executions' => 0,
        'total_execution_time' => 0,
        'average_node_time' => 0,
        'errors' => 0,
        'warnings' => 0,
      ],
      'resource_usage' => [
        'memory_usage' => [],
        'cpu_usage' => [],
        'disk_usage' => [],
      ],
    ];

    $this->activeSessions[$executionId] = $session;

    // Dispatch monitoring started event.
    $event = new GenericEvent($executionId, [
      'execution_id' => $executionId,
      'session' => $session,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.monitoring.started');
  }

  /**
   * Stop monitoring an execution.
   *
   * @param string $executionId
   *   The execution ID to stop monitoring.
   *
   * @return array
   *   Final monitoring report.
   */
  public function stopMonitoring(string $executionId): array {
    if (!isset($this->activeSessions[$executionId])) {
      $this->logger->warning('Attempted to stop monitoring non-existent execution: @id', [
        '@id' => $executionId,
      ]);
      return [];
    }

    $session = $this->activeSessions[$executionId];
    $endTime = microtime(TRUE);
    $endMemory = memory_get_usage(TRUE);
    $endPeakMemory = memory_get_peak_usage(TRUE);

    $totalTime = $endTime - $session['start_time'];
    $memoryDelta = $endMemory - $session['start_memory'];
    $peakMemoryDelta = $endPeakMemory - $session['start_peak_memory'];

    $finalReport = [
      'execution_id' => $executionId,
      'total_execution_time' => $totalTime,
      'memory_usage' => [
        'start' => $session['start_memory'],
        'end' => $endMemory,
        'delta' => $memoryDelta,
        'peak_start' => $session['start_peak_memory'],
        'peak_end' => $endPeakMemory,
        'peak_delta' => $peakMemoryDelta,
      ],
      'metrics' => $session['metrics'],
      'resource_usage' => $session['resource_usage'],
      'performance_score' => $this->calculatePerformanceScore($session, $totalTime),
    ];

    $this->logger->info('Stopped monitoring execution @id', [
      '@id' => $executionId,
      'total_time' => $totalTime,
      'memory_delta' => $memoryDelta,
      'performance_score' => $finalReport['performance_score'],
    ]);

    // Store final report in cache.
    $this->metricsCache[$executionId] = $finalReport;

    // Remove from active sessions.
    unset($this->activeSessions[$executionId]);

    // Dispatch monitoring stopped event.
    $event = new GenericEvent($executionId, [
      'execution_id' => $executionId,
      'final_report' => $finalReport,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.monitoring.stopped');

    return $finalReport;
  }

  /**
   * Record a node execution event.
   *
   * @param string $executionId
   *   The execution ID.
   * @param string $nodeId
   *   The node ID.
   * @param array $metrics
   *   Node execution metrics.
   */
  public function recordNodeExecution(string $executionId, string $nodeId, array $metrics = []): void {
    if (!isset($this->activeSessions[$executionId])) {
      $this->logger->warning('Attempted to record node execution for non-monitored execution: @id', [
        '@id' => $executionId,
      ]);
      return;
    }

    $session = &$this->activeSessions[$executionId];
    $session['metrics']['node_executions']++;
    $session['metrics']['total_execution_time'] += $metrics['execution_time'] ?? 0;

    // Update average node execution time.
    $session['metrics']['average_node_time'] = $session['metrics']['total_execution_time'] / $session['metrics']['node_executions'];

    // Record resource usage.
    $this->recordResourceUsage($executionId, $metrics);

    $this->logger->debug('Recorded node execution for @execution_id, node @node_id', [
      '@execution_id' => $executionId,
      '@node_id' => $nodeId,
      'metrics' => $metrics,
    ]);

    // Dispatch node execution event.
    $event = new GenericEvent($nodeId, [
      'execution_id' => $executionId,
      'node_id' => $nodeId,
      'metrics' => $metrics,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.monitoring.node_execution');
  }

  /**
   * Record an error during execution.
   *
   * @param string $executionId
   *   The execution ID.
   * @param \Exception $error
   *   The error that occurred.
   * @param array $context
   *   Additional context.
   */
  public function recordError(string $executionId, \Exception $error, array $context = []): void {
    if (!isset($this->activeSessions[$executionId])) {
      return;
    }

    $session = &$this->activeSessions[$executionId];
    $session['metrics']['errors']++;

    $this->logger->error('Recorded error for execution @id: @error', [
      '@id' => $executionId,
      '@error' => $error->getMessage(),
      'context' => $context,
    ]);

    // Dispatch error event.
    $event = new GenericEvent($error, [
      'execution_id' => $executionId,
      'error' => $error->getMessage(),
      'context' => $context,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.monitoring.error');
  }

  /**
   * Record a warning during execution.
   *
   * @param string $executionId
   *   The execution ID.
   * @param string $warning
   *   The warning message.
   * @param array $context
   *   Additional context.
   */
  public function recordWarning(string $executionId, string $warning, array $context = []): void {
    if (!isset($this->activeSessions[$executionId])) {
      return;
    }

    $session = &$this->activeSessions[$executionId];
    $session['metrics']['warnings']++;

    $this->logger->warning('Recorded warning for execution @id: @warning', [
      '@id' => $executionId,
      '@warning' => $warning,
      'context' => $context,
    ]);

    // Dispatch warning event.
    $event = new GenericEvent($warning, [
      'execution_id' => $executionId,
      'warning' => $warning,
      'context' => $context,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.monitoring.warning');
  }

  /**
   * Get current monitoring status for an execution.
   *
   * @param string $executionId
   *   The execution ID.
   *
   * @return array|null
   *   Current monitoring status or NULL if not monitored.
   */
  public function getMonitoringStatus(string $executionId): ?array {
    if (!isset($this->activeSessions[$executionId])) {
      return NULL;
    }

    $session = $this->activeSessions[$executionId];
    $currentTime = microtime(TRUE);
    $currentMemory = memory_get_usage(TRUE);

    return [
      'execution_id' => $executionId,
      'elapsed_time' => $currentTime - $session['start_time'],
      'current_memory' => $currentMemory,
      'memory_delta' => $currentMemory - $session['start_memory'],
      'metrics' => $session['metrics'],
      'resource_usage' => $session['resource_usage'],
    ];
  }

  /**
   * Get performance metrics for all monitored executions.
   *
   * @param array $filters
   *   Optional filters for metrics.
   *
   * @return array
   *   Performance metrics summary.
   */
  public function getPerformanceMetrics(array $filters = []): array {
    $metrics = [
      'active_executions' => count($this->activeSessions),
      'completed_executions' => count($this->metricsCache),
      'total_execution_time' => 0,
      'average_execution_time' => 0,
      'total_node_executions' => 0,
      'total_errors' => 0,
      'total_warnings' => 0,
      'memory_usage' => [
        'current' => memory_get_usage(TRUE),
        'peak' => memory_get_peak_usage(TRUE),
      ],
    ];

    // Calculate metrics from active sessions.
    foreach ($this->activeSessions as $session) {
      $metrics['total_node_executions'] += $session['metrics']['node_executions'];
      $metrics['total_errors'] += $session['metrics']['errors'];
      $metrics['total_warnings'] += $session['metrics']['warnings'];
    }

    // Calculate metrics from completed executions.
    foreach ($this->metricsCache as $report) {
      $metrics['total_execution_time'] += $report['total_execution_time'];
      $metrics['total_node_executions'] += $report['metrics']['node_executions'];
      $metrics['total_errors'] += $report['metrics']['errors'];
      $metrics['total_warnings'] += $report['metrics']['warnings'];
    }

    // Calculate averages.
    $totalExecutions = count($this->metricsCache);
    if ($totalExecutions > 0) {
      $metrics['average_execution_time'] = $metrics['total_execution_time'] / $totalExecutions;
    }

    return $metrics;
  }

  /**
   * Get detailed performance report for an execution.
   *
   * @param string $executionId
   *   The execution ID.
   *
   * @return array|null
   *   Detailed performance report or NULL if not found.
   */
  public function getDetailedReport(string $executionId): ?array {
    // Check active sessions first.
    if (isset($this->activeSessions[$executionId])) {
      return $this->getMonitoringStatus($executionId);
    }

    // Check completed executions.
    return $this->metricsCache[$executionId] ?? NULL;
  }

  /**
   * Clear old metrics cache entries.
   *
   * @param int $maxAge
   *   Maximum age in seconds for cache entries.
   */
  public function clearOldMetrics(int $maxAge = 86400): void {
    $cutoffTime = time() - $maxAge;
    $clearedCount = 0;

    foreach ($this->metricsCache as $executionId => $report) {
      if (isset($report['timestamp']) && $report['timestamp'] < $cutoffTime) {
        unset($this->metricsCache[$executionId]);
        $clearedCount++;
      }
    }

    if ($clearedCount > 0) {
      $this->logger->info('Cleared @count old metrics cache entries', [
        '@count' => $clearedCount,
      ]);
    }
  }

  /**
   * Record resource usage for an execution.
   *
   * @param string $executionId
   *   The execution ID.
   * @param array $metrics
   *   The metrics containing resource usage data.
   */
  private function recordResourceUsage(string $executionId, array $metrics): void {
    if (!isset($this->activeSessions[$executionId])) {
      return;
    }

    $session = &$this->activeSessions[$executionId];
    $timestamp = time();

    // Record memory usage.
    if (isset($metrics['memory_usage'])) {
      $session['resource_usage']['memory_usage'][$timestamp] = $metrics['memory_usage'];
    }

    // Record CPU usage.
    if (isset($metrics['cpu_usage'])) {
      $session['resource_usage']['cpu_usage'][$timestamp] = $metrics['cpu_usage'];
    }

    // Record disk usage.
    if (isset($metrics['disk_usage'])) {
      $session['resource_usage']['disk_usage'][$timestamp] = $metrics['disk_usage'];
    }
  }

  /**
   * Calculate performance score for an execution.
   *
   * @param array $session
   *   The monitoring session data.
   * @param float $totalTime
   *   The total execution time.
   *
   * @return float
   *   Performance score between 0 and 100.
   */
  private function calculatePerformanceScore(array $session, float $totalTime): float {
    $score = 100.0;

    // Deduct points for errors.
    $errorPenalty = $session['metrics']['errors'] * 10;
    $score -= $errorPenalty;

    // Deduct points for warnings.
    $warningPenalty = $session['metrics']['warnings'] * 2;
    $score -= $warningPenalty;

    // Deduct points for long execution time (if over 30 seconds).
    if ($totalTime > 30) {
      $timePenalty = min(20, ($totalTime - 30) / 10);
      $score -= $timePenalty;
    }

    // Deduct points for high memory usage.
    $memoryUsage = memory_get_usage(TRUE);
    $memoryLimit = ini_get('memory_limit');
    if ($memoryLimit !== '-1') {
      $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
      $memoryPercentage = ($memoryUsage / $memoryLimitBytes) * 100;
      if ($memoryPercentage > 80) {
        $memoryPenalty = min(15, ($memoryPercentage - 80) / 2);
        $score -= $memoryPenalty;
      }
    }

    return max(0, $score);
  }

  /**
   * Parse memory limit string to bytes.
   *
   * @param string $memoryLimit
   *   Memory limit string (e.g., "128M", "1G").
   *
   * @return int
   *   Memory limit in bytes.
   */
  private function parseMemoryLimit(string $memoryLimit): int {
    $unit = strtoupper(substr($memoryLimit, -1));
    $value = (int) substr($memoryLimit, 0, -1);

    return match ($unit) {
      'K' => $value * 1024,
      'M' => $value * 1024 * 1024,
      'G' => $value * 1024 * 1024 * 1024,
      default => $value,
    };
  }

}
