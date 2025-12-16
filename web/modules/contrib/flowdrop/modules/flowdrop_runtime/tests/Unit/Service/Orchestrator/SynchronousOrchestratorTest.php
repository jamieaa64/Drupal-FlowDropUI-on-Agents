<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Unit\Service\Orchestrator;

use Drupal\Tests\UnitTestCase;
use Drupal\flowdrop\DTO\Input;
use Drupal\flowdrop\DTO\Config;
use Drupal\flowdrop\DTO\Output;
use Drupal\flowdrop_runtime\Service\Orchestrator\SynchronousOrchestrator;
use Drupal\flowdrop_runtime\Service\Runtime\NodeRuntimeService;
use Drupal\flowdrop_runtime\Service\Runtime\ExecutionContext;
use Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager;
use Drupal\flowdrop_runtime\Service\Compiler\WorkflowCompiler;
use Drupal\flowdrop_runtime\DTO\Compiler\CompiledWorkflow;
use Drupal\flowdrop_runtime\DTO\Compiler\ExecutionPlan;
use Drupal\flowdrop_runtime\DTO\Compiler\NodeMapping;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionResult;
use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationRequest;
use Drupal\flowdrop_workflow\DTO\WorkflowDTO;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the SynchronousOrchestrator service.
 *
 * @coversDefaultClass \Drupal\flowdrop_runtime\Service\Orchestrator\SynchronousOrchestrator
 * @group flowdrop_runtime
 */
class SynchronousOrchestratorTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The orchestrator under test.
   *
   * @var \Drupal\flowdrop_runtime\Service\Orchestrator\SynchronousOrchestrator
   */
  private SynchronousOrchestrator $orchestrator;

  /**
   * Mock services.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\flowdrop_runtime\Service\Runtime\NodeRuntimeService>
   */
  private $nodeRuntime;

  /**
   * Mock execution context service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\flowdrop_runtime\Service\Runtime\ExecutionContext>
   */
  private $executionContext;

  /**
   * Mock real-time manager service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager>
   */
  private $realTimeManager;

  /**
   * Mock workflow compiler service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\flowdrop_runtime\Service\Compiler\WorkflowCompiler>
   */
  private $workflowCompiler;

  /**
   * Mock logger factory service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Logger\LoggerChannelFactoryInterface>
   */
  private $loggerFactory;

  /**
   * Mock logger service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Logger\LoggerChannelInterface>
   */
  private $logger;

  /**
   * Mock event dispatcher service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Symfony\Component\EventDispatcher\EventDispatcherInterface>
   */
  private $eventDispatcher;

  /**
   * Tracks the order in which nodes are executed.
   *
   * @var array<string>
   */
  private array $executedNodeOrder = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Reset execution order tracking.
    $this->executedNodeOrder = [];

    // Create mock services.
    $this->nodeRuntime = $this->prophesize(NodeRuntimeService::class);
    $this->executionContext = $this->prophesize(ExecutionContext::class);
    $this->realTimeManager = $this->prophesize(RealTimeManager::class);
    $this->workflowCompiler = $this->prophesize(WorkflowCompiler::class);
    $this->loggerFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->logger = $this->prophesize(LoggerChannelInterface::class);
    $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

    // Setup logger factory.
    // PHPStan doesn't recognize LoggerChannelFactoryInterface::get() method.
    // @phpstan-ignore-next-line
    $this->loggerFactory->get("flowdrop_runtime")->willReturn($this->logger->reveal());

    // Create the orchestrator.
    $this->orchestrator = new SynchronousOrchestrator(
      $this->nodeRuntime->reveal(),
      $this->executionContext->reveal(),
      $this->realTimeManager->reveal(),
      $this->workflowCompiler->reveal(),
      $this->loggerFactory->reveal(),
      $this->eventDispatcher->reveal()
    );
  }

  /**
   * @covers ::getType
   */
  public function testGetType(): void {
    $this->assertEquals("synchronous", $this->orchestrator->getType());
  }

  /**
   * @covers ::supportsWorkflow
   */
  public function testSupportsWorkflow(): void {
    $workflow = ["id" => "test_workflow"];
    $this->assertTrue($this->orchestrator->supportsWorkflow($workflow));
  }

  /**
   * @covers ::getCapabilities
   */
  public function testGetCapabilities(): void {
    $capabilities = $this->orchestrator->getCapabilities();

    $this->assertArrayHasKey("synchronous_execution", $capabilities);
    $this->assertArrayHasKey("parallel_execution", $capabilities);
    $this->assertArrayHasKey("async_execution", $capabilities);
    $this->assertArrayHasKey("retry_support", $capabilities);
    $this->assertArrayHasKey("error_recovery", $capabilities);
    $this->assertArrayHasKey("workflow_compilation", $capabilities);

    $this->assertTrue($capabilities["synchronous_execution"]);
    $this->assertFalse($capabilities["parallel_execution"]);
    $this->assertFalse($capabilities["async_execution"]);
    $this->assertTrue($capabilities["retry_support"]);
    $this->assertTrue($capabilities["error_recovery"]);
    $this->assertTrue($capabilities["workflow_compilation"]);
  }

  /**
   * Tests linear workflow execution order: A → B → C.
   *
   * This test verifies that nodes in a simple linear chain are executed
   * in the correct topological order.
   *
   * @covers ::orchestrate
   */
  public function testLinearWorkflowExecutionOrder(): void {
    // Define a linear workflow: node_a → node_b → node_c.
    $workflow = $this->createLinearWorkflow();
    $expectedOrder = ["node_a", "node_b", "node_c"];

    // Setup mocks for successful execution.
    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    // Create orchestration request.
    $request = new OrchestrationRequest(
      workflowId: "test_linear_workflow",
      pipelineId: "pipeline_linear_1",
      workflow: $workflow,
      initialData: ["initial_value" => "test"],
      options: [],
    );

    // Execute orchestration.
    $response = $this->orchestrator->orchestrate($request);

    // Assert execution was successful.
    $this->assertEquals("completed", $response->getStatus());
    $this->assertCount(3, $response->getResults());

    // Assert nodes were executed in correct order.
    $this->assertEquals(
      $expectedOrder,
      $this->executedNodeOrder,
      "Nodes should be executed in topological order: A → B → C"
    );
  }

  /**
   * Tests parallel branches workflow: A → (B, C) → D.
   *
   * This test verifies that when a node has multiple dependencies,
   * all dependencies are executed before the dependent node.
   *
   * @covers ::orchestrate
   */
  public function testParallelBranchesWorkflowExecutionOrder(): void {
    // Define workflow: node_a → node_b, node_a → node_c,
    // (node_b, node_c) → node_d.
    $workflow = $this->createParallelBranchesWorkflow();
    // Valid topological orders: [a, b, c, d] or [a, c, b, d].
    $expectedOrder = ["node_a", "node_b", "node_c", "node_d"];

    // Setup mocks.
    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    // Create orchestration request.
    $request = new OrchestrationRequest(
      workflowId: "test_parallel_workflow",
      pipelineId: "pipeline_parallel_1",
      workflow: $workflow,
      initialData: [],
      options: [],
    );

    // Execute orchestration.
    $response = $this->orchestrator->orchestrate($request);

    // Assert execution was successful.
    $this->assertEquals("completed", $response->getStatus());
    $this->assertCount(4, $response->getResults());

    // Assert node_a is executed first.
    $this->assertEquals(
      "node_a",
      $this->executedNodeOrder[0],
      "Node A (root) should be executed first"
    );

    // Assert node_d is executed last (depends on both B and C).
    $this->assertEquals(
      "node_d",
      end($this->executedNodeOrder),
      "Node D should be executed last as it depends on B and C"
    );

    // Assert B and C are executed before D.
    $dIndex = array_search("node_d", $this->executedNodeOrder, TRUE);
    $bIndex = array_search("node_b", $this->executedNodeOrder, TRUE);
    $cIndex = array_search("node_c", $this->executedNodeOrder, TRUE);

    $this->assertLessThan(
      $dIndex,
      $bIndex,
      "Node B should be executed before Node D"
    );
    $this->assertLessThan(
      $dIndex,
      $cIndex,
      "Node C should be executed before Node D"
    );
  }

  /**
   * Tests diamond workflow: A → (B, C), B → D, C → D.
   *
   * This tests the classic diamond dependency pattern.
   *
   * @covers ::orchestrate
   */
  public function testDiamondWorkflowExecutionOrder(): void {
    // Diamond pattern: A splits to B and C, both merge to D.
    $workflow = $this->createDiamondWorkflow();
    $expectedOrder = ["node_a", "node_b", "node_c", "node_d"];

    // Setup mocks.
    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    // Create orchestration request.
    $request = new OrchestrationRequest(
      workflowId: "test_diamond_workflow",
      pipelineId: "pipeline_diamond_1",
      workflow: $workflow,
      initialData: [],
      options: [],
    );

    // Execute orchestration.
    $response = $this->orchestrator->orchestrate($request);

    // Assert execution was successful.
    $this->assertEquals("completed", $response->getStatus());

    // Verify A is first and D is last.
    $this->assertEquals(
      "node_a",
      $this->executedNodeOrder[0],
      "Node A should be executed first"
    );
    $this->assertEquals(
      "node_d",
      end($this->executedNodeOrder),
      "Node D should be executed last"
    );

    // Verify B and C are both executed after A but before D.
    $aIndex = array_search("node_a", $this->executedNodeOrder, TRUE);
    $bIndex = array_search("node_b", $this->executedNodeOrder, TRUE);
    $cIndex = array_search("node_c", $this->executedNodeOrder, TRUE);
    $dIndex = array_search("node_d", $this->executedNodeOrder, TRUE);

    $this->assertGreaterThan($aIndex, $bIndex);
    $this->assertGreaterThan($aIndex, $cIndex);
    $this->assertLessThan($dIndex, $bIndex);
    $this->assertLessThan($dIndex, $cIndex);
  }

  /**
   * Tests single node workflow execution.
   *
   * @covers ::orchestrate
   */
  public function testSingleNodeWorkflowExecution(): void {
    $workflow = [
      "id" => "single_node_workflow",
      "nodes" => [
        ["id" => "node_a", "type" => "processor_a", "config" => []],
      ],
      "connections" => [],
    ];
    $expectedOrder = ["node_a"];

    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    $request = new OrchestrationRequest(
      workflowId: "single_node_workflow",
      pipelineId: "pipeline_single_1",
      workflow: $workflow,
      initialData: [],
      options: [],
    );

    $response = $this->orchestrator->orchestrate($request);

    $this->assertEquals("completed", $response->getStatus());
    $this->assertCount(1, $this->executedNodeOrder);
    $this->assertEquals("node_a", $this->executedNodeOrder[0]);
  }

  /**
   * Tests workflow with multiple root nodes: (A, B) → C.
   *
   * @covers ::orchestrate
   */
  public function testMultipleRootNodesWorkflowExecutionOrder(): void {
    $workflow = [
      "id" => "multi_root_workflow",
      "nodes" => [
        ["id" => "node_a", "type" => "processor_a", "config" => []],
        ["id" => "node_b", "type" => "processor_b", "config" => []],
        ["id" => "node_c", "type" => "processor_c", "config" => []],
      ],
      "connections" => [
        ["source" => "node_a", "target" => "node_c"],
        ["source" => "node_b", "target" => "node_c"],
      ],
    ];
    // Both A and B are roots, order between them can vary.
    $expectedOrder = ["node_a", "node_b", "node_c"];

    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    $request = new OrchestrationRequest(
      workflowId: "multi_root_workflow",
      pipelineId: "pipeline_multi_root_1",
      workflow: $workflow,
      initialData: [],
      options: [],
    );

    $response = $this->orchestrator->orchestrate($request);

    $this->assertEquals("completed", $response->getStatus());

    // Node C must be executed after both A and B.
    $cIndex = array_search("node_c", $this->executedNodeOrder, TRUE);
    $aIndex = array_search("node_a", $this->executedNodeOrder, TRUE);
    $bIndex = array_search("node_b", $this->executedNodeOrder, TRUE);

    $this->assertGreaterThan(
      $aIndex,
      $cIndex,
      "Node C should be executed after Node A"
    );
    $this->assertGreaterThan(
      $bIndex,
      $cIndex,
      "Node C should be executed after Node B"
    );
  }

  /**
   * Tests that orchestration response contains correct metadata.
   *
   * @covers ::orchestrate
   */
  public function testOrchestrationResponseMetadata(): void {
    $workflow = $this->createLinearWorkflow();
    $expectedOrder = ["node_a", "node_b", "node_c"];

    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    $request = new OrchestrationRequest(
      workflowId: "test_metadata_workflow",
      pipelineId: "pipeline_metadata_1",
      workflow: $workflow,
      initialData: [],
      options: [],
    );

    $response = $this->orchestrator->orchestrate($request);

    // Verify response structure.
    $this->assertNotEmpty($response->getExecutionId());
    $this->assertEquals("completed", $response->getStatus());
    $this->assertGreaterThan(0, $response->getExecutionTime());

    // Verify metadata.
    $metadata = $response->getMetadata();
    $this->assertEquals("synchronous", $metadata["orchestrator_type"]);
    $this->assertEquals(3, $metadata["nodes_executed"]);
  }

  /**
   * Tests gateway workflow where TRUE branch is taken.
   *
   * Workflow: Input → IfElse → (True: ProcessA, False: ProcessB) → Output
   * When IfElse outputs active_branches="true", only ProcessA should execute.
   *
   * @covers ::orchestrate
   */
  public function testGatewayWorkflowTrueBranchExecuted(): void {
    $workflow = $this->createGatewayWorkflow();
    // Execution order includes all nodes, but gateway logic will skip inactive.
    $expectedOrder = ["input_node", "if_else_node", "true_branch_node", "false_branch_node", "output_node"];

    // Setup mocks with gateway output for TRUE branch.
    $this->setupMocksForGatewayWorkflow($workflow, $expectedOrder, "true");

    $request = new OrchestrationRequest(
      workflowId: "test_gateway_workflow",
      pipelineId: "pipeline_gateway_1",
      workflow: $workflow,
      initialData: ["test_value" => "hello"],
      options: [],
    );

    $response = $this->orchestrator->orchestrate($request);

    // Verify execution completed.
    $this->assertEquals("completed", $response->getStatus());

    // Verify metadata shows skipped nodes.
    $metadata = $response->getMetadata();
    $this->assertArrayHasKey("nodes_skipped", $metadata);
    $this->assertArrayHasKey("skipped_node_ids", $metadata);

    // Verify only the expected nodes were executed (not false_branch_node).
    $this->assertContains("input_node", $this->executedNodeOrder);
    $this->assertContains("if_else_node", $this->executedNodeOrder);
    $this->assertContains("true_branch_node", $this->executedNodeOrder);
    $this->assertNotContains("false_branch_node", $this->executedNodeOrder);

    // Output node should execute (it connects to the true branch via trigger).
    $this->assertContains("output_node", $this->executedNodeOrder);
  }

  /**
   * Tests gateway workflow where FALSE branch is taken.
   *
   * @covers ::orchestrate
   */
  public function testGatewayWorkflowFalseBranchExecuted(): void {
    $workflow = $this->createGatewayWorkflow();
    $expectedOrder = ["input_node", "if_else_node", "true_branch_node", "false_branch_node", "output_node"];

    // Setup mocks with gateway output for FALSE branch.
    $this->setupMocksForGatewayWorkflow($workflow, $expectedOrder, "false");

    $request = new OrchestrationRequest(
      workflowId: "test_gateway_workflow_false",
      pipelineId: "pipeline_gateway_2",
      workflow: $workflow,
      initialData: ["test_value" => "goodbye"],
      options: [],
    );

    $response = $this->orchestrator->orchestrate($request);

    $this->assertEquals("completed", $response->getStatus());

    // Verify false branch was executed, true branch was skipped.
    $this->assertContains("input_node", $this->executedNodeOrder);
    $this->assertContains("if_else_node", $this->executedNodeOrder);
    $this->assertContains("false_branch_node", $this->executedNodeOrder);
    $this->assertNotContains("true_branch_node", $this->executedNodeOrder);
  }

  /**
   * Tests that nodes without triggers always execute.
   *
   * A node connected only via data edges (no trigger) should execute
   * regardless of gateway state.
   *
   * @covers ::orchestrate
   */
  public function testNonTriggerNodesAlwaysExecute(): void {
    $workflow = [
      "id" => "non_trigger_workflow",
      "nodes" => [
        ["id" => "node_a", "type" => "processor_a", "config" => []],
        ["id" => "node_b", "type" => "processor_b", "config" => []],
      ],
      // Data edge, not trigger edge (no -input-trigger in handle).
      "edges" => [
        [
          "source" => "node_a",
          "target" => "node_b",
          "sourceHandle" => "node_a-output-data",
          "targetHandle" => "node_b-input-data",
        ],
      ],
    ];
    $expectedOrder = ["node_a", "node_b"];

    $this->setupMocksForWorkflow($workflow, $expectedOrder);

    $request = new OrchestrationRequest(
      workflowId: "non_trigger_workflow",
      pipelineId: "pipeline_non_trigger_1",
      workflow: $workflow,
      initialData: [],
      options: [],
    );

    $response = $this->orchestrator->orchestrate($request);

    $this->assertEquals("completed", $response->getStatus());
    $this->assertEquals(["node_a", "node_b"], $this->executedNodeOrder);
  }

  /**
   * Creates a linear workflow: A → B → C.
   *
   * @return array<string, mixed>
   *   The workflow definition array.
   */
  private function createLinearWorkflow(): array {
    return [
      "id" => "linear_workflow",
      "nodes" => [
        ["id" => "node_a", "type" => "processor_a", "config" => ["setting" => "value_a"]],
        ["id" => "node_b", "type" => "processor_b", "config" => ["setting" => "value_b"]],
        ["id" => "node_c", "type" => "processor_c", "config" => ["setting" => "value_c"]],
      ],
      "connections" => [
        ["source" => "node_a", "target" => "node_b"],
        ["source" => "node_b", "target" => "node_c"],
      ],
    ];
  }

  /**
   * Creates a parallel branches workflow: A → (B, C) → D.
   *
   * @return array<string, mixed>
   *   The workflow definition array.
   */
  private function createParallelBranchesWorkflow(): array {
    return [
      "id" => "parallel_workflow",
      "nodes" => [
        ["id" => "node_a", "type" => "processor_a", "config" => []],
        ["id" => "node_b", "type" => "processor_b", "config" => []],
        ["id" => "node_c", "type" => "processor_c", "config" => []],
        ["id" => "node_d", "type" => "processor_d", "config" => []],
      ],
      "connections" => [
        ["source" => "node_a", "target" => "node_b"],
        ["source" => "node_a", "target" => "node_c"],
        ["source" => "node_b", "target" => "node_d"],
        ["source" => "node_c", "target" => "node_d"],
      ],
    ];
  }

  /**
   * Creates a diamond workflow: A → (B, C), B → D, C → D.
   *
   * @return array<string, mixed>
   *   The workflow definition array.
   */
  private function createDiamondWorkflow(): array {
    return [
      "id" => "diamond_workflow",
      "nodes" => [
        ["id" => "node_a", "type" => "processor_a", "config" => []],
        ["id" => "node_b", "type" => "processor_b", "config" => []],
        ["id" => "node_c", "type" => "processor_c", "config" => []],
        ["id" => "node_d", "type" => "processor_d", "config" => []],
      ],
      "connections" => [
        ["source" => "node_a", "target" => "node_b"],
        ["source" => "node_a", "target" => "node_c"],
        ["source" => "node_b", "target" => "node_d"],
        ["source" => "node_c", "target" => "node_d"],
      ],
    ];
  }

  /**
   * Creates a gateway workflow: Input → IfElse → (True: A, False: B) → Output.
   *
   * This workflow tests gateway/conditional branching:
   * - input_node: Starting node
   * - if_else_node: Gateway that outputs active_branches
   * - true_branch_node: Executes only if active_branches="true"
   * - false_branch_node: Executes only if active_branches="false"
   * - output_node: Executes after the active branch.
   *
   * @return array<string, mixed>
   *   The workflow definition array.
   */
  private function createGatewayWorkflow(): array {
    return [
      "id" => "gateway_workflow",
      "nodes" => [
        ["id" => "input_node", "type" => "input", "config" => []],
        ["id" => "if_else_node", "type" => "if_else", "config" => ["matchText" => "hello", "operator" => "equals"]],
        ["id" => "true_branch_node", "type" => "processor_a", "config" => []],
        ["id" => "false_branch_node", "type" => "processor_b", "config" => []],
        ["id" => "output_node", "type" => "output", "config" => []],
      ],
      "edges" => [
        // Input → IfElse (data edge).
        [
          "id" => "edge_1",
          "source" => "input_node",
          "target" => "if_else_node",
          "sourceHandle" => "input_node-output-data",
          "targetHandle" => "if_else_node-input-data",
        ],
        // IfElse → True branch (trigger edge with branch name "True").
        [
          "id" => "edge_2",
          "source" => "if_else_node",
          "target" => "true_branch_node",
          "sourceHandle" => "if_else_node-output-True",
          "targetHandle" => "true_branch_node-input-trigger",
        ],
        // IfElse → False branch (trigger edge with branch name "False").
        [
          "id" => "edge_3",
          "source" => "if_else_node",
          "target" => "false_branch_node",
          "sourceHandle" => "if_else_node-output-False",
          "targetHandle" => "false_branch_node-input-trigger",
        ],
        // True branch → Output (trigger edge).
        [
          "id" => "edge_4",
          "source" => "true_branch_node",
          "target" => "output_node",
          "sourceHandle" => "true_branch_node-output-data",
          "targetHandle" => "output_node-input-trigger",
        ],
        // False branch → Output (trigger edge).
        [
          "id" => "edge_5",
          "source" => "false_branch_node",
          "target" => "output_node",
          "sourceHandle" => "false_branch_node-output-data",
          "targetHandle" => "output_node-input-trigger",
        ],
      ],
    ];
  }

  /**
   * Sets up all mocks needed for workflow execution.
   *
   * @param array<string, mixed> $workflow
   *   The workflow definition.
   * @param array<string> $expectedExecutionOrder
   *   The expected node execution order.
   */
  private function setupMocksForWorkflow(array $workflow, array $expectedExecutionOrder): void {
    // Create node mappings for all nodes in the workflow.
    $nodeMappings = [];
    foreach ($workflow["nodes"] as $node) {
      $nodeMappings[$node["id"]] = new NodeMapping(
        nodeId: $node["id"],
        processorId: $node["type"],
        config: $node["config"] ?? [],
        metadata: ["label" => $node["id"]]
      );
    }

    // Create input mappings based on connections.
    $inputMappings = [];
    foreach ($workflow["nodes"] as $node) {
      $inputMappings[$node["id"]] = [];
    }
    foreach ($workflow["connections"] ?? [] as $connection) {
      $targetId = $connection["target"];
      $sourceId = $connection["source"];
      $inputMappings[$targetId][$sourceId] = [
        "type" => "output",
        "source_node" => $sourceId,
        "target_input" => "default",
      ];
    }

    // Create execution plan with expected order.
    $executionPlan = new ExecutionPlan(
      executionOrder: $expectedExecutionOrder,
      inputMappings: $inputMappings,
      outputMappings: [],
      metadata: ["total_nodes" => count($expectedExecutionOrder)]
    );

    // Create compiled workflow.
    $compiledWorkflow = new CompiledWorkflow(
      workflowId: $workflow["id"],
      executionPlan: $executionPlan,
      nodeMappings: $nodeMappings,
      dependencyGraph: [],
      metadata: []
    );

    // Setup workflow compiler mock.
    $this->workflowCompiler->compile(Argument::type(WorkflowDTO::class))
      ->willReturn($compiledWorkflow);

    // Create mock execution context.
    $mockContext = new NodeExecutionContext(
      workflowId: $workflow["id"],
      pipelineId: "test_pipeline",
      initialData: []
    );

    // Setup execution context mock.
    $this->executionContext->createContext(
      Argument::type("string"),
      Argument::type("string"),
      Argument::type("array")
    )->willReturn($mockContext);

    $this->executionContext->updateContext(
      Argument::type(NodeExecutionContext::class),
      Argument::type("string"),
      Argument::any()
    )->will(function ($args) {
      return $args[0];
    });

    // Setup node runtime mock to track execution order.
    $executedNodeOrder = &$this->executedNodeOrder;
    $this->nodeRuntime->executeNode(
      Argument::type("string"),
      Argument::type("string"),
      Argument::type("string"),
      Argument::type(Input::class),
      Argument::type(Config::class),
      Argument::type(NodeExecutionContext::class)
    )->will(function ($args) use (&$executedNodeOrder, $mockContext) {
      // Track the node ID that was executed.
      $nodeId = $args[1];
      $nodeType = $args[2];
      $executedNodeOrder[] = $nodeId;

      // Create mock output.
      $output = new Output([
        "status" => "success",
        "result" => "output_from_{$nodeId}",
      ]);

      // Return mock result.
      return new NodeExecutionResult(
        nodeId: $nodeId,
        nodeType: $nodeType,
        status: "success",
        output: $output,
        executionTime: 0.01,
        timestamp: time(),
        context: $mockContext
      );
    });

    // Setup real-time manager mock (these are side effects we don't verify).
    $this->realTimeManager->startMonitoring(
      Argument::type("string"),
      Argument::type("array")
    )->shouldBeCalled();

    $this->realTimeManager->updateExecutionStatus(
      Argument::type("string"),
      Argument::type("string"),
      Argument::type("array")
    )->shouldBeCalled();

    // Setup event dispatcher mock.
    $this->eventDispatcher->dispatch(
      Argument::any(),
      Argument::type("string")
    )->willReturn(Argument::any());

    // Setup logger mock (allow any logging).
    // Note: These methods return void, so we don't specify willReturn().
    $this->logger->info(Argument::any(), Argument::any())->shouldBeCalled();
    $this->logger->error(Argument::any(), Argument::any());
    $this->logger->debug(Argument::any(), Argument::any());
  }

  /**
   * Sets up mocks for gateway workflow execution.
   *
   * This is similar to setupMocksForWorkflow but specifically handles
   * the IfElse node to output the correct active_branches value.
   *
   * @param array<string, mixed> $workflow
   *   The workflow definition.
   * @param array<string> $expectedExecutionOrder
   *   The expected node execution order (before gateway filtering).
   * @param string $activeBranch
   *   The branch to activate ("true" or "false").
   */
  private function setupMocksForGatewayWorkflow(
    array $workflow,
    array $expectedExecutionOrder,
    string $activeBranch,
  ): void {
    // Create node mappings for all nodes in the workflow.
    $nodeMappings = [];
    foreach ($workflow["nodes"] as $node) {
      $nodeMappings[$node["id"]] = new NodeMapping(
        nodeId: $node["id"],
        processorId: $node["type"],
        config: $node["config"] ?? [],
        metadata: ["label" => $node["id"]]
      );
    }

    // Create input mappings based on edges.
    $inputMappings = [];
    foreach ($workflow["nodes"] as $node) {
      $inputMappings[$node["id"]] = [];
    }
    foreach ($workflow["edges"] ?? [] as $edge) {
      $targetId = $edge["target"];
      $sourceId = $edge["source"];
      $inputMappings[$targetId][$sourceId] = [
        "type" => "output",
        "source_node" => $sourceId,
        "target_input" => "default",
      ];
    }

    // Create execution plan with expected order.
    $executionPlan = new ExecutionPlan(
      executionOrder: $expectedExecutionOrder,
      inputMappings: $inputMappings,
      outputMappings: [],
      metadata: ["total_nodes" => count($expectedExecutionOrder)]
    );

    // Create compiled workflow.
    $compiledWorkflow = new CompiledWorkflow(
      workflowId: $workflow["id"],
      executionPlan: $executionPlan,
      nodeMappings: $nodeMappings,
      dependencyGraph: [],
      metadata: []
    );

    // Setup workflow compiler mock.
    $this->workflowCompiler->compile(Argument::type(WorkflowDTO::class))
      ->willReturn($compiledWorkflow);

    // Create mock execution context.
    $mockContext = new NodeExecutionContext(
      workflowId: $workflow["id"],
      pipelineId: "test_pipeline",
      initialData: []
    );

    // Setup execution context mock.
    $this->executionContext->createContext(
      Argument::type("string"),
      Argument::type("string"),
      Argument::type("array")
    )->willReturn($mockContext);

    $this->executionContext->updateContext(
      Argument::type(NodeExecutionContext::class),
      Argument::type("string"),
      Argument::any()
    )->will(function ($args) {
      return $args[0];
    });

    // Setup node runtime mock to track execution order and return gateway
    // output.
    $executedNodeOrder = &$this->executedNodeOrder;
    $this->nodeRuntime->executeNode(
      Argument::type("string"),
      Argument::type("string"),
      Argument::type("string"),
      Argument::type(Input::class),
      Argument::type(Config::class),
      Argument::type(NodeExecutionContext::class)
    )->will(function ($args) use (&$executedNodeOrder, $mockContext, $activeBranch) {
      // Track the node ID that was executed.
      $nodeId = $args[1];
      $nodeType = $args[2];
      $executedNodeOrder[] = $nodeId;

      // Create mock output.
      // For the if_else_node, include active_branches.
      $outputData = [
        "status" => "success",
        "result" => "output_from_{$nodeId}",
      ];

      // If this is the gateway node, add active_branches output.
      if ($nodeType === "if_else") {
        $outputData["active_branches"] = $activeBranch;
      }

      $output = new Output($outputData);

      // Return mock result.
      return new NodeExecutionResult(
        nodeId: $nodeId,
        nodeType: $nodeType,
        status: "success",
        output: $output,
        executionTime: 0.01,
        timestamp: time(),
        context: $mockContext
      );
    });

    // Setup real-time manager mock.
    $this->realTimeManager->startMonitoring(
      Argument::type("string"),
      Argument::type("array")
    )->shouldBeCalled();

    $this->realTimeManager->updateExecutionStatus(
      Argument::type("string"),
      Argument::type("string"),
      Argument::type("array")
    )->shouldBeCalled();

    // Setup event dispatcher mock.
    $this->eventDispatcher->dispatch(
      Argument::any(),
      Argument::type("string")
    )->willReturn(Argument::any());

    // Setup logger mock.
    $this->logger->info(Argument::any(), Argument::any())->shouldBeCalled();
    $this->logger->error(Argument::any(), Argument::any());
    $this->logger->debug(Argument::any(), Argument::any());
  }

}
