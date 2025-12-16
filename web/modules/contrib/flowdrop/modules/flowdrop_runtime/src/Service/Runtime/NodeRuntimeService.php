<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Runtime;

use Psr\Log\LoggerInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionContext;
use Drupal\flowdrop_runtime\DTO\Runtime\NodeExecutionResult;
use Drupal\flowdrop_runtime\Exception\RuntimeException;
use Drupal\flowdrop_runtime\Service\RealTime\RealTimeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Core node runtime service with real-time updates.
 */
class NodeRuntimeService {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly object $nodeProcessorManager,
    private readonly RealTimeManager $realTimeManager,
  ) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Execute a single node with real-time updates.
   */
  public function executeNode(
    string $executionId,
    string $nodeId,
    string $nodeType,
    InputInterface $inputs,
    ConfigInterface $config,
    NodeExecutionContext $context,
  ): NodeExecutionResult {
    $this->logger->info('Executing node @node_id of type @node_type', [
      '@node_id' => $nodeId,
      '@node_type' => $nodeType,
    ]);

    // Update node status to running.
    $this->realTimeManager->updateNodeStatus($executionId, $nodeId, 'running', [
      'node_type' => $nodeType,
      'start_time' => time(),
    ]);

    $startTime = microtime(TRUE);

    try {
      /** @var \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface $node */
      $node = \Drupal::service('entity_type.manager')->getStorage('flowdrop_node_type')->load($nodeType);
      // Get the node processor plugin.
      $processor = $this->nodeProcessorManager->createInstance($node->getExecutorPlugin());

      // Validate inputs before execution.
      if (!$processor->validateInputs($inputs->toArray())) {
        // Throw new RuntimeException("Invalid inputs for node $nodeId");.
      }

      // Execute the node.
      $output = $processor->execute($inputs, $config);
      $executionTime = microtime(TRUE) - $startTime;

      // Create execution result.
      $result = new NodeExecutionResult(
        nodeId: $nodeId,
        nodeType: $nodeType,
        status: $output->getStatus(),
        output: $output,
        executionTime: $executionTime,
        timestamp: time(),
        context: $context
      );

      // Update node status to complete.
      $this->realTimeManager->updateNodeStatus($executionId, $nodeId, 'completed', [
        'execution_time' => $executionTime,
        'output_size' => strlen(serialize($output->toArray())),
        'end_time' => time(),
      ]);

      // Dispatch node execution completed event.
      $event = new GenericEvent($result, [
        'execution_id' => $executionId,
        'node_id' => $nodeId,
        'execution_time' => $executionTime,
        'timestamp' => time(),
      ]);
      $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.node.completed');

      $this->logger->info('Node @node_id executed successfully in @time seconds', [
        '@node_id' => $nodeId,
        '@time' => round($executionTime, 3),
      ]);

      return $result;

    }
    catch (\Exception $e) {
      $executionTime = microtime(TRUE) - $startTime;

      // Update node status to failed.
      $this->realTimeManager->updateNodeStatus($executionId, $nodeId, 'failed', [
        'error' => $e->getMessage(),
        'execution_time' => $executionTime,
        'end_time' => time(),
      ]);

      $this->logger->error('Node @node_id execution failed: @error', [
        '@node_id' => $nodeId,
        '@error' => $e->getMessage(),
      ]);

      throw new RuntimeException(
        "Node execution failed for $nodeId: " . $e->getMessage(),
        0,
        $e
      );
    }
  }

  /**
   * Execute multiple nodes in sequence with real-time updates.
   */
  public function executeNodes(
    string $executionId,
    array $nodes,
    NodeExecutionContext $context,
  ): array {
    $results = [];

    foreach ($nodes as $node) {
      $result = $this->executeNode(
        $executionId,
        $node['id'],
        $node['type'],
        $node['inputs'],
        $node['config'],
        $context
      );

      $results[$node['id']] = $result;

      // Update context with node output for next nodes.
      $context->addNodeOutput($node['id'], $result->getOutput());
    }

    return $results;
  }

  /**
   * Validate node before execution.
   */
  public function validateNode(string $nodeType, array $inputs): bool {
    try {
      $processor = $this->nodeProcessorManager->createInstance($nodeType);
      return $processor->validateInputs($inputs);
    }
    catch (\Exception $e) {
      $this->logger->error('Node validation failed for type @type: @error', [
        '@type' => $nodeType,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Get node processor information.
   */
  public function getNodeProcessorInfo(string $nodeType): array {
    try {
      $processor = $this->nodeProcessorManager->createInstance($nodeType);
      return [
        'id' => $processor->getPluginId(),
        'name' => $processor->getName(),
        'description' => $processor->getDescription(),
        'category' => $processor->getCategory(),
        'version' => $processor->getVersion(),
        'input_schema' => $processor->getInputSchema(),
        'output_schema' => $processor->getOutputSchema(),
        'config_schema' => $processor->getConfigSchema(),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get processor info for type @type: @error', [
        '@type' => $nodeType,
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

}
