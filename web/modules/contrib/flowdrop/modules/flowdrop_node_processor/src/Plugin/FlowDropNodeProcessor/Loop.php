<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for Loop nodes.
 */
#[FlowDropNodeProcessor(
  id: "loop",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Loop"),
  type: "default",
  supportedTypes: ["default"],
  category: "control",
  description: "Loop control structure",
  version: "1.0.0",
  tags: ["loop", "control", "iteration"]
)]
class Loop extends AbstractFlowDropNodeProcessor {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getLogger(): LoggerChannelInterface {
    return $this->loggerFactory->get('flowdrop_node_processor');
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $maxIterations = $config->getConfig('maxIterations', 10);
    $condition = $config->getConfig('condition', '');
    $delay = $config->getConfig('delay', 0);

    $iterations = 0;
    $results = [];

    // Get the input data to iterate over.
    $data = $inputs->get('data') ?: [];

    foreach ($data as $item) {
      if ($iterations >= $maxIterations) {
        break;
      }

      // Simulate processing delay.
      if ($delay > 0) {
        // Convert to microseconds.
        usleep($delay * 1000);
      }

      $results[] = [
        'iteration' => $iterations + 1,
        'item' => $item,
        'timestamp' => time(),
      ];

      $iterations++;
    }

    $this->getLogger()->info('Loop executed successfully', [
      'iterations' => $iterations,
      'max_iterations' => $maxIterations,
      'delay' => $delay,
    ]);

    return [
      'results' => $results,
      'iterations' => $iterations,
      'max_iterations' => $maxIterations,
      'condition' => $condition,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Loop nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'results' => [
          'type' => 'array',
          'description' => 'The loop results',
        ],
        'iterations' => [
          'type' => 'integer',
          'description' => 'Number of iterations performed',
        ],
        'max_iterations' => [
          'type' => 'integer',
          'description' => 'Maximum iterations allowed',
        ],
        'condition' => [
          'type' => 'string',
          'description' => 'The loop condition',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'maxIterations' => [
          'type' => 'integer',
          'title' => 'Max Iterations',
          'description' => 'Maximum number of iterations',
          'default' => 10,
        ],
        'condition' => [
          'type' => 'string',
          'title' => 'Condition',
          'description' => 'Loop condition expression',
          'default' => '',
        ],
        'delay' => [
          'type' => 'integer',
          'title' => 'Delay',
          'description' => 'Delay between iterations in milliseconds',
          'default' => 0,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'data' => [
          'type' => 'array',
          'title' => 'Data',
          'description' => 'The data to iterate over',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
