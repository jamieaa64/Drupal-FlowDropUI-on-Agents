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
 * Executor for Data Operations nodes.
 */
#[FlowDropNodeProcessor(
  id: "data_operations",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Data Operations"),
  type: "default",
  supportedTypes: ["default"],
  description: "Perform various data operations on arrays and objects",
  category: "processing",
  version: "1.0.0",
  tags: ["data", "processing", "array", "filter", "sort"]
)]
class DataOperations extends AbstractFlowDropNodeProcessor {

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
    // Data operations can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $operation = $config->getConfig('operation', 'filter');
    $key = $config->getConfig('key', '');
    $value = $config->getConfig('value', '');
    $condition = $config->getConfig('condition', 'equals');

    // Get the input data.
    $data = $inputs->get('data', []);

    $result = [];
    switch ($operation) {
      case 'filter':
        $result = $this->filterData($data, $key, $value, $condition);
        break;

      case 'sort':
        $result = $this->sortData($data, $key, $value === 'desc');
        break;

      case 'group':
        $result = $this->groupData($data, $key);
        break;

      case 'map':
        $result = $this->mapData($data, $key, $value);
        break;

      case 'reduce':
        $result = $this->reduceData($data, $key, $value);
        break;

      case 'unique':
        $result = $this->uniqueData($data, $key);
        break;

      case 'slice':
        $start = (int) $key;
        $length = (int) $value;
        $result = array_slice($data, $start, $length);
        break;

      case 'merge':
        $result = $this->mergeData($data);
        break;
    }

    $this->getLogger()->info('Data operations executed successfully', [
      'operation' => $operation,
      'input_count' => count($data),
      'output_count' => count($result),
    ]);

    return [
      'result' => $result,
      'operation' => $operation,
      'input_count' => count($data),
      'output_count' => count($result),
    ];
  }

  /**
   * Filter data based on key-value condition.
   *
   * @param array $data
   *   The data to filter.
   * @param string $key
   *   The key to filter on.
   * @param mixed $value
   *   The value to compare against.
   * @param string $condition
   *   The comparison condition.
   *
   * @return array
   *   The filtered data.
   */
  private function filterData(array $data, string $key, mixed $value, string $condition): array {
    if (empty($key)) {
      return $data;
    }

    return array_filter($data, function ($item) use ($key, $value, $condition) {
      $item_value = is_array($item) ? ($item[$key] ?? NULL) : (is_object($item) ? ($item->$key ?? NULL) : NULL);

      switch ($condition) {
        case 'equals':
          return $item_value == $value;

        case 'not_equals':
          return $item_value != $value;

        case 'contains':
          return strpos((string) $item_value, (string) $value) !== FALSE;

        case 'greater_than':
          return $item_value > $value;

        case 'less_than':
          return $item_value < $value;

        case 'greater_than_or_equal':
          return $item_value >= $value;

        case 'less_than_or_equal':
          return $item_value <= $value;

        case 'is_empty':
          return empty($item_value);

        case 'is_not_empty':
          return !empty($item_value);

        default:
          return TRUE;
      }
    });
  }

  /**
   * Sort data by key.
   *
   * @param array $data
   *   The data to sort.
   * @param string $key
   *   The key to sort by.
   * @param bool $descending
   *   Whether to sort in descending order.
   *
   * @return array
   *   The sorted data.
   */
  private function sortData(array $data, string $key, bool $descending = FALSE): array {
    if (empty($key)) {
      return $data;
    }

    usort($data, function ($a, $b) use ($key, $descending) {
      $a_value = is_array($a) ? ($a[$key] ?? '') : (is_object($a) ? ($a->$key ?? '') : '');
      $b_value = is_array($b) ? ($b[$key] ?? '') : (is_object($b) ? ($b->$key ?? '') : '');

      $result = $a_value <=> $b_value;
      return $descending ? -$result : $result;
    });

    return $data;
  }

  /**
   * Group data by key.
   *
   * @param array $data
   *   The data to group.
   * @param string $key
   *   The key to group by.
   *
   * @return array
   *   The grouped data.
   */
  private function groupData(array $data, string $key): array {
    if (empty($key)) {
      return $data;
    }

    $grouped = [];
    foreach ($data as $item) {
      $group_key = is_array($item) ? ($item[$key] ?? '') : (is_object($item) ? ($item->$key ?? '') : '');
      $grouped[$group_key][] = $item;
    }

    return $grouped;
  }

  /**
   * Map data using a key.
   *
   * @param array $data
   *   The data to map.
   * @param string $key
   *   The key to map by.
   * @param mixed $value
   *   The value to use for mapping.
   *
   * @return array
   *   The mapped data.
   */
  private function mapData(array $data, string $key, mixed $value): array {
    if (empty($key)) {
      return $data;
    }

    return array_map(function ($item) use ($key, $value) {
      if (is_array($item)) {
        $item[$key] = $value;
      }
      elseif (is_object($item)) {
        $item->$key = $value;
      }
      return $item;
    }, $data);
  }

  /**
   * Reduce data using a key.
   *
   * @param array $data
   *   The data to reduce.
   * @param string $key
   *   The key to reduce by.
   * @param mixed $initialValue
   *   The initial value.
   *
   * @return mixed
   *   The reduced value.
   */
  private function reduceData(array $data, string $key, mixed $initialValue): mixed {
    return array_reduce($data, function ($carry, $item) use ($key) {
      $item_value = is_array($item) ? ($item[$key] ?? 0) : (is_object($item) ? ($item->$key ?? 0) : 0);
      return $carry + $item_value;
    }, $initialValue);
  }

  /**
   * Get unique data by key.
   *
   * @param array $data
   *   The data to make unique.
   * @param string $key
   *   The key to check uniqueness by.
   *
   * @return array
   *   The unique data.
   */
  private function uniqueData(array $data, string $key): array {
    if (empty($key)) {
      return array_unique($data, SORT_REGULAR);
    }

    $seen = [];
    $unique = [];

    foreach ($data as $item) {
      $item_value = is_array($item) ? ($item[$key] ?? '') : (is_object($item) ? ($item->$key ?? '') : '');
      if (!in_array($item_value, $seen, TRUE)) {
        $seen[] = $item_value;
        $unique[] = $item;
      }
    }

    return $unique;
  }

  /**
   * Merge data arrays.
   *
   * @param array $data
   *   The data to merge.
   *
   * @return array
   *   The merged data.
   */
  private function mergeData(array $data): array {
    $merged = [];
    foreach ($data as $item) {
      if (is_array($item)) {
        $merged = array_merge($merged, $item);
      }
    }
    return $merged;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'result' => [
          'type' => 'array',
          'description' => 'The processed data result',
        ],
        'operation' => [
          'type' => 'string',
          'description' => 'The operation performed',
        ],
        'input_count' => [
          'type' => 'integer',
          'description' => 'Number of input items',
        ],
        'output_count' => [
          'type' => 'integer',
          'description' => 'Number of output items',
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
          'description' => 'Data operation to perform',
          'default' => 'filter',
          'enum' => [
            'filter',
            'sort',
            'group',
            'map',
            'reduce',
            'unique',
            'slice',
            'merge',
          ],
        ],
        'key' => [
          'type' => 'string',
          'title' => 'Key',
          'description' => 'Key to operate on',
          'default' => '',
        ],
        'value' => [
          'type' => 'mixed',
          'title' => 'Value',
          'description' => 'Value for the operation',
          'default' => '',
        ],
        'condition' => [
          'type' => 'string',
          'title' => 'Condition',
          'description' => 'Condition for filtering',
          'default' => 'equals',
          'enum' => [
            'equals',
            'not_equals',
            'contains',
            'greater_than',
            'less_than',
            'greater_than_or_equal',
            'less_than_or_equal',
            'is_empty',
            'is_not_empty',
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
          'type' => 'array',
          'title' => 'Data',
          'description' => 'Data to process',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
