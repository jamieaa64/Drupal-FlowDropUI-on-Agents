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
 * Executor for If/Else Logic nodes.
 *
 * If/Else nodes provide simple conditional logic with text input,
 * match text, and operator fields for basic string comparisons.
 */
#[FlowDropNodeProcessor(
  id: "if_else",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("If/Else"),
  type: "gateway",
  supportedTypes: ["gateway"],
  category: "logic",
  description: "Simple boolean gateway with text input, match text, and operator",
  version: "1.0.0",
  tags: ["if", "else", "conditional", "logic", "text", "comparison"]
)]
class IfElse extends AbstractFlowDropNodeProcessor {

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
    $match_text = $config->getConfig('matchText', '');
    $operator = $config->getConfig('operator', 'equals');
    $case_sensitive = $config->getConfig('caseSensitive', FALSE);

    $text_input = $inputs->getInput('data');
    try {
      $condition_result = $this->evaluateCondition($text_input, $operator, $match_text, $case_sensitive);
      $active_branch = $condition_result ? 'true' : 'false';
      $this->getLogger()->info('If/Else logic executed: @operator "@text_input" vs "@match_text" -> @result', [
        '@operator' => $operator,
        '@text_input' => $text_input,
        '@match_text' => $match_text,
        '@result' => $active_branch,
      ]);
      return [
        'active_branches' => $active_branch,
        'input_value' => $text_input,
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
   * Evaluate a condition.
   *
   * @param string $text_input
   *   The text input to evaluate.
   * @param string $operator
   *   The comparison operator.
   * @param string $match_text
   *   The text to match against.
   * @param bool $case_sensitive
   *   Whether the comparison is case sensitive.
   *
   * @return bool
   *   Whether the condition is met.
   */
  protected function evaluateCondition(string $text_input, string $operator, string $match_text, bool $case_sensitive): bool {
    // Convert to strings for string operations.
    $input_str = $text_input;
    $match_str = $match_text;

    if (!$case_sensitive) {
      $input_str = strtolower($input_str);
      $match_str = strtolower($match_str);
    }

    switch ($operator) {
      case 'equals':
        return $input_str === $match_str;

      case 'not_equals':
        return $input_str !== $match_str;

      case 'contains':
        return strpos($input_str, $match_str) !== FALSE;

      case 'starts_with':
        return strpos($input_str, $match_str) === 0;

      case 'ends_with':
        return strpos($input_str, $match_str) === (strlen($input_str) - strlen($match_str));

      case 'regex':
        return preg_match($match_str, $input_str) === 1;

      default:
        throw new \Exception("Unknown operator: {$operator}");
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
        'matchText' => [
          'type' => 'string',
          'title' => 'Match Text',
          'description' => 'The text to match against',
          'default' => '',
        ],
        'operator' => [
          'type' => 'string',
          'title' => 'Operator',
          'description' => 'The comparison operator to use',
          'default' => 'equals',
          'enum' => [
            'equals',
            'not_equals',
            'contains',
            'starts_with',
            'ends_with',
            'regex',
          ],
        ],
        'caseSensitive' => [
          'type' => 'boolean',
          'title' => 'Case Sensitive',
          'description' => 'Whether string comparisons are case sensitive',
          'default' => FALSE,
        ],
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
        'data' => [
          'type' => 'mixed',
          'title' => 'Input Data',
          'description' => 'Optional input data (if not using textInput config)',
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
