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
 * Executor for Conditional Logic nodes.
 *
 * Conditional nodes act as control flow elements that evaluate conditions
 * and determine which execution path to follow. They don't produce data
 * outputs like other nodes, but rather control the flow of execution.
 */
#[FlowDropNodeProcessor(
  id: "conditional",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Conditional"),
  type: "conditional",
  supportedTypes: ["conditional"],
  description: "Evaluate conditions and control execution flow",
  category: "control",
  version: "1.0.0",
  tags: ["conditional", "control", "flow", "logic"]
)]
class Conditional extends AbstractFlowDropNodeProcessor {

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
    // Conditional nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $input_value = $inputs->get('value') ?? $inputs->get('text') ?? $inputs->get('data');
    $operator = $config->getConfig('operator', 'equals');
    $compare_value = $config->getConfig('compareValue', '');
    $case_sensitive = $config->getConfig('caseSensitive', FALSE);
    $default_branch = $config->getConfig('defaultBranch', 'false');

    try {
      $condition_result = $this->evaluateCondition($input_value, $operator, $compare_value, $case_sensitive);

      // Determine which branch to follow based on condition result.
      $branch_to_follow = $condition_result ? 'true' : 'false';

      // If condition fails and we have a default branch, use it.
      if (!$condition_result && $default_branch !== 'false') {
        $branch_to_follow = $default_branch;
      }

      $this->getLogger()->info('Conditional logic executed: @condition -> @result -> @branch', [
        '@condition' => $operator,
        '@result' => $condition_result ? 'true' : 'false',
        '@branch' => $branch_to_follow,
      ]);

      // Return control flow information rather than data.
      return [
        'condition_evaluated' => TRUE,
        'condition_result' => $condition_result,
        'branch_to_follow' => $branch_to_follow,
        'operator' => $operator,
        'input_value' => $input_value,
        'compare_value' => $compare_value,
        'case_sensitive' => $case_sensitive,
        'default_branch' => $default_branch,
        'execution_metadata' => [
          'timestamp' => time(),
          'condition_type' => 'conditional',
          'flow_control' => TRUE,
        ],
      ];

    }
    catch (\Exception $e) {
      $this->getLogger()->error('Conditional logic execution failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Evaluate a condition.
   *
   * @param mixed $input_value
   *   The input value to evaluate.
   * @param string $operator
   *   The comparison operator.
   * @param mixed $compare_value
   *   The value to compare against.
   * @param bool $case_sensitive
   *   Whether the comparison is case sensitive.
   *
   * @return bool
   *   Whether the condition is met.
   */
  protected function evaluateCondition($input_value, string $operator, $compare_value, bool $case_sensitive): bool {
    // Convert to strings for string operations.
    $input_str = (string) $input_value;
    $compare_str = (string) $compare_value;

    if (!$case_sensitive) {
      $input_str = strtolower($input_str);
      $compare_str = strtolower($compare_str);
    }

    switch ($operator) {
      case 'equals':
        return $input_str === $compare_str;

      case 'not_equals':
        return $input_str !== $compare_str;

      case 'contains':
        return strpos($input_str, $compare_str) !== FALSE;

      case 'not_contains':
        return strpos($input_str, $compare_str) === FALSE;

      case 'starts_with':
        return strpos($input_str, $compare_str) === 0;

      case 'ends_with':
        return strpos($input_str, $compare_str) === (strlen($input_str) - strlen($compare_str));

      case 'greater_than':
        return is_numeric($input_value) && is_numeric($compare_value) && $input_value > $compare_value;

      case 'less_than':
        return is_numeric($input_value) && is_numeric($compare_value) && $input_value < $compare_value;

      case 'greater_than_or_equal':
        return is_numeric($input_value) && is_numeric($compare_value) && $input_value >= $compare_value;

      case 'less_than_or_equal':
        return is_numeric($input_value) && is_numeric($compare_value) && $input_value <= $compare_value;

      case 'is_empty':
        return empty($input_value);

      case 'is_not_empty':
        return !empty($input_value);

      case 'is_null':
        return $input_value === NULL;

      case 'is_not_null':
        return $input_value !== NULL;

      case 'regex_match':
        return preg_match($compare_value, $input_str) === 1;

      default:
        throw new \Exception("Unknown operator: {$operator}");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    // Conditional nodes don't produce data outputs - they control flow
    // Return an empty schema to indicate no data output.
    return [
      'type' => 'object',
      'description' => 'Conditional nodes control execution flow and do not produce data outputs',
      'properties' => [
        'condition_evaluated' => [
          'type' => 'boolean',
          'description' => 'Whether the condition was successfully evaluated',
        ],
        'condition_result' => [
          'type' => 'boolean',
          'description' => 'The result of the condition evaluation',
        ],
        'branch_to_follow' => [
          'type' => 'string',
          'description' => 'Which execution branch should be followed',
          'enum' => ['true', 'false'],
        ],
        'operator' => [
          'type' => 'string',
          'description' => 'The comparison operator used',
        ],
        'input_value' => [
          'type' => 'mixed',
          'description' => 'The input value that was evaluated',
        ],
        'compare_value' => [
          'type' => 'mixed',
          'description' => 'The value the input was compared against',
        ],
        'case_sensitive' => [
          'type' => 'boolean',
          'description' => 'Whether the comparison was case sensitive',
        ],
        'default_branch' => [
          'type' => 'string',
          'description' => 'The default branch to follow when condition fails',
        ],
        'execution_metadata' => [
          'type' => 'object',
          'description' => 'Metadata about the execution',
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
        'operator' => [
          'type' => 'string',
          'title' => 'Operator',
          'description' => 'The comparison operator to use',
          'default' => 'equals',
          'enum' => [
            'equals',
            'not_equals',
            'contains',
            'not_contains',
            'starts_with',
            'ends_with',
            'greater_than',
            'less_than',
            'greater_than_or_equal',
            'less_than_or_equal',
            'is_empty',
            'is_not_empty',
            'is_null',
            'is_not_null',
            'regex_match',
          ],
        ],
        'compareValue' => [
          'type' => 'mixed',
          'title' => 'Compare Value',
          'description' => 'The value to compare against',
          'default' => '',
        ],
        'caseSensitive' => [
          'type' => 'boolean',
          'title' => 'Case Sensitive',
          'description' => 'Whether string comparisons are case sensitive',
          'default' => FALSE,
        ],
        'defaultBranch' => [
          'type' => 'string',
          'title' => 'Default Branch',
          'description' => 'Default branch to follow when condition fails',
          'default' => 'false',
          'enum' => ['true', 'false'],
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Optional description for this conditional node',
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
          'description' => 'The value to evaluate',
          'required' => FALSE,
        ],
        'text' => [
          'type' => 'string',
          'title' => 'Text Input',
          'description' => 'Text value to evaluate',
          'required' => FALSE,
        ],
        'data' => [
          'type' => 'mixed',
          'title' => 'Data Input',
          'description' => 'Data value to evaluate',
          'required' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'conditional';
  }

}
