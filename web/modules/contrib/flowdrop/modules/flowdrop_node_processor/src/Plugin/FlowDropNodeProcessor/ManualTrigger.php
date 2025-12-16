<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for Manual Trigger nodes.
 *
 * Manual triggers allow users to manually start workflow execution
 * with custom input data or default configured data.
 */
#[FlowDropNodeProcessor(
  id: "manual_trigger",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Manual Trigger"),
  type: "trigger",
  supportedTypes: ["default"],
  category: "trigger",
  description: "Manual workflow trigger",
  version: "1.0.0",
  tags: ["trigger", "manual", "workflow"]
)]
class ManualTrigger extends AbstractTrigger {

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
    // This method is required by AbstractFlowDropNodeProcessor
    // but triggers use their own execute method, so we delegate to the parent.
    return parent::execute($inputs, $config)->toArray();
  }

  /**
   * {@inheritdoc}
   */
  protected function getTriggerType(): string {
    return 'manual';
  }

  /**
   * {@inheritdoc}
   */
  protected function processTriggerData(array $trigger_data, array $inputs): array {
    // Manual triggers use the configured default data or inputs.
    $processed_data = !empty($inputs) ? $inputs : $trigger_data;

    // Add manual trigger specific metadata.
    $processed_data['manual_execution'] = TRUE;
    $processed_data['execution_source'] = 'manual';
    $processed_data['user_initiated'] = TRUE;

    return $processed_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    $base_schema = parent::getConfigSchema();

    // Add manual trigger specific configuration.
    $base_schema['properties']['defaultInputs'] = [
      'type' => 'object',
      'title' => 'Default Inputs',
      'description' => 'Default input data to use when manually triggering the workflow',
      'default' => [],
    ];

    $base_schema['properties']['allowCustomInputs'] = [
      'type' => 'boolean',
      'title' => 'Allow Custom Inputs',
      'description' => 'Whether to allow users to provide custom inputs when manually triggering',
      'default' => TRUE,
    ];

    $base_schema['properties']['showInputForm'] = [
      'type' => 'boolean',
      'title' => 'Show Input Form',
      'description' => 'Whether to show an input form when manually triggering',
      'default' => FALSE,
    ];

    return $base_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    $base_schema = parent::getInputSchema();

    // Add manual trigger specific input fields.
    $base_schema['properties']['customData'] = [
      'type' => 'object',
      'title' => 'Custom Data',
      'description' => 'Custom data provided during manual execution',
      'required' => FALSE,
    ];

    $base_schema['properties']['executionNotes'] = [
      'type' => 'string',
      'title' => 'Execution Notes',
      'description' => 'Optional notes about this manual execution',
      'required' => FALSE,
    ];

    return $base_schema;
  }

}
