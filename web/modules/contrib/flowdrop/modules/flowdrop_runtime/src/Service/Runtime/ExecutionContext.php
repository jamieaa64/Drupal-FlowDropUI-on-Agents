<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Runtime;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop\DTO\OutputInterface;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Manages execution context for workflow runs.
 */
class ExecutionContext {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Create a new execution context.
   */
  public function createContext(
    string $workflowId,
    string $pipelineId,
    array $initialData = [],
  ): NodeExecutionContext {
    return new NodeExecutionContext(
      workflowId: $workflowId,
      pipelineId: $pipelineId,
      initialData: $initialData,
    );
  }

  /**
   * Update context with node output.
   */
  public function updateContext(
    NodeExecutionContext $context,
    string $nodeId,
    OutputInterface $output,
  ): NodeExecutionContext {
    $context->addNodeOutput($nodeId, $output);

    $this->logger->debug('Updated execution context for node @node_id', [
      '@node_id' => $nodeId,
    ]);

    return $context;
  }

  /**
   * Get input data for a node based on its dependencies.
   */
  public function getNodeInputData(
    NodeExecutionContext $context,
    array $nodeDependencies,
  ): array {
    $inputData = $context->getInitialData();

    // Add outputs from dependent nodes.
    foreach ($nodeDependencies as $dependencyNodeId) {
      $dependencyOutput = $context->getNodeOutput($dependencyNodeId);
      if ($dependencyOutput) {
        $inputData = array_merge($inputData, $dependencyOutput->toArray());
      }
    }

    return $inputData;
  }

  /**
   * Validate execution context.
   */
  public function validateContext(NodeExecutionContext $context): bool {
    if (empty($context->getWorkflowId())) {
      $this->logger->error('Execution context missing workflow ID');
      return FALSE;
    }

    if (empty($context->getPipelineId())) {
      $this->logger->error('Execution context missing pipeline ID');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get execution summary.
   */
  public function getExecutionSummary(NodeExecutionContext $context): array {
    return [
      'workflow_id' => $context->getWorkflowId(),
      'pipeline_id' => $context->getPipelineId(),
      'node_count' => count($context->getNodeOutputs()),
      'metadata' => $context->getMetadata(),
    ];
  }

}
