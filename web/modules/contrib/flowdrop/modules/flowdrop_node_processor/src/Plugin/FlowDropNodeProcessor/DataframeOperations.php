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
 * Executor for Dataframe Operations nodes.
 */
#[FlowDropNodeProcessor(
  id: "dataframe_operations",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Dataframe Operations"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Operations on dataframe data",
  version: "1.0.0",
  tags: ["dataframe", "processing", "data"]
)]
class DataframeOperations extends AbstractFlowDropNodeProcessor {

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
    $operation = $config->getConfig('operation', 'head');
    $columns = $config->getConfig('columns', []);
    $rows = $config->getConfig('rows', 5);
    $condition = $config->getConfig('condition', '');

    // Get the input dataframe.
    $dataframe = $inputs->get('dataframe') ?: [];

    $result = [];
    switch ($operation) {
      case 'head':
        $result = $this->head($dataframe, $rows);
        break;

      case 'tail':
        $result = $this->tail($dataframe, $rows);
        break;

      case 'select':
        $result = $this->select($dataframe, $columns);
        break;

      case 'filter':
        $result = $this->filter($dataframe, $condition);
        break;

      case 'sort':
        $result = $this->sort($dataframe, $columns);
        break;

      case 'group':
        $result = $this->group($dataframe, $columns);
        break;

      case 'aggregate':
        $result = $this->aggregate($dataframe, $columns);
        break;

      case 'merge':
        $result = $this->merge($dataframe);
        break;
    }

    $this->getLogger()->info('Dataframe operations executed successfully', [
      'operation' => $operation,
      'input_rows' => count($dataframe['data'] ?? []),
      'output_rows' => count($result['data'] ?? []),
    ]);

    return [
      'result' => $result,
      'operation' => $operation,
      'input_rows' => count($dataframe['data'] ?? []),
      'output_rows' => count($result['data'] ?? []),
      'columns' => $columns,
      'rows' => $rows,
    ];
  }

  /**
   * Get first n rows.
   */
  private function head(array $dataframe, int $rows): array {
    $data = $dataframe['data'] ?? [];
    return [
      'data' => array_slice($data, 0, $rows),
      'columns' => $dataframe['columns'] ?? [],
      'index' => array_slice($dataframe['index'] ?? [], 0, $rows),
    ];
  }

  /**
   * Get last n rows.
   */
  private function tail(array $dataframe, int $rows): array {
    $data = $dataframe['data'] ?? [];
    return [
      'data' => array_slice($data, -$rows),
      'columns' => $dataframe['columns'] ?? [],
      'index' => array_slice($dataframe['index'] ?? [], -$rows),
    ];
  }

  /**
   * Select specific columns.
   */
  private function select(array $dataframe, array $columns): array {
    $data = $dataframe['data'] ?? [];
    if (empty($columns)) {
      return $dataframe;
    }

    $filteredData = [];
    foreach ($data as $row) {
      $filteredRow = [];
      foreach ($columns as $column) {
        if (isset($row[$column])) {
          $filteredRow[$column] = $row[$column];
        }
      }
      $filteredData[] = $filteredRow;
    }

    return [
      'data' => $filteredData,
      'columns' => $columns,
      'index' => $dataframe['index'] ?? [],
    ];
  }

  /**
   * Filter rows based on condition.
   */
  private function filter(array $dataframe, string $condition): array {
    $data = $dataframe['data'] ?? [];
    if (empty($condition)) {
      return $dataframe;
    }

    // Simple condition evaluation (in real implementation,
    // use proper expression parser)
    $filteredData = array_filter($data, function ($row) use ($condition) {
      switch ($condition) {
        case 'empty':
          return empty($row);

        case 'not empty':
          return !empty($row);
      }
    });

    return [
      'data' => array_values($filteredData),
      'columns' => $dataframe['columns'] ?? [],
      'index' => array_slice($dataframe['index'] ?? [], 0, count($filteredData)),
    ];
  }

  /**
   * Sort by columns.
   */
  private function sort(array $dataframe, array $columns): array {
    $data = $dataframe['data'] ?? [];
    if (empty($columns)) {
      return $dataframe;
    }

    usort($data, function ($a, $b) use ($columns) {
      foreach ($columns as $column) {
        $aVal = $a[$column] ?? '';
        $bVal = $b[$column] ?? '';
        $comparison = $aVal <=> $bVal;
        if ($comparison !== 0) {
          return $comparison;
        }
      }
      return 0;
    });

    return [
      'data' => $data,
      'columns' => $dataframe['columns'] ?? [],
      'index' => $dataframe['index'] ?? [],
    ];
  }

  /**
   * Group by columns.
   */
  private function group(array $dataframe, array $columns): array {
    $data = $dataframe['data'] ?? [];
    if (empty($columns)) {
      return $dataframe;
    }

    $groups = [];
    foreach ($data as $row) {
      $groupKey = [];
      foreach ($columns as $column) {
        $groupKey[] = $row[$column] ?? '';
      }
      $key = implode('|', $groupKey);

      if (!isset($groups[$key])) {
        $groups[$key] = [];
      }
      $groups[$key][] = $row;
    }

    return [
      'data' => $groups,
      'columns' => $columns,
      'index' => array_keys($groups),
    ];
  }

  /**
   * Aggregate data.
   */
  private function aggregate(array $dataframe, array $columns): array {
    $data = $dataframe['data'] ?? [];
    if (empty($columns)) {
      return $dataframe;
    }

    $aggregates = [];
    foreach ($columns as $column) {
      $values = array_column($data, $column);
      $aggregates[$column] = [
        'count' => count($values),
        'sum' => array_sum($values),
        'avg' => count($values) > 0 ? array_sum($values) / count($values) : 0,
        'min' => min($values),
        'max' => max($values),
      ];
    }

    return [
      'data' => $aggregates,
      'columns' => $columns,
      'index' => array_keys($aggregates),
    ];
  }

  /**
   * Merge dataframes.
   */
  private function merge(array $dataframe): array {
    // This is a placeholder for merge operation
    // In practice, you'd merge multiple dataframes.
    return $dataframe;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Dataframe operations nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'result' => [
          'type' => 'object',
          'description' => 'The processed dataframe',
        ],
        'operation' => [
          'type' => 'string',
          'description' => 'The operation performed',
        ],
        'input_rows' => [
          'type' => 'integer',
          'description' => 'Number of input rows',
        ],
        'output_rows' => [
          'type' => 'integer',
          'description' => 'Number of output rows',
        ],
        'columns' => [
          'type' => 'array',
          'description' => 'The columns used',
        ],
        'rows' => [
          'type' => 'integer',
          'description' => 'The number of rows',
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
          'description' => 'Dataframe operation to perform',
          'default' => 'head',
          'enum' => ['head', 'tail', 'select', 'filter', 'sort', 'group', 'aggregate', 'merge'],
        ],
        'columns' => [
          'type' => 'array',
          'title' => 'Columns',
          'description' => 'Columns to operate on',
          'default' => [],
        ],
        'rows' => [
          'type' => 'integer',
          'title' => 'Rows',
          'description' => 'Number of rows for head/tail operations',
          'default' => 5,
        ],
        'condition' => [
          'type' => 'string',
          'title' => 'Condition',
          'description' => 'Filter condition',
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
        'dataframe' => [
          'type' => 'object',
          'title' => 'Dataframe',
          'description' => 'The dataframe to operate on',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
