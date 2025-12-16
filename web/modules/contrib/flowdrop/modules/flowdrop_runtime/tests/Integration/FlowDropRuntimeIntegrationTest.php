<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Integration;

use Drupal\KernelTests\KernelTestBase;

/**
 * Integration tests for FlowDrop Runtime module.
 *
 * @group flowdrop_runtime
 */
class FlowDropRuntimeIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'flowdrop',
    'flowdrop_runtime',
    'flowdrop_node_processor',
    'flowdrop_workflow',
    'flowdrop_pipeline',
  ];

  /**
   * Test that runtime services can be instantiated.
   */
  public function testRuntimeServices(): void {
    // Test that services can be retrieved from container without exceptions.
    $this->container->get('flowdrop_runtime.real_time_manager');
    $this->container->get('flowdrop_runtime.node_runtime');
    $this->container->get('flowdrop_runtime.synchronous_orchestrator');
    // If we get here without exceptions, the test passes.
    // No assertion needed - if we get here, the services were instantiated
    // successfully.
    $this->expectNotToPerformAssertions();
  }

  /**
   * Test real-time status tracking.
   */
  public function testRealTimeStatusTracking(): void {
    $realTimeManager = $this->container->get('flowdrop_runtime.real_time_manager');

    // Test status tracking initialization.
    $workflow = [
      'id' => 'test_workflow',
      'nodes' => [
        ['id' => 'node1', 'type' => 'test_node'],
        ['id' => 'node2', 'type' => 'test_node'],
      ],
    ];

    $executionId = 'test_execution_' . time();
    $realTimeManager->startMonitoring($executionId, $workflow);

    // Test status updates.
    $realTimeManager->updateNodeStatus($executionId, 'node1', 'running', ['test' => 'data']);
    $realTimeManager->updateNodeStatus($executionId, 'node1', 'completed', ['result' => 'success']);

    // Verify status tracking.
    $status = $realTimeManager->getExecutionStatus($executionId);
    $this->assertNotEmpty($status);

    $nodeStatuses = $realTimeManager->getNodeStatuses($executionId);
    $this->assertCount(2, $nodeStatuses);
  }

  /**
   * Test orchestrator capabilities.
   */
  public function testOrchestratorCapabilities(): void {
    $orchestrator = $this->container->get('flowdrop_runtime.synchronous_orchestrator');

    // Test orchestrator type.
    $this->assertEquals('synchronous', $orchestrator->getType());

    // Test capabilities.
    $capabilities = $orchestrator->getCapabilities();
    $this->assertArrayHasKey('synchronous_execution', $capabilities);
    $this->assertTrue($capabilities['synchronous_execution']);

    // Test workflow support.
    $workflow = [
      'nodes' => [['id' => 'node1', 'type' => 'test']],
      'edges' => [],
    ];
    $this->assertTrue($orchestrator->supportsWorkflow($workflow));
  }

}
