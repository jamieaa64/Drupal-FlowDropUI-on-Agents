<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Unit\Service\Monitoring;

use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\Tests\UnitTestCase;
use Drupal\flowdrop_runtime\Service\Monitoring\ExecutionMonitor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ExecutionMonitor.
 *
 * @coversDefaultClass \Drupal\flowdrop_runtime\Service\Monitoring\ExecutionMonitor
 * @group flowdrop_runtime
 */
class ExecutionMonitorTest extends UnitTestCase {

  /**
   * The execution monitor service.
   *
   * @var \Drupal\flowdrop_runtime\Service\Monitoring\ExecutionMonitor
   */
  private ExecutionMonitor $executionMonitor;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private LoggerInterface $logger;

  /**
   * Mock event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')->willReturn($this->logger);

    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->executionMonitor = new ExecutionMonitor($this->loggerFactory, $this->eventDispatcher);
  }

  /**
   * Test starting monitoring for an execution.
   *
   * @covers ::startMonitoring
   */
  public function testStartMonitoring(): void {
    $executionId = 'test_execution_123';
    $context = ['workflow_id' => 'test_workflow', 'user_id' => 'test_user'];

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Started monitoring execution @id');

    $this->eventDispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(GenericEvent::class), 'flowdrop_runtime.monitoring.started');

    $this->executionMonitor->startMonitoring($executionId, $context);

    // Verify that the session was created.
    $status = $this->executionMonitor->getMonitoringStatus($executionId);
    $this->assertNotNull($status);
    $this->assertEquals($executionId, $status['execution_id']);
  }

  /**
   * Test stopping monitoring for an execution.
   *
   * @covers ::stopMonitoring
   */
  public function testStopMonitoring(): void {
    $executionId = 'test_execution_123';
    $context = ['workflow_id' => 'test_workflow'];

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId, $context);

    // Record some node executions.
    $this->executionMonitor->recordNodeExecution($executionId, 'node_1', ['execution_time' => 1.5]);
    $this->executionMonitor->recordNodeExecution($executionId, 'node_2', ['execution_time' => 2.0]);
    $report = $this->executionMonitor->stopMonitoring($executionId);
    $this->assertArrayHasKey('execution_id', $report);
    $this->assertEquals($executionId, $report['execution_id']);
    $this->assertArrayHasKey('total_execution_time', $report);
    $this->assertArrayHasKey('memory_usage', $report);
    $this->assertArrayHasKey('metrics', $report);
    $this->assertArrayHasKey('performance_score', $report);
    $this->assertEquals(2, $report['metrics']['node_executions']);
    $this->assertEquals(3.5, $report['metrics']['total_execution_time']);
  }

  /**
   * Test stopping monitoring for non-existent execution.
   *
   * @covers ::stopMonitoring
   */
  public function testStopMonitoringNonExistentExecution(): void {
    $executionId = 'non_existent_execution';
    $report = $this->executionMonitor->stopMonitoring($executionId);
    $this->assertEmpty($report);
  }

  /**
   * Test recording node execution.
   *
   * @covers ::recordNodeExecution
   */
  public function testRecordNodeExecution(): void {
    $executionId = 'test_execution_123';
    $nodeId = 'test_node_1';
    $metrics = [
      'execution_time' => 1.5,
    // 1MB
      'memory_usage' => 1024 * 1024,
      'cpu_usage' => 25.5,
    ];

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId);
    $this->executionMonitor->recordNodeExecution($executionId, $nodeId, $metrics);

    // Verify the metrics were recorded.
    $status = $this->executionMonitor->getMonitoringStatus($executionId);
    $this->assertEquals(1, $status['metrics']['node_executions']);
    $this->assertEquals(1.5, $status['metrics']['total_execution_time']);
    $this->assertEquals(1.5, $status['metrics']['average_node_time']);
  }

  /**
   * Test recording node execution for non-monitored execution.
   *
   * @covers ::recordNodeExecution
   */
  public function testRecordNodeExecutionNonMonitored(): void {
    $executionId = 'non_monitored_execution';
    $nodeId = 'test_node_1';
    $this->executionMonitor->recordNodeExecution($executionId, $nodeId);
    $this->expectNotToPerformAssertions();
  }

  /**
   * Test recording errors during execution.
   *
   * @covers ::recordError
   */
  public function testRecordError(): void {
    $executionId = 'test_execution_123';
    $error = new \Exception('Test error message');
    $context = ['node_id' => 'test_node', 'step' => 'processing'];

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId);
    $this->executionMonitor->recordError($executionId, $error, $context);
    // Verify the error was recorded.
    $status = $this->executionMonitor->getMonitoringStatus($executionId);
    $this->assertEquals(1, $status['metrics']['errors']);
  }

  /**
   * Test recording warnings during execution.
   *
   * @covers ::recordWarning
   */
  public function testRecordWarning(): void {
    $executionId = 'test_execution_123';
    $warning = 'Test warning message';
    $context = ['node_id' => 'test_node'];

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId);

    $this->executionMonitor->recordWarning($executionId, $warning, $context);

    // Verify the warning was recorded.
    $status = $this->executionMonitor->getMonitoringStatus($executionId);
    $this->assertEquals(1, $status['metrics']['warnings']);
  }

  /**
   * Test getting monitoring status.
   *
   * @covers ::getMonitoringStatus
   */
  public function testGetMonitoringStatus(): void {
    $executionId = 'test_execution_123';
    $context = ['workflow_id' => 'test_workflow'];

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId, $context);

    // Record some activity.
    $this->executionMonitor->recordNodeExecution($executionId, 'node_1', ['execution_time' => 1.0]);
    $this->executionMonitor->recordError($executionId, new \Exception('Test error'));

    $status = $this->executionMonitor->getMonitoringStatus($executionId);

    $this->assertNotNull($status);
    $this->assertEquals($executionId, $status['execution_id']);
    $this->assertArrayHasKey('elapsed_time', $status);
    $this->assertArrayHasKey('current_memory', $status);
    $this->assertArrayHasKey('memory_delta', $status);
    $this->assertArrayHasKey('metrics', $status);
    $this->assertArrayHasKey('resource_usage', $status);
    $this->assertEquals(1, $status['metrics']['node_executions']);
    $this->assertEquals(1, $status['metrics']['errors']);
    $this->assertEquals(0, $status['metrics']['warnings']);
  }

  /**
   * Test getting monitoring status for non-monitored execution.
   *
   * @covers ::getMonitoringStatus
   */
  public function testGetMonitoringStatusNonMonitored(): void {
    $executionId = 'non_monitored_execution';

    $status = $this->executionMonitor->getMonitoringStatus($executionId);

    $this->assertNull($status);
  }

  /**
   * Test getting performance metrics.
   *
   * @covers ::getPerformanceMetrics
   */
  public function testGetPerformanceMetrics(): void {
    // Start multiple executions.
    $this->executionMonitor->startMonitoring('execution_1');
    $this->executionMonitor->startMonitoring('execution_2');

    // Record some activity.
    $this->executionMonitor->recordNodeExecution('execution_1', 'node_1', ['execution_time' => 1.0]);
    $this->executionMonitor->recordNodeExecution('execution_2', 'node_1', ['execution_time' => 2.0]);
    $this->executionMonitor->recordError('execution_1', new \Exception('Test error'));

    // Stop one execution.
    $this->executionMonitor->stopMonitoring('execution_1');

    $metrics = $this->executionMonitor->getPerformanceMetrics();

    $this->assertArrayHasKey('active_executions', $metrics);
    $this->assertArrayHasKey('completed_executions', $metrics);
    $this->assertArrayHasKey('total_execution_time', $metrics);
    $this->assertArrayHasKey('average_execution_time', $metrics);
    $this->assertArrayHasKey('total_node_executions', $metrics);
    $this->assertArrayHasKey('total_errors', $metrics);
    $this->assertArrayHasKey('total_warnings', $metrics);
    $this->assertArrayHasKey('memory_usage', $metrics);

    $this->assertEquals(1, $metrics['active_executions']);
    $this->assertEquals(1, $metrics['completed_executions']);
    $this->assertEquals(2, $metrics['total_node_executions']);
    $this->assertEquals(1, $metrics['total_errors']);
  }

  /**
   * Test getting detailed performance report.
   *
   * @covers ::getDetailedReport
   */
  public function testGetDetailedReport(): void {
    $executionId = 'test_execution_123';

    // Start monitoring and record activity.
    $this->executionMonitor->startMonitoring($executionId);
    $this->executionMonitor->recordNodeExecution($executionId, 'node_1', ['execution_time' => 1.5]);

    // Get report for active execution.
    $activeReport = $this->executionMonitor->getDetailedReport($executionId);
    $this->assertNotNull($activeReport);
    $this->assertEquals($executionId, $activeReport['execution_id']);

    // Stop monitoring and get final report.
    $finalReport = $this->executionMonitor->stopMonitoring($executionId);
    $this->assertArrayHasKey('performance_score', $finalReport);

    // Get report for completed execution.
    $completedReport = $this->executionMonitor->getDetailedReport($executionId);
    $this->assertNotNull($completedReport);
    $this->assertEquals($executionId, $completedReport['execution_id']);
  }

  /**
   * Test getting detailed report for non-existent execution.
   *
   * @covers ::getDetailedReport
   */
  public function testGetDetailedReportNonExistent(): void {
    $executionId = 'non_existent_execution';

    $report = $this->executionMonitor->getDetailedReport($executionId);

    $this->assertNull($report);
  }

  /**
   * Test clearing old metrics cache.
   *
   * @covers ::clearOldMetrics
   */
  public function testClearOldMetrics(): void {
    // Start and stop an execution to create a cache entry.
    $this->executionMonitor->startMonitoring('test_execution');
    $this->executionMonitor->stopMonitoring('test_execution');

    // Clear old metrics (should clear the cache entry).
    // Max age of 0 seconds.
    $this->executionMonitor->clearOldMetrics(0);

    // Try to get the report - should be null since it was cleared.
    $report = $this->executionMonitor->getDetailedReport('test_execution');
    $metrics = array_filter($report['metrics']);
    $this->assertEmpty($metrics);
  }

  /**
   * Test performance score calculation.
   */
  public function testPerformanceScoreCalculation(): void {
    $executionId = 'test_execution_123';

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId);

    // Record some errors and warnings.
    $this->executionMonitor->recordError($executionId, new \Exception('Test error'));
    $this->executionMonitor->recordWarning($executionId, 'Test warning');

    // Stop monitoring and check performance score.
    $report = $this->executionMonitor->stopMonitoring($executionId);

    $this->assertArrayHasKey('performance_score', $report);
    $this->assertGreaterThanOrEqual(0, $report['performance_score']);
    $this->assertLessThanOrEqual(100, $report['performance_score']);

    // Score should be reduced due to errors and warnings.
    $this->assertLessThan(100, $report['performance_score']);
  }

  /**
   * Test resource usage tracking.
   */
  public function testResourceUsageTracking(): void {
    $executionId = 'test_execution_123';

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId);

    // Record node execution with resource usage.
    $metrics = [
      'execution_time' => 1.0,
    // 1MB
      'memory_usage' => 1024 * 1024,
      'cpu_usage' => 25.5,
    // 512KB
      'disk_usage' => 512 * 1024,
    ];

    $this->executionMonitor->recordNodeExecution($executionId, 'node_1', $metrics);

    // Get status and check resource usage.
    $status = $this->executionMonitor->getMonitoringStatus($executionId);
    $this->assertArrayHasKey('resource_usage', $status);
    $this->assertArrayHasKey('memory_usage', $status['resource_usage']);
    $this->assertArrayHasKey('cpu_usage', $status['resource_usage']);
    $this->assertArrayHasKey('disk_usage', $status['resource_usage']);
  }

  /**
   * Test multiple node executions and metrics aggregation.
   */
  public function testMultipleNodeExecutions(): void {
    $executionId = 'test_execution_123';

    // Start monitoring.
    $this->executionMonitor->startMonitoring($executionId);

    // Record multiple node executions.
    $this->executionMonitor->recordNodeExecution($executionId, 'node_1', ['execution_time' => 1.0]);
    $this->executionMonitor->recordNodeExecution($executionId, 'node_2', ['execution_time' => 2.0]);
    $this->executionMonitor->recordNodeExecution($executionId, 'node_3', ['execution_time' => 1.5]);

    // Get status and verify metrics.
    $status = $this->executionMonitor->getMonitoringStatus($executionId);
    $this->assertEquals(3, $status['metrics']['node_executions']);
    $this->assertEquals(4.5, $status['metrics']['total_execution_time']);
    $this->assertEquals(1.5, $status['metrics']['average_node_time']);

    // Stop monitoring and get final report.
    $report = $this->executionMonitor->stopMonitoring($executionId);
    $this->assertEquals(3, $report['metrics']['node_executions']);
    $this->assertEquals(4.5, $report['metrics']['total_execution_time']);
  }

}
