<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_test\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\flowdrop\DTO\InputInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Start Node Processor for testing FlowDrop system.
 *
 * Emits a static payload to initiate workflow testing.
 */
#[FlowDropNodeProcessor(
  id: "start_node_processor",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Start Node Processor"),
  type: "start",
  supportedTypes: ["start"],
  category: "testing",
  description: "Emits a static payload for workflow testing",
  version: "1.0.0",
  inputs: [],
  outputs: [
    "data" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Static test data",
    ],
  ],
  config: [
    "message" => [
      "type" => "string",
      "default" => "Hello from Start Node!",
      "description" => "Static message to emit",
    ],
  ],
  tags: ["test", "start", "debug"]
)]
final class StartNodeProcessor extends AbstractFlowDropNodeProcessor {

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
    return $this->loggerFactory->get('flowdrop_node_test');
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $message = $this->getConfigValue($config, "message", "Hello from Start Node!");

    return [
      "data" => $message,
      "timestamp" => time(),
      "source" => "start_node_processor",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // No inputs to validate for start node.
    return TRUE;
  }

  /**
   * Get a configuration value with fallback.
   *
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The configuration object.
   * @param string $key
   *   The configuration key.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The configuration value or default.
   */
  protected function getConfigValue(ConfigInterface $config, string $key, $default = NULL) {
    return $config->get($key) ?? $default;
  }

}
