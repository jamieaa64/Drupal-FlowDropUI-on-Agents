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
 * Executor for Calculator nodes.
 */
#[FlowDropNodeProcessor(
  id: "calculator",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Calculator"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Perform mathematical operations on numeric values",
  version: "1.0.0",
  tags: ["math", "calculation", "numeric", "processing"]
)]
class Calculator extends AbstractFlowDropNodeProcessor {

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
    // Check if we have numeric values to work with.
    $values = $inputs['values'] ?? [];
    return !empty($values) && is_array($values);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $operation = $config->getConfig('operation', 'add');
    $precision = $config->getConfig('precision', 2);

    // Get input values.
    $values = $inputs->get('values', []);

    // Ensure we have at least 2 values for most operations.
    if (count($values) < 2) {
      $values = [0, 0];
    }

    $result = 0;
    switch ($operation) {
      case 'add':
        $result = array_sum($values);
        break;

      case 'subtract':
        $result = $values[0];
        for ($i = 1; $i < count($values); $i++) {
          $result -= $values[$i];
        }
        break;

      case 'multiply':
        $result = array_product($values);
        break;

      case 'divide':
        $result = $values[0];
        for ($i = 1; $i < count($values); $i++) {
          if ($values[$i] != 0) {
            $result /= $values[$i];
          }
          else {
            $result = INF;
            break;
          }
        }
        break;

      case 'power':
        $result = pow($values[0], $values[1]);
        break;

      case 'sqrt':
        $result = sqrt($values[0]);
        break;

      case 'average':
        $result = array_sum($values) / count($values);
        break;

      case 'min':
        $result = min($values);
        break;

      case 'max':
        $result = max($values);
        break;

      case 'median':
        $result = $this->calculateMedian($values);
        break;

      case 'mode':
        $result = $this->calculateMode($values);
        break;
    }

    $result = round($result, $precision);

    $this->getLogger()->info('Calculator executed successfully', [
      'operation' => $operation,
      'values_count' => count($values),
      'result' => $result,
    ]);

    return [
      'result' => $result,
      'operation' => $operation,
      'values' => $values,
      'precision' => $precision,
    ];
  }

  /**
   * Calculate the median of an array of values.
   *
   * @param array $values
   *   The array of numeric values.
   *
   * @return float
   *   The median value.
   */
  private function calculateMedian(array $values): float {
    if (empty($values)) {
      return 0.0;
    }

    // Sort the values.
    sort($values);
    $count = count($values);

    if ($count % 2 === 0) {
      // Even number of elements - average of two middle values.
      $middle1 = $values[($count / 2) - 1];
      $middle2 = $values[$count / 2];
      return ($middle1 + $middle2) / 2;
    }
    else {
      // Odd number of elements - middle value.
      return $values[($count - 1) / 2];
    }
  }

  /**
   * Calculate the mode of an array of values.
   *
   * @param array $values
   *   The array of numeric values.
   *
   * @return float
   *   The mode value (most frequent value).
   */
  private function calculateMode(array $values): float {
    if (empty($values)) {
      return 0.0;
    }

    // Count occurrences of each value.
    $counts = array_count_values($values);

    // Find the value with the highest count.
    $maxCount = max($counts);
    $modes = array_keys($counts, $maxCount, TRUE);

    // Return the first mode if multiple exist.
    return (float) $modes[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'result' => [
          'type' => 'number',
          'description' => 'The calculation result',
        ],
        'operation' => [
          'type' => 'string',
          'description' => 'The operation performed',
        ],
        'values' => [
          'type' => 'array',
          'description' => 'The input values used',
        ],
        'precision' => [
          'type' => 'integer',
          'description' => 'The precision used',
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
        'operation' => [
          'type' => 'string',
          'title' => 'Operation',
          'description' => 'Mathematical operation to perform',
          'default' => 'add',
          'enum' => [
            'add',
            'subtract',
            'multiply',
            'divide',
            'power',
            'sqrt',
            'average',
            'min',
            'max',
            'median',
            'mode',
          ],
        ],
        'precision' => [
          'type' => 'integer',
          'title' => 'Precision',
          'description' => 'Number of decimal places',
          'default' => 2,
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
        'values' => [
          'type' => 'array',
          'title' => 'Values',
          'description' => 'Numeric values for calculation',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
