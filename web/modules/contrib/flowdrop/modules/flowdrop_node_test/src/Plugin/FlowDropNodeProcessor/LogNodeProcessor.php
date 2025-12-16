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
 * Log Node Processor for testing FlowDrop system.
 *
 * Logs input payload to watchdog for debugging purposes.
 */
#[FlowDropNodeProcessor(
  id: "log_node_processor",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Log Node Processor"),
  type: "log",
  supportedTypes: ["log"],
  category: "testing",
  description: "Logs input payload to watchdog for debugging",
  version: "1.0.0",
  inputs: [
    "data" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Data to log",
    ],
  ],
  outputs: [
    "data" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Passed through data",
    ],
    "logged" => [
      "type" => "boolean",
      "required" => TRUE,
      "description" => "Whether data was logged successfully",
    ],
  ],
  config: [
    "log_level" => [
      "type" => "string",
      "default" => "info",
      "description" => "Log level (debug, info, warning, error)",
    ],
    "include_timestamp" => [
      "type" => "boolean",
      "default" => TRUE,
      "description" => "Include timestamp in log message",
    ],
  ],
  tags: ["test", "log", "debug"]
)]
final class LogNodeProcessor extends AbstractFlowDropNodeProcessor {

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
    $data = $inputs->get("data", "");
    $logLevel = $this->getConfigValue($config, "log_level", "info");
    $includeTimestamp = $this->getConfigValue($config, "include_timestamp", TRUE);

    $message = $data;
    if ($includeTimestamp) {
      $message = "[" . date("Y-m-d H:i:s") . "] " . $message;
    }

    // Log to watchdog using injected logger.
    $logger = $this->getLogger();
    $logged = FALSE;

    try {
      switch ($logLevel) {
        case "debug":
          $logger->debug($message);
          break;

        case "warning":
          $logger->warning($message);
          break;

        case "error":
          $logger->error($message);
          break;

        default:
          $logger->info($message);
      }
      $logged = TRUE;
    }
    catch (\Exception $e) {
      $logger->error("Failed to log message: @error", ["@error" => $e->getMessage()]);
    }

    return [
      "data" => $data,
      "logged" => $logged,
      "log_level" => $logLevel,
      "timestamp" => time(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    if (!isset($inputs["data"])) {
      return FALSE;
    }

    $data = $inputs["data"];
    if (!is_string($data) && !is_numeric($data)) {
      return FALSE;
    }

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
