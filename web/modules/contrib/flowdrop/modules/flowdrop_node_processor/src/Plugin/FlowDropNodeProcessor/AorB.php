<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for A/B Logic nodes.
 *
 * A/B nodes provide simple conditional logic random behaviour.
 */
#[FlowDropNodeProcessor(
  id: "a_or_b",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("A/B"),
  type: "gateway",
  supportedTypes: ["gateway"],
  description: "Simple boolean gateway for A/B use case",
  category: "logic",
  version: "1.0.0",
  tags: ["if", "else", "conditional", "logic", "text", "comparison"]
)]
class AorB extends AbstractFlowDropNodeProcessor {

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
  public function validateInputs(array $inputs): bool {
    // If/Else nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {

    try {
      $condition_result = (bool) random_int(0, 1);
      $active_branch = $condition_result ? 'a' : 'b';
      $this->getLogger()->info('A/B logic executed: @branch', [
        '@result' => $active_branch,
      ]);
      return [
        'active_branches' => $active_branch,
        'input_value' => NULL,
        'execution_metadata' => [
          'timestamp' => time(),
          'gateway_type' => 'branch',
          'flow_control' => TRUE,
        ],
      ];

    }
    catch (\Exception $e) {
      $this->getLogger()->error('If/Else logic execution failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'branches' => [
          'type' => 'array',
          'description' => 'The active branches',
          'default' => [
            [
              'name' => 'A',
              'value' => 'a',
            ],
            [
              'name' => 'B',
              'value' => 'b',
            ],
          ],
          'format' => 'hidden',
          'items' => [
            'type' => 'object',
            'properties' => [
              'name' => [
                'type' => 'string',
                'description' => 'The name of the branch',
              ],
              'value' => [
                'type' => 'boolean',
                'description' => 'The value of the branch',
              ],
            ],
            'description' => 'The active branch',
          ],
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
          'type' => 'mixed',
          'title' => 'Trigger',
          'description' => 'Trigger Input',
          'required' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'gateway';
  }

}
