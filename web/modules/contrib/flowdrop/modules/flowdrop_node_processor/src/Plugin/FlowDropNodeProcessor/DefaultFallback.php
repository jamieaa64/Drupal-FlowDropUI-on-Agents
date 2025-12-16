<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default fallback executor for nodes that don't need processing.
 *
 * This plugin serves as a fallback for nodes that don't require any
 * input processing or output generation. It's useful for:
 * - Display-only nodes
 * - Placeholder nodes
 * - Nodes that are purely decorative
 * - Nodes that handle their own processing internally.
 */
#[FlowDropNodeProcessor(
  id: "default_fallback",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Default Fallback"),
  type: "default",
  supportedTypes: ["default"],
  category: "control",
  description: "Default fallback for failed operations",
  version: "1.0.0",
  tags: ["fallback", "control", "error"]
)]
class DefaultFallback extends AbstractFlowDropNodeProcessor {

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
    // Default fallback executor does nothing - it's a no-op
    // This is useful for nodes that don't need any processing.
    $this->getLogger()->info('Default fallback executor executed - no processing required', [
      'inputs_count' => count($inputs->toArray()),
      'config_keys' => array_keys($config->toArray()),
    ]);

    // Return empty result since this executor doesn't produce any outputs.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Default fallback accepts any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    // No inputs required.
    return [
      'type' => 'object',
      'properties' => [],
      'required' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    // No outputs produced.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Optional description for this node',
          'default' => '',
        ],
      ],
      'required' => [],
    ];
  }

}
