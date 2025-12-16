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
 * Executor for Gateway/Branch nodes.
 *
 * Gateway nodes split execution paths based on conditions, allowing workflows
 * to branch into multiple execution paths. This is similar to BPMN gateways
 * and provides control flow capabilities for complex workflow logic.
 */
#[FlowDropNodeProcessor(
  id: "boolean_gateway",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Boolean Gateway"),
  type: "gateway",
  supportedTypes: ["gateway"],
  description: "Split execution paths based on conditions for workflow branching",
  category: "control",
  version: "1.0.0",
  tags: ["gateway", "branch", "control", "flow", "split", "condition"]
)]
class BooleanGateway extends AbstractFlowDropNodeProcessor {

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
    // Gateway nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $input_value = $inputs->get('value');
    try {
      $active_branch = $input_value ? 'true' : 'false';
      $this->getLogger()->info('Gateway executed: @branch', [
        '@branches' => $active_branch,
      ]);

      return [
        'active_branches' => $active_branch,
        'input_value' => $input_value,
        'execution_metadata' => [
          'timestamp' => time(),
          'gateway_type' => 'branch',
          'flow_control' => TRUE,
        ],
      ];

    }
    catch (\Exception $e) {
      $this->getLogger()->error('Gateway execution failed: @error', [
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
              'name' => 'True',
              'value' => TRUE,
            ],
            [
              'name' => 'False',
              'value' => FALSE,
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
        'value' => [
          'type' => 'boolean',
          'title' => 'Input Value',
          'description' => 'The value to evaluate against branch conditions',
          'required' => TRUE,
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
