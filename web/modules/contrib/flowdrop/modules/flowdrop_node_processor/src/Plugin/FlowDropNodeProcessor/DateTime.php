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
 * Executor for DateTime nodes.
 */
#[FlowDropNodeProcessor(
  id: "date_time",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Date Time"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Date and time operations",
  version: "1.0.0",
  tags: ["datetime", "processing", "time"]
)]
class DateTime extends AbstractFlowDropNodeProcessor {

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
    $format = $config->getConfig('format', 'Y-m-d H:i:s');
    $timezone = $config->getConfig('timezone', 'UTC');
    $operation = $config->getConfig('operation', 'current');

    $now = new \DateTime('now', new \DateTimeZone($timezone));

    $result = '';
    switch ($operation) {
      case 'current':
        $result = $now->format($format);
        break;

      case 'timestamp':
        $result = $now->getTimestamp();
        break;

      case 'iso':
        $result = $now->format('c');
        break;

      case 'unix':
        $result = time();
        break;

      case 'custom':
        $customFormat = $config->getConfig('customFormat', 'Y-m-d H:i:s');
        $result = $now->format($customFormat);
        break;
    }

    $this->getLogger()->info('DateTime executed successfully', [
      'operation' => $operation,
      'format' => $format,
      'timezone' => $timezone,
    ]);

    return [
      'datetime' => $result,
      'timestamp' => $now->getTimestamp(),
      'format' => $format,
      'timezone' => $timezone,
      'operation' => $operation,
      'iso' => $now->format('c'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // DateTime nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'datetime' => [
          'type' => 'string',
          'description' => 'The formatted datetime',
        ],
        'timestamp' => [
          'type' => 'integer',
          'description' => 'The Unix timestamp',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The format used',
        ],
        'timezone' => [
          'type' => 'string',
          'description' => 'The timezone used',
        ],
        'operation' => [
          'type' => 'string',
          'description' => 'The operation performed',
        ],
        'iso' => [
          'type' => 'string',
          'description' => 'ISO 8601 formatted datetime',
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
          'description' => 'DateTime operation (current, timestamp, iso, unix, custom)',
          'default' => 'current',
        ],
        'format' => [
          'type' => 'string',
          'title' => 'Format',
          'description' => 'PHP date format string',
          'default' => 'Y-m-d H:i:s',
        ],
        'timezone' => [
          'type' => 'string',
          'title' => 'Timezone',
          'description' => 'Timezone to use',
          'default' => 'UTC',
        ],
        'customFormat' => [
          'type' => 'string',
          'title' => 'Custom Format',
          'description' => 'Custom date format for custom operation',
          'default' => 'Y-m-d H:i:s',
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
        'datetime' => [
          'type' => 'string',
          'title' => 'DateTime',
          'description' => 'Optional datetime input to process',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
