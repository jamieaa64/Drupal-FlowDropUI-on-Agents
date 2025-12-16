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
 * Executor for Structured Output nodes.
 */
#[FlowDropNodeProcessor(
  id: "structured_output",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Structured Output"),
  type: "default",
  supportedTypes: ["default"],
  category: "output",
  description: "Structured data output",
  version: "1.0.0",
  tags: ["output", "structured", "data"]
)]
class StructuredOutput extends AbstractFlowDropNodeProcessor {

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
    $schema = $config->getConfig('schema', []);
    $validate = $config->getConfig('validate', TRUE);

    // Get the input data.
    $data = $inputs->get('data') ?: [];

    // Validate against schema if enabled.
    $validationErrors = [];
    if ($validate && !empty($schema)) {
      $validationErrors = $this->validateAgainstSchema($data, $schema);
    }

    // Format the output.
    $output = '';
    switch ($format) {
      case 'json':
        $output = json_encode($data, JSON_PRETTY_PRINT);
        break;

      case 'xml':
        $output = $this->arrayToXml($data);
        break;

      case 'yaml':
        $output = $this->arrayToYaml($data);
        break;

      case 'csv':
        $output = $this->arrayToCsv($data);
        break;
    }

    $this->getLogger()->info('Structured output executed successfully', [
      'format' => $format,
      'data_keys' => array_keys($data),
      'validation_errors' => count($validationErrors),
    ]);

    return [
      'output' => $output,
      'data' => $data,
      'format' => $format,
      'validation_errors' => $validationErrors,
      'is_valid' => empty($validationErrors),
    ];
  }

  /**
   * Validate data against schema.
   */
  private function validateAgainstSchema(array $data, array $schema): array {
    $errors = [];

    foreach ($schema as $field => $rules) {
      if (isset($rules['required']) && $rules['required'] && !isset($data[$field])) {
        $errors[] = "Required field '{$field}' is missing";
        continue;
      }

      if (isset($data[$field])) {
        $value = $data[$field];

        if (isset($rules['type'])) {
          $actualType = gettype($value);
          if ($actualType !== $rules['type']) {
            $errors[] = "Field '{$field}' should be {$rules['type']}, got {$actualType}";
          }
        }

        if (isset($rules['min_length']) && is_string($value) && strlen($value) < $rules['min_length']) {
          $errors[] = "Field '{$field}' is too short (minimum {$rules['min_length']} characters)";
        }

        if (isset($rules['max_length']) && is_string($value) && strlen($value) > $rules['max_length']) {
          $errors[] = "Field '{$field}' is too long (maximum {$rules['max_length']} characters)";
        }
      }
    }

    return $errors;
  }

  /**
   * Convert array to XML.
   */
  private function arrayToXml(array $data): string {
    $xml = new \SimpleXMLElement('<?xml version="1.0"?><data></data>');
    $this->arrayToXmlHelper($data, $xml);
    return $xml->asXML();
  }

  /**
   * Helper for array to XML conversion.
   */
  private function arrayToXmlHelper(array $data, \SimpleXMLElement $xml): void {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $subnode = $xml->addChild($key);
        $this->arrayToXmlHelper($value, $subnode);
      }
      else {
        $xml->addChild($key, htmlspecialchars($value));
      }
    }
  }

  /**
   * Convert array to YAML.
   */
  private function arrayToYaml(array $data): string {
    if (function_exists('yaml_emit')) {
      return yaml_emit($data);
    }
    return json_encode($data, JSON_PRETTY_PRINT);
  }

  /**
   * Convert array to CSV.
   */
  private function arrayToCsv(array $data): string {
    if (empty($data)) {
      return '';
    }

    $output = fopen('php://temp', 'r+');
    if (is_array(reset($data))) {
      // Array of arrays.
      fputcsv($output, array_keys(reset($data)));
      foreach ($data as $row) {
        fputcsv($output, $row);
      }
    }
    else {
      // Single array.
      fputcsv($output, array_keys($data));
      fputcsv($output, array_values($data));
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Structured output nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'output' => [
          'type' => 'string',
          'description' => 'The formatted output',
        ],
        'data' => [
          'type' => 'object',
          'description' => 'The structured data',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The output format used',
        ],
        'validation_errors' => [
          'type' => 'array',
          'description' => 'Any validation errors',
        ],
        'is_valid' => [
          'type' => 'boolean',
          'description' => 'Whether the data is valid',
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
          'enum' => ['json', 'xml', 'yaml', 'csv'],
        ],
        'schema' => [
          'type' => 'object',
          'title' => 'Schema',
          'description' => 'Validation schema for the output',
          'default' => [],
        ],
        'validate' => [
          'type' => 'boolean',
          'title' => 'Validate',
          'description' => 'Whether to validate against schema',
          'default' => TRUE,
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
          'type' => 'object',
          'title' => 'Data',
          'description' => 'The data to structure and output',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
