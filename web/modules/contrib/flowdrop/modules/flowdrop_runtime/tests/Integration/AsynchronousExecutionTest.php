<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Integration;

use Drupal\KernelTests\KernelTestBase;
use Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator;
use Drupal\flowdrop_workflow\FlowDropWorkflowInterface;

/**
 * Integration test for asynchronous execution capabilities.
 *
 * @group flowdrop_runtime
 */
class AsynchronousExecutionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'text',
    'flowdrop',
    'flowdrop_workflow',
    'flowdrop_pipeline',
    'flowdrop_job',
    'flowdrop_node_type',
    'flowdrop_runtime',
  ];

  /**
   * The asynchronous orchestrator service.
   *
   * @var \Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator
   */
  protected $asynchronousOrchestrator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('flowdrop_workflow');
    $this->installEntitySchema('flowdrop_pipeline');
    $this->installEntitySchema('flowdrop_job');
    $this->installEntitySchema('flowdrop_node_type');

    $this->asynchronousOrchestrator = $this->container->get('flowdrop_runtime.asynchronous_orchestrator');
  }

  /**
   * Test that the asynchronous orchestrator service is available.
   */
  public function testAsynchronousOrchestratorService(): void {
    $this->assertInstanceOf(AsynchronousOrchestrator::class, $this->asynchronousOrchestrator);
    $this->assertEquals('asynchronous', $this->asynchronousOrchestrator->getType());
  }

  /**
   * Test queue configuration is properly loaded.
   */
  public function testQueueConfiguration(): void {
    $queue_factory = $this->container->get('queue');

    // Test pipeline execution queue.
    $pipeline_queue = $queue_factory->get('flowdrop_runtime_pipeline_execution');
    $this->assertNotNull($pipeline_queue);

    // Test job execution queue.
    $job_queue = $queue_factory->get('flowdrop_runtime_job_execution');
    $this->assertNotNull($job_queue);
  }

  /**
   * Test orchestrator type registration.
   */
  public function testOrchestratorRegistration(): void {
    $orchestrator_manager = $this->container->get('flowdrop_runtime.orchestrator_manager');

    // Check if asynchronous orchestrator is registered.
    $definitions = $orchestrator_manager->getDefinitions();
    $this->assertArrayHasKey('asynchronous', $definitions);

    // Test creating the orchestrator instance.
    $instance = $orchestrator_manager->createInstance('asynchronous');
    $this->assertInstanceOf(AsynchronousOrchestrator::class, $instance);
  }

  /**
   * Test pipeline creation from workflow.
   */
  public function testPipelineCreation(): void {
    // Create a simple workflow for testing.
    $workflow = $this->createMockWorkflow();

    $workflow_array = [
      'id' => $workflow->id(),
      'label' => $workflow->label(),
      'nodes' => $workflow->getNodes(),
      'edges' => $workflow->getEdges(),
    ];
    $initial_data = ['test_input' => 'test_value'];
    $options = ['max_concurrent_jobs' => 3];

    $pipeline = $this->asynchronousOrchestrator->createPipelineFromWorkflow($workflow_array, $initial_data, $options);

    $this->assertNotNull($pipeline);
    $this->assertEquals($workflow->id(), $pipeline->getWorkflowId());
    $this->assertEquals('pending', $pipeline->getStatus());
    $this->assertEquals(3, $pipeline->getMaxConcurrentJobs());
  }

  /**
   * Create a mock workflow for testing.
   *
   * @return \Drupal\flowdrop_workflow\FlowDropWorkflowInterface
   *   A mock workflow.
   */
  protected function createMockWorkflow() {
    $workflow_storage = $this->container->get('entity_type.manager')->getStorage('flowdrop_workflow');

    $workflow = $workflow_storage->create([
      'id' => 'test_workflow_' . uniqid(),
      'label' => 'Test Workflow',
      'nodes' => [
        [
          'id' => 'node1',
          'data' => [
            'type' => 'input',
            'label' => 'Input Node',
          ],
        ],
        [
          'id' => 'node2',
          'data' => [
            'type' => 'output',
            'label' => 'Output Node',
          ],
        ],
      ],
      'edges' => [
        [
          'source' => 'node1',
          'target' => 'node2',
        ],
      ],
    ]);

    $workflow->save();

    assert($workflow instanceof FlowDropWorkflowInterface);
    return $workflow;
  }

}
