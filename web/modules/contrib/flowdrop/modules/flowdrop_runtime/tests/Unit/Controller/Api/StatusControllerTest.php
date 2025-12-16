<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Unit\Controller\Api;

use Drupal\Tests\UnitTestCase;
use Drupal\flowdrop_runtime\Controller\Api\StatusController;
use Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager;
use Drupal\flowdrop_runtime\Service\Monitoring\ExecutionMonitor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for StatusController.
 *
 * @coversDefaultClass \Drupal\flowdrop_runtime\Controller\Api\StatusController
 * @group flowdrop_runtime
 */
class StatusControllerTest extends UnitTestCase {

  /**
   * The status controller.
   *
   * @var \Drupal\flowdrop_runtime\Controller\Api\StatusController
   */
  private StatusController $statusController;

  /**
   * Mock real time manager.
   *
   * @var \Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager|\PHPUnit\Framework\MockObject\MockObject
   */
  private RealTimeManager $realTimeManager;

  /**
   * Mock execution monitor.
   *
   * @var \Drupal\flowdrop_runtime\Service\Monitoring\ExecutionMonitor|\PHPUnit\Framework\MockObject\MockObject
   */
  private ExecutionMonitor $executionMonitor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->realTimeManager = $this->createMock(RealTimeManager::class);
    $this->executionMonitor = $this->createMock(ExecutionMonitor::class);

    $this->statusController = new StatusController(
      $this->realTimeManager,
      $this->executionMonitor
    );
  }

  /**
   * Test controller creation.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = $this->createMock(ContainerInterface::class);

    $container->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['flowdrop_runtime.real_time_manager', $this->realTimeManager],
        ['flowdrop_runtime.execution_monitor', $this->executionMonitor],
      ]);

    $controller = StatusController::create($container);
    // Controller creation test - instance type already known.
  }

  /**
   * Test get execution status endpoint.
   *
   * @covers ::getExecutionStatus
   */
  public function testGetExecutionStatus(): void {
    $executionId = 'test_execution_123';
    $expectedStatus = [
      'status' => 'running',
      'progress' => 0.5,
      'start_time' => time(),
    ];

    $this->realTimeManager->expects($this->once())
      ->method('getExecutionStatus')
      ->with($executionId)
      ->willReturn($expectedStatus);

    $response = $this->statusController->getExecutionStatus($executionId);

    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($executionId, $data['execution_id']);
    $this->assertEquals($expectedStatus, $data['status']);
  }

  /**
   * Test get execution status with non-existent execution.
   *
   * @covers ::getExecutionStatus
   */
  public function testGetExecutionStatusNotFound(): void {
    $executionId = 'non_existent_execution';

    $this->realTimeManager->expects($this->once())
      ->method('getExecutionStatus')
      ->with($executionId)
      ->willReturn([]);

    $response = $this->statusController->getExecutionStatus($executionId);

    $this->assertEquals(404, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('Execution not found', $data['error']);
    $this->assertEquals($executionId, $data['execution_id']);
  }

  /**
   * Test get execution status with exception.
   *
   * @covers ::getExecutionStatus
   */
  public function testGetExecutionStatusException(): void {
    $executionId = 'test_execution_123';
    $errorMessage = 'Service unavailable';

    $this->realTimeManager->expects($this->once())
      ->method('getExecutionStatus')
      ->with($executionId)
      ->willThrowException(new \Exception($errorMessage));

    $response = $this->statusController->getExecutionStatus($executionId);

    $this->assertEquals(500, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($errorMessage, $data['error']);
    $this->assertEquals($executionId, $data['execution_id']);
  }

  /**
   * Test get node statuses endpoint.
   *
   * @covers ::getNodeStatuses
   */
  public function testGetNodeStatuses(): void {
    $executionId = 'test_execution_123';
    $expectedNodeStatuses = [
      'node_1' => [
        'status' => 'completed',
        'execution_time' => 1.5,
        'memory_usage' => 1024 * 1024,
      ],
      'node_2' => [
        'status' => 'running',
        'execution_time' => 0.5,
        'memory_usage' => 512 * 1024,
      ],
    ];

    $this->realTimeManager->expects($this->once())
      ->method('getNodeStatuses')
      ->with($executionId)
      ->willReturn($expectedNodeStatuses);

    $response = $this->statusController->getNodeStatuses($executionId);

    // Response type is already known from method signature.
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($executionId, $data['execution_id']);
    $this->assertEquals($expectedNodeStatuses, $data['node_statuses']);
  }

  /**
   * Test get execution details endpoint.
   *
   * @covers ::getExecutionDetails
   */
  public function testGetExecutionDetails(): void {
    $executionId = 'test_execution_123';
    $executionStatus = [
      'status' => 'running',
      'progress' => 0.75,
    ];
    $nodeStatuses = [
      'node_1' => [
        'status' => 'completed',
        'execution_time' => 1.0,
      ],
      'node_2' => [
        'status' => 'completed',
        'execution_time' => 2.0,
      ],
      'node_3' => [
        'status' => 'running',
        'execution_time' => 0.5,
      ],
    ];

    $this->realTimeManager->expects($this->once())
      ->method('getExecutionStatus')
      ->with($executionId)
      ->willReturn($executionStatus);

    $this->realTimeManager->expects($this->once())
      ->method('getNodeStatuses')
      ->with($executionId)
      ->willReturn($nodeStatuses);

    $response = $this->statusController->getExecutionDetails($executionId);

    // Response type is already known from method signature.
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($executionId, $data['execution_id']);
    $this->assertEquals($executionStatus, $data['execution_status']);
    $this->assertEquals($nodeStatuses, $data['node_statuses']);
    $this->assertArrayHasKey('metrics', $data);
    $this->assertEquals(3, $data['metrics']['total_nodes']);
    $this->assertEquals(2, $data['metrics']['completed_nodes']);
    $this->assertEquals(0, $data['metrics']['failed_nodes']);
    $this->assertEquals(1, $data['metrics']['pending_nodes']);
    $this->assertEquals(3.0, $data['metrics']['total_execution_time']);
  }

  /**
   * Test get health endpoint.
   *
   * @covers ::getHealth
   */
  public function testGetHealth(): void {
    $response = $this->statusController->getHealth();

    // Response type is already known from method signature.
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals('healthy', $data['status']);
    $this->assertArrayHasKey('timestamp', $data);
    $this->assertEquals('1.0.0', $data['version']);
    $this->assertArrayHasKey('services', $data);
    $this->assertArrayHasKey('system', $data);
    $this->assertArrayHasKey('memory_usage', $data['system']);
    $this->assertArrayHasKey('php_version', $data['system']);
  }

  /**
   * Test get system metrics endpoint.
   *
   * @covers ::getSystemMetrics
   */
  public function testGetSystemMetrics(): void {
    $expectedMetrics = [
      'active_executions' => 2,
      'completed_executions' => 10,
      'total_execution_time' => 150.5,
      'average_execution_time' => 15.05,
      'total_node_executions' => 50,
      'total_errors' => 2,
      'total_warnings' => 5,
    ];

    $this->executionMonitor->expects($this->once())
      ->method('getPerformanceMetrics')
      ->willReturn($expectedMetrics);

    $response = $this->statusController->getSystemMetrics();

    // Response type is already known from method signature.
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($expectedMetrics, $data['metrics']);
    $this->assertArrayHasKey('timestamp', $data);
    $this->assertEquals('current', $data['period']);
  }

  /**
   * Test get system metrics with exception.
   *
   * @covers ::getSystemMetrics
   */
  public function testGetSystemMetricsException(): void {
    $errorMessage = 'Metrics service unavailable';

    $this->executionMonitor->expects($this->once())
      ->method('getPerformanceMetrics')
      ->willThrowException(new \Exception($errorMessage));

    $response = $this->statusController->getSystemMetrics();

    // Response type is already known from method signature.
    $this->assertEquals(500, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertEquals($errorMessage, $data['error']);
    $this->assertArrayHasKey('timestamp', $data);
  }

}
