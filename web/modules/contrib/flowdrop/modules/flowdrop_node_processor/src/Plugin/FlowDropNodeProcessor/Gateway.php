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
  id: "gateway",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Gateway"),
  type: "gateway",
  supportedTypes: ["gateway"],
  description: "Split execution paths based on conditions for workflow branching",
  category: "control",
  version: "1.0.0",
  tags: ["gateway", "branch", "control", "flow", "split", "condition"]
)]
class Gateway extends AbstractFlowDropNodeProcessor {

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
    $branches = $config->getConfig('branches', []);
    $default_branch = $config->getConfig('defaultBranch', '');
    try {
      $active_branches = $this->evaluateBranches($input_value, $branches, $default_branch);

      $this->getLogger()->info('Gateway executed: @branches', [
        '@branches' => implode(', ', $active_branches),
      ]);

      return [
        'active_branches' => $active_branches,
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
   * Evaluate branches based on gateway type and conditions.
   *
   * @param string $input_value
   *   The input value to evaluate.
   * @param array $branches
   *   Array of branch configurations.
   * @param string $default_branch
   *   The default branch to follow if no conditions are met.
   *
   * @return array
   *   Array of active branch names.
   */
  protected function evaluateBranches($input_value, array $branches, string $default_branch): array {
    $active_branches = [];
    // Multiple branches can be active (all conditions that match)
    foreach ($branches as $branch) {
      if ($this->evaluateBranchCondition($input_value, $branch)) {
        $active_branches[] = $branch['name'];
      }
    }
    // If no branches are active and we have a default branch, use it.
    if (empty($active_branches) && !empty($default_branch)) {
      $active_branches[] = $default_branch;
    }

    return $active_branches;
  }

  /**
   * Evaluate a single branch condition.
   *
   * @param string $input_value
   *   The input value to evaluate.
   * @param array $branch
   *   The branch configuration.
   *
   * @return bool
   *   Whether the branch condition is met.
   */
  protected function evaluateBranchCondition($input_value, array $branch): bool {
    $condition = $branch['condition'] ?? [];
    $compare_value = $condition['value'] ?? '';
    // Convert to strings for string operations.
    $input_str = (string) $input_value;
    $compare_str = (string) $compare_value;
    return $input_str === $compare_str;
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
          'title' => 'Branches',
          'description' => 'Array of branch configurations',
          'format' => 'hidden',
          'items' => [
            'type' => 'object',
            'properties' => [
              'name' => [
                'type' => 'string',
                'title' => 'Branch Identifier',
                'description' => 'Unique name for this branch',
              ],
              'value' => [
                'type' => 'string',
                'title' => 'Value',
                'description' => 'Input value that activates the branch',
              ],
            ],
          ],
        ],
        'defaultBranch' => [
          'type' => 'string',
          'title' => 'Default Branch name',
          'description' => 'Default branch name to execute if no conditions are met',
          'default' => '',
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Optional description for this gateway node',
          'default' => '',
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
          'type' => 'mixed',
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
