<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Compiler;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop_runtime\DTO\Compiler\CompiledWorkflow;
use Drupal\flowdrop_runtime\DTO\Compiler\ExecutionPlan;
use Drupal\flowdrop_runtime\DTO\Compiler\NodeMapping;
use Drupal\flowdrop_workflow\DTO\WorkflowDTO;
use Drupal\flowdrop_runtime\Exception\CompilationException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Compiles workflow entities into executable DAGs.
 */
class WorkflowCompiler {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  /**
   * Constructs the WorkflowCompiler.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Compile a workflow entity into an executable DAG.
   *
   * @param array<string, mixed>|WorkflowDTO $workflow
   *   The workflow definition array or WorkflowDTO.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Compiler\CompiledWorkflow
   *   The compiled workflow with execution plan.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\CompilationException
   *   When compilation fails due to validation errors.
   */
  public function compile(array|WorkflowDTO $workflow): CompiledWorkflow {
    // Convert array to WorkflowDTO if needed.
    if (is_array($workflow)) {
      $workflowDTO = WorkflowDTO::fromArray($workflow);
    }
    else {
      $workflowDTO = $workflow;
    }

    $this->logger->info('Compiling workflow @id', [
      '@id' => $workflowDTO->getId(),
    ]);

    try {
      // Validate workflow structure.
      $this->validateWorkflowStructure($workflowDTO);

      // Build dependency graph from DTO.
      $dependencyGraph = $this->buildDependencyGraphFromDto($workflowDTO);

      // Detect circular dependencies.
      $this->detectCircularDependencies($dependencyGraph);

      // Generate execution plan.
      $executionPlan = $this->generateExecutionPlanFromDto($workflowDTO, $dependencyGraph);

      // Create node mappings from DTO.
      $nodeMappings = $this->createNodeMappingsFromDto($workflowDTO);

      $compiledWorkflow = new CompiledWorkflow(
        workflowId: $workflowDTO->getId(),
        executionPlan: $executionPlan,
        nodeMappings: $nodeMappings,
        dependencyGraph: $dependencyGraph,
        metadata: [
          'node_count' => $workflowDTO->getNodeCount(),
          'edge_count' => $workflowDTO->getEdgeCount(),
          'compilation_timestamp' => time(),
        ]
      );

      $this->logger->info('Successfully compiled workflow @id with @nodes nodes', [
        '@id' => $workflowDTO->getId(),
        '@nodes' => $workflowDTO->getNodeCount(),
      ]);

      return $compiledWorkflow;

    }
    catch (\Exception $e) {
      $this->logger->error('Workflow compilation failed: @error', [
        '@error' => $e->getMessage(),
        '@workflow_id' => $workflowDTO->getId(),
      ]);

      throw new CompilationException(
        "Workflow compilation failed: " . $e->getMessage(),
        0,
        $e
      );
    }
  }

  /**
   * Validate workflow structure using DTO.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Workflow\WorkflowDTO $workflow
   *   The workflow DTO.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\CompilationException
   *   When workflow structure is invalid.
   */
  private function validateWorkflowStructure(WorkflowDTO $workflow): void {
    if (empty($workflow->getId())) {
      throw new CompilationException('Workflow must have an ID');
    }

    if ($workflow->getNodeCount() === 0) {
      throw new CompilationException('Workflow must have at least one node');
    }

    // Validate each node has required properties.
    foreach ($workflow->getNodes() as $node) {
      if (empty($node->getId())) {
        throw new CompilationException('All nodes must have an ID');
      }

      if (empty($node->getTypeId())) {
        throw new CompilationException("Node {$node->getId()} must have a type");
      }
    }
  }

  /**
   * Build dependency graph from WorkflowDTO.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Workflow\WorkflowDTO $workflow
   *   The workflow DTO.
   *
   * @return array<string, array<string, mixed>>
   *   The dependency graph.
   */
  private function buildDependencyGraphFromDto(WorkflowDTO $workflow): array {
    $graph = [];

    // Initialize graph with all nodes.
    foreach ($workflow->getNodes() as $nodeId => $node) {
      $graph[$nodeId] = [
        'dependencies' => [],
        'dependents' => [],
        'in_degree' => 0,
      ];
    }

    // Build connections from edges.
    foreach ($workflow->getEdges() as $edge) {
      $sourceId = $edge->getSource();
      $targetId = $edge->getTarget();

      if (empty($sourceId) || empty($targetId)) {
        continue;
      }

      // Validate that both nodes exist.
      if (!isset($graph[$sourceId]) || !isset($graph[$targetId])) {
        $this->logger->warning('Edge references non-existent node: @source -> @target', [
          '@source' => $sourceId,
          '@target' => $targetId,
        ]);
        continue;
      }

      // Add dependency relationship.
      $graph[$sourceId]['dependents'][] = $targetId;
      $graph[$targetId]['dependencies'][] = $sourceId;
      $graph[$targetId]['in_degree']++;
    }

    return $graph;
  }

  /**
   * Detect circular dependencies using DFS.
   *
   * Detecting circular dependencies using Depth-First Search
   * (DFS) involves modeling your dependencies as a
   * directed graph and then using a modified DFS
   * algorithm to identify cycles within that graph.
   *
   * @param array $dependencyGraph
   *   The dependency graph.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\CompilationException
   *   When circular dependencies are detected.
   */
  private function detectCircularDependencies(array $dependencyGraph): void {
    $visited = [];
    $recursionStack = [];

    foreach (array_keys($dependencyGraph) as $nodeId) {
      if (!isset($visited[$nodeId])) {
        if ($this->hasCycle($nodeId, $dependencyGraph, $visited, $recursionStack)) {
          throw new CompilationException(
            'Circular dependency detected in workflow'
          );
        }
      }
    }
  }

  /**
   * Check for cycles using DFS.
   *
   * @param string $nodeId
   *   The current node ID.
   * @param array $graph
   *   The dependency graph.
   * @param array $visited
   *   Visited nodes.
   * @param array $recursionStack
   *   Current recursion stack.
   *
   * @return bool
   *   True if cycle is detected.
   */
  private function hasCycle(string $nodeId, array $graph, array &$visited, array &$recursionStack): bool {
    $visited[$nodeId] = TRUE;
    $recursionStack[$nodeId] = TRUE;

    foreach ($graph[$nodeId]['dependents'] ?? [] as $dependentId) {
      if (!isset($visited[$dependentId])) {
        if ($this->hasCycle($dependentId, $graph, $visited, $recursionStack)) {
          return TRUE;
        }
      }
      elseif (isset($recursionStack[$dependentId]) && $recursionStack[$dependentId]) {
        return TRUE;
      }
    }

    $recursionStack[$nodeId] = FALSE;
    return FALSE;
  }

  /**
   * Generate execution plan using topological sort from WorkflowDTO.
   *
   * Create a valid sequence of tasks or operations where
   * all dependencies are respected. The process involves
   * modeling the tasks as a directed acyclic graph (DAG),
   * where nodes are tasks and edges indicate that one task
   * must be completed before another.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Workflow\WorkflowDTO $workflow
   *   The workflow DTO.
   * @param array<string, array<string, mixed>> $dependencyGraph
   *   The dependency graph.
   *
   * @return \Drupal\flowdrop_runtime\DTO\Compiler\ExecutionPlan
   *   The execution plan.
   */
  private function generateExecutionPlanFromDto(WorkflowDTO $workflow, array $dependencyGraph): ExecutionPlan {
    $executionOrder = [];
    $inputMappings = [];
    $outputMappings = [];

    // Topological sort for execution order.
    $queue = [];
    $inDegree = [];

    // Initialize in-degree counts.
    foreach ($dependencyGraph as $nodeId => $nodeData) {
      $inDegree[$nodeId] = $nodeData['in_degree'];
      if ($nodeData['in_degree'] === 0) {
        $queue[] = $nodeId;
      }
    }

    // Process nodes in topological order.
    while (!empty($queue)) {
      $nodeId = array_shift($queue);
      $executionOrder[] = $nodeId;

      // Process dependents.
      foreach ($dependencyGraph[$nodeId]['dependents'] ?? [] as $dependentId) {
        $inDegree[$dependentId]--;
        if ($inDegree[$dependentId] === 0) {
          $queue[] = $dependentId;
        }
      }
    }

    // Generate input/output mappings using WorkflowDTO edge information.
    foreach ($executionOrder as $nodeId) {
      $dependencies = $dependencyGraph[$nodeId]['dependencies'] ?? [];

      // Input mappings: map dependency outputs to this node's inputs.
      $inputMappings[$nodeId] = [];
      foreach ($dependencies as $dependencyId) {
        // Get the specific edge info for more precise mappings.
        $incomingEdges = $workflow->getIncomingEdges($nodeId);
        $relevantEdges = array_filter(
          $incomingEdges,
          fn($edge) => $edge->getSource() === $dependencyId
        );

        foreach ($relevantEdges as $edge) {
          $inputMappings[$nodeId][$dependencyId] = [
            'type' => 'output',
            'source_node' => $dependencyId,
            'source_output' => $edge->getSourceOutputName(),
            'target_input' => $edge->getTargetInputName(),
            'is_trigger' => $edge->isTrigger(),
            'branch_name' => $edge->getBranchName(),
          ];
        }

        // Fallback if no edge found (shouldn't happen).
        if (empty($inputMappings[$nodeId][$dependencyId])) {
          $inputMappings[$nodeId][$dependencyId] = [
            'type' => 'output',
            'source_node' => $dependencyId,
            'target_input' => 'default',
            'is_trigger' => FALSE,
            'branch_name' => '',
          ];
        }
      }

      // Output mappings: map this node's outputs to dependents.
      $outputMappings[$nodeId] = [];
      foreach ($dependencyGraph[$nodeId]['dependents'] ?? [] as $dependentId) {
        $outgoingEdges = $workflow->getOutgoingEdges($nodeId);
        $relevantEdges = array_filter(
          $outgoingEdges,
          fn($edge) => $edge->getTarget() === $dependentId
        );

        foreach ($relevantEdges as $edge) {
          $outputMappings[$nodeId][$dependentId] = [
            'type' => 'input',
            'target_node' => $dependentId,
            'source_output' => $edge->getSourceOutputName(),
            'target_input' => $edge->getTargetInputName(),
            'is_trigger' => $edge->isTrigger(),
            'branch_name' => $edge->getBranchName(),
          ];
        }

        // Fallback if no edge found.
        if (empty($outputMappings[$nodeId][$dependentId])) {
          $outputMappings[$nodeId][$dependentId] = [
            'type' => 'input',
            'target_node' => $dependentId,
            'source_output' => 'default',
            'is_trigger' => FALSE,
            'branch_name' => '',
          ];
        }
      }
    }

    return new ExecutionPlan(
      executionOrder: $executionOrder,
      inputMappings: $inputMappings,
      outputMappings: $outputMappings,
      metadata: [
        'total_nodes' => count($executionOrder),
        'leaf_nodes' => count(array_filter($dependencyGraph, fn($node) => empty($node['dependents']))),
        'root_nodes' => count(array_filter($dependencyGraph, fn($node) => empty($node['dependencies']))),
      ]
    );
  }

  /**
   * Create node mappings for processor plugins from WorkflowDTO.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Workflow\WorkflowDTO $workflow
   *   The workflow DTO.
   *
   * @return array<string, NodeMapping>
   *   Array of NodeMapping objects keyed by node ID.
   */
  private function createNodeMappingsFromDto(WorkflowDTO $workflow): array {
    $nodeMappings = [];

    foreach ($workflow->getNodes() as $nodeId => $node) {
      $nodeMappings[$nodeId] = new NodeMapping(
        nodeId: $nodeId,
        processorId: $node->getTypeId(),
        config: $node->getConfig(),
        metadata: [
          'label' => $node->getLabel(),
          'description' => $node->getMetadata()['description'] ?? '',
          'category' => $node->getMetadata()['category'] ?? '',
          'inputs' => $node->getInputs(),
          'outputs' => $node->getOutputs(),
        ]
      );
    }

    return $nodeMappings;
  }

  /**
   * Validate a compiled workflow.
   *
   * @param \Drupal\flowdrop_runtime\DTO\Compiler\CompiledWorkflow $compiledWorkflow
   *   The compiled workflow to validate.
   *
   * @return bool
   *   True if valid.
   */
  public function validateCompiledWorkflow(CompiledWorkflow $compiledWorkflow): bool {
    $executionPlan = $compiledWorkflow->getExecutionPlan();
    $nodeMappings = $compiledWorkflow->getNodeMappings();

    // Check that all nodes in execution order have mappings.
    foreach ($executionPlan->getExecutionOrder() as $nodeId) {
      if (!isset($nodeMappings[$nodeId])) {
        $this->logger->error('Execution plan references unmapped node: @node_id', [
          '@node_id' => $nodeId,
        ]);
        return FALSE;
      }
    }

    // Check that all mapped nodes are in execution order.
    foreach (array_keys($nodeMappings) as $nodeId) {
      if (!in_array($nodeId, $executionPlan->getExecutionOrder())) {
        $this->logger->error('Mapped node not in execution order: @node_id', [
          '@node_id' => $nodeId,
        ]);
        return FALSE;
      }
    }

    return TRUE;
  }

}
