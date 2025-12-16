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
 * Executor for Message to Data nodes.
 */
#[FlowDropNodeProcessor(
  id: "message_to_data",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Message to Data"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Convert messages to structured data",
  version: "1.0.0",
  tags: ["message", "data", "processing"]
)]
class MessageToData extends AbstractFlowDropNodeProcessor {

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
    $extractFields = $config->getConfig('extractFields', []);

    // Get the input message.
    $message = $inputs->get('message') ?: '';

    $data = [];
    switch ($format) {
      case 'json':
        $data = json_decode($message, TRUE) ?: [];
        break;

      case 'csv':
        $data = $this->parseCsv($message);
        break;

      case 'xml':
        $data = $this->parseXml($message);
        break;

      case 'yaml':
        $data = $this->parseYaml($message);
        break;

      case 'key_value':
        $data = $this->parseKeyValue($message);
        break;
    }

    // Extract specific fields if configured.
    if (!empty($extractFields) && is_array($data)) {
      $extracted = [];
      foreach ($extractFields as $field) {
        if (isset($data[$field])) {
          $extracted[$field] = $data[$field];
        }
      }
      $data = $extracted;
    }

    $this->getLogger()->info('Message to data executed successfully', [
      'format' => $format,
      'message_length' => strlen($message),
      'data_keys' => array_keys($data),
    ]);

    return [
      'data' => $data,
      'format' => $format,
      'extracted_fields' => $extractFields,
      'original_message' => $message,
    ];
  }

  /**
   * Parse CSV data.
   */
  private function parseCsv(string $csv): array {
    $lines = explode("\n", trim($csv));
    $headers = str_getcsv(array_shift($lines));
    $data = [];
    foreach ($lines as $line) {
      $row = str_getcsv($line);
      if (count($row) === count($headers)) {
        $data[] = array_combine($headers, $row);
      }
    }
    return $data;
  }

  /**
   * Parse XML data.
   */
  private function parseXml(string $xml): array {
    $data = [];
    try {
      $xmlObj = simplexml_load_string($xml);
      if ($xmlObj) {
        $data = json_decode(json_encode($xmlObj), TRUE);
      }
    }
    catch (\Exception $e) {
      // Return empty array on parse error.
    }
    return $data;
  }

  /**
   * Parse YAML data.
   */
  private function parseYaml(string $yaml): array {
    $data = [];
    try {
      if (function_exists('yaml_parse')) {
        $data = yaml_parse($yaml) ?: [];
      }
    }
    catch (\Exception $e) {
      // Return empty array on parse error.
    }
    return $data;
  }

  /**
   * Parse key-value pairs.
   */
  private function parseKeyValue(string $text): array {
    $data = [];
    $lines = explode("\n", $text);

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || strpos($line, '=') === FALSE) {
        continue;
      }

      $parts = explode('=', $line, 2);
      if (count($parts) === 2) {
        $data[trim($parts[0])] = trim($parts[1]);
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Message to data nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'data' => [
          'type' => 'object',
          'description' => 'The parsed data',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The format used for parsing',
        ],
        'extracted_fields' => [
          'type' => 'array',
          'description' => 'The fields that were extracted',
        ],
        'original_message' => [
          'type' => 'string',
          'description' => 'The original message',
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
          'description' => 'Data format to parse',
          'default' => 'json',
          'enum' => ['json', 'csv', 'xml', 'yaml', 'key_value'],
        ],
        'extractFields' => [
          'type' => 'array',
          'title' => 'Extract Fields',
          'description' => 'Specific fields to extract from the data',
          'default' => [],
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
        'message' => [
          'type' => 'string',
          'title' => 'Message',
          'description' => 'The message to convert to data',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
