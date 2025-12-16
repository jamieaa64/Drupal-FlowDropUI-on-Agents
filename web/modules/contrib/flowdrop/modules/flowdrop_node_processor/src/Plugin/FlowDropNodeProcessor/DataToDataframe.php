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
 * Executor for Data to Dataframe nodes.
 */
#[FlowDropNodeProcessor(
  id: "data_to_dataframe",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Data to Dataframe"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Convert data to dataframe format",
  version: "1.0.0",
  tags: ["data", "dataframe", "processing"]
)]
class DataToDataframe extends AbstractFlowDropNodeProcessor {

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
    $format = $config->getConfig('format', 'json');
    $includeIndex = $config->getConfig('includeIndex', FALSE);
    $orient = $config->getConfig('orient', 'records');

    // Get the input data.
    $data = $inputs->get('data') ?: [];

    // Convert to dataframe-like structure.
    $dataframe = $this->createDataframe($data, $includeIndex, $orient);

    $this->getLogger()->info('Data to dataframe executed successfully', [
      'format' => $format,
      'rows_count' => count($dataframe['data']),
      'columns_count' => count($dataframe['columns']),
    ]);

    return [
      'dataframe' => $dataframe,
      'format' => $format,
      'rows_count' => count($dataframe['data']),
      'columns_count' => count($dataframe['columns']),
      'include_index' => $includeIndex,
      'orient' => $orient,
    ];
  }

  /**
   * Create a dataframe-like structure from data.
   */
  private function createDataframe(array $data, bool $includeIndex, string $orient): array {
    if (empty($data)) {
      return [
        'data' => [],
        'columns' => [],
        'index' => [],
      ];
    }

    // Determine columns from first item.
    $columns = [];
    $firstItem = reset($data);
    if (is_array($firstItem)) {
      $columns = array_keys($firstItem);
    }

    // Create index if requested.
    $index = [];
    if ($includeIndex) {
      $index = array_keys($data);
    }

    // Normalize data based on orient.
    $normalizedData = [];
    switch ($orient) {
      case 'records':
        $normalizedData = $data;
        break;

      case 'index':
        $normalizedData = array_values($data);
        break;

      case 'columns':
        $normalizedData = $this->transposeData($data);
        break;

      case 'split':
        $normalizedData = $this->splitData($data);
        break;

      default:
        $normalizedData = $data;
    }

    return [
      'data' => $normalizedData,
      'columns' => $columns,
      'index' => $index,
    ];
  }

  /**
   * Transpose data for columns orient.
   */
  private function transposeData(array $data): array {
    if (empty($data)) {
      return [];
    }

    $transposed = [];
    $firstItem = reset($data);
    if (is_array($firstItem)) {
      foreach (array_keys($firstItem) as $key) {
        $transposed[$key] = array_column($data, $key);
      }
    }

    return $transposed;
  }

  /**
   * Split data for split orient.
   */
  private function splitData(array $data): array {
    if (empty($data)) {
      return ['data' => [], 'index' => [], 'columns' => []];
    }

    $firstItem = reset($data);
    if (!is_array($firstItem)) {
      return ['data' => $data, 'index' => [], 'columns' => []];
    }

    return [
      'data' => array_values($data),
      'index' => array_keys($data),
      'columns' => array_keys($firstItem),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Data to dataframe nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'dataframe' => [
          'type' => 'object',
          'description' => 'The dataframe structure',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The output format',
        ],
        'rows_count' => [
          'type' => 'integer',
          'description' => 'Number of rows in dataframe',
        ],
        'columns_count' => [
          'type' => 'integer',
          'description' => 'Number of columns in dataframe',
        ],
        'include_index' => [
          'type' => 'boolean',
          'description' => 'Whether index is included',
        ],
        'orient' => [
          'type' => 'string',
          'description' => 'The orientation used',
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
        'format' => [
          'type' => 'string',
          'title' => 'Format',
          'description' => 'Output format to use',
          'default' => 'json',
          'enum' => ['json', 'csv', 'parquet'],
        ],
        'includeIndex' => [
          'type' => 'boolean',
          'title' => 'Include Index',
          'description' => 'Whether to include row indices',
          'default' => FALSE,
        ],
        'orient' => [
          'type' => 'string',
          'title' => 'Orientation',
          'description' => 'Data orientation to use',
          'default' => 'records',
          'enum' => ['records', 'index', 'columns', 'split'],
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
          'description' => 'The data to convert to dataframe',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
