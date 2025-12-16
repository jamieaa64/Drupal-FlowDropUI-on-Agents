<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_pipeline\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\flowdrop_pipeline\Service\JobGenerationService;

/**
 * Test the job generation service.
 *
 * @group flowdrop_pipeline
 */
class JobGenerationServiceTest extends KernelTestBase {

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
  ];

  /**
   * The job generation service.
   *
   * @var \Drupal\flowdrop_pipeline\Service\JobGenerationService
   */
  protected $jobGenerationService;

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

    $this->jobGenerationService = $this->container->get('flowdrop_pipeline.job_generation');
  }

  /**
   * Test job generation service is available.
   */
  public function testJobGenerationService(): void {
    $this->assertInstanceOf(JobGenerationService::class, $this->jobGenerationService);
  }

  /**
   * Test dependency graph building.
   */
  public function testDependencyGraph(): void {
    $nodes = [
      ['id' => 'node1'],
      ['id' => 'node2'],
      ['id' => 'node3'],
    ];

    $edges = [
      ['source' => 'node1', 'target' => 'node2'],
      ['source' => 'node2', 'target' => 'node3'],
    ];

    // Use reflection to test protected method.
    $reflection = new \ReflectionClass($this->jobGenerationService);
    $method = $reflection->getMethod('buildDependencyGraph');
    $method->setAccessible(TRUE);

    $dependency_graph = $method->invoke($this->jobGenerationService, $nodes, $edges);

    $expected = [
      'node1' => [],
      'node2' => ['node1'],
      'node3' => ['node2'],
    ];

    $this->assertEquals($expected, $dependency_graph);
  }

  /**
   * Test execution order calculation.
   */
  public function testExecutionOrder(): void {
    $dependency_graph = [
      'node1' => [],
      'node2' => ['node1'],
      'node3' => ['node2'],
      'node4' => ['node1'],
    ];

    // Use reflection to test protected method.
    $reflection = new \ReflectionClass($this->jobGenerationService);
    $method = $reflection->getMethod('calculateExecutionOrder');
    $method->setAccessible(TRUE);

    $execution_order = $method->invoke($this->jobGenerationService, $dependency_graph);

    // node1 should be first (no dependencies)
    $this->assertEquals(0, $execution_order['node1']);

    // node2 and node4 should be after node1.
    $this->assertGreaterThan($execution_order['node1'], $execution_order['node2']);
    $this->assertGreaterThan($execution_order['node1'], $execution_order['node4']);

    // node3 should be after node2.
    $this->assertGreaterThan($execution_order['node2'], $execution_order['node3']);
  }

  /**
   * Test ready jobs calculation.
   */
  public function testGetReadyJobs(): void {
    // Create a mock workflow.
    $workflow = $this->createMockWorkflow();

    // Create a pipeline.
    $pipeline = $this->createMockPipeline($workflow);

    // Generate jobs.
    $jobs = $this->jobGenerationService->generateJobs($pipeline);

    // Initially, only jobs with no dependencies should be ready.
    $ready_jobs = $this->jobGenerationService->getReadyJobs($pipeline);

    // Should have at least one ready job (the input node)
    $this->assertNotEmpty($ready_jobs);

    // Verify pipeline contains the jobs.
    $pipeline_jobs = $pipeline->getJobs();
    $this->assertNotEmpty($pipeline_jobs);
    $this->assertEquals(count($jobs), count($pipeline_jobs));

    // Ready jobs should be sorted by priority.
    $priorities = array_map(fn($job) => $job->getPriority(), $ready_jobs);
    $sorted_priorities = $priorities;
    sort($sorted_priorities);
    $this->assertEquals($sorted_priorities, $priorities);
  }

  /**
   * Create a mock workflow for testing.
   */
  protected function createMockWorkflow() {
    $workflow_storage = $this->container->get('entity_type.manager')->getStorage('flowdrop_workflow');

    $workflow = $workflow_storage->create([
      'id' => 'test_workflow_' . uniqid(),
      'label' => 'Test Workflow',
      'nodes' => [
        [
          'id' => 'input1',
          'data' => [
            'type' => 'input',
            'label' => 'Input Node',
          ],
        ],
        [
          'id' => 'process1',
          'data' => [
            'type' => 'process',
            'label' => 'Process Node',
          ],
        ],
        [
          'id' => 'output1',
          'data' => [
            'type' => 'output',
            'label' => 'Output Node',
          ],
        ],
      ],
      'edges' => [
        [
          'source' => 'input1',
          'target' => 'process1',
        ],
        [
          'source' => 'process1',
          'target' => 'output1',
        ],
      ],
    ]);

    $workflow->save();
    return $workflow;
  }

  /**
   * Create a mock pipeline for testing.
   */
  protected function createMockPipeline($workflow) {
    $pipeline_storage = $this->container->get('entity_type.manager')->getStorage('flowdrop_pipeline');

    $pipeline = $pipeline_storage->create([
      'label' => 'Test Pipeline',
      'bundle' => 'default',
      'workflow_id' => ['target_id' => $workflow->id()],
      'input_data' => json_encode(['test' => 'data']),
      'status' => 'pending',
    ]);

    $pipeline->save();
    return $pipeline;
  }

}
