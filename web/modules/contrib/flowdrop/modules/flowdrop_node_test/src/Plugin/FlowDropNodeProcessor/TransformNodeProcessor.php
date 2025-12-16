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
 * Transform Node Processor for testing FlowDrop system.
 *
 * Applies simple data transformations for testing purposes.
 */
#[FlowDropNodeProcessor(
  id: "transform_node_processor",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Transform Node Processor"),
  type: "transform",
  supportedTypes: ["transform"],
  category: "testing",
  description: "Applies simple data transformations for testing",
  version: "1.0.0",
  inputs: [
    "data" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Data to transform",
    ],
  ],
  outputs: [
    "transformed_data" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Transformed data",
    ],
    "original_data" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Original input data",
    ],
    "transformation_applied" => [
      "type" => "string",
      "required" => TRUE,
      "description" => "Type of transformation applied",
    ],
  ],
  config: [
    "transformation_type" => [
      "type" => "string",
      "default" => "uppercase",
      "description" => "Type of transformation (uppercase, lowercase, reverse, count_words)",
    ],
    "prefix" => [
      "type" => "string",
      "default" => "",
      "description" => "Prefix to add to transformed data",
    ],
    "suffix" => [
      "type" => "string",
      "default" => "",
      "description" => "Suffix to add to transformed data",
    ],
  ],
  tags: ["test", "transform", "data"]
)]
final class TransformNodeProcessor extends AbstractFlowDropNodeProcessor {

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
    $transformationType = $this->getConfigValue($config, "transformation_type", "uppercase");
    $prefix = $this->getConfigValue($config, "prefix", "");
    $suffix = $this->getConfigValue($config, "suffix", "");

    $transformedData = $this->applyTransformation($data, $transformationType);

    // Add prefix and suffix.
    $finalData = $prefix . $transformedData . $suffix;

    return [
      "transformed_data" => $finalData,
      "original_data" => $data,
      "transformation_applied" => $transformationType,
      "timestamp" => time(),
    ];
  }

  /**
   * Apply the specified transformation to the data.
   *
   * @param string $data
   *   The input data to transform.
   * @param string $type
   *   The type of transformation to apply.
   *
   * @return string
   *   The transformed data.
   */
  private function applyTransformation(string $data, string $type): string {
    return match ($type) {
      "uppercase" => strtoupper($data),
      "lowercase" => strtolower($data),
      "reverse" => strrev($data),
      "count_words" => (string) str_word_count($data),
      "capitalize" => ucwords(strtolower($data)),
      "trim" => trim($data),
      default => $data
    };
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
