<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\DTO\Output;
use Drupal\flowdrop\DTO\OutputInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;

/**
 * Abstract base class for Trigger nodes.
 *
 * This class provides common functionality for all trigger types
 * and defines the contract that trigger implementations must follow.
 */
abstract class AbstractTrigger extends AbstractFlowDropNodeProcessor {

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $inputs, ConfigInterface $config): OutputInterface {
    $trigger_type = $this->getTriggerType();
    $trigger_data = $config->getConfig('triggerData', []);
    $workflow_id = $config->getConfig('workflowId', '');
    $pipeline_id = $config->getConfig('pipelineId', '');

    // Log the trigger execution.
    $this->getLogger()->info('Trigger executed successfully', [
      'trigger_type' => $trigger_type,
      'workflow_id' => $workflow_id,
      'pipeline_id' => $pipeline_id,
      'inputs_count' => count($inputs->toArray()),
      'trigger_data' => $trigger_data,
    ]);

    // Process trigger data based on type.
    $processed_data = $this->processTriggerData($trigger_data, $inputs->toArray());

    // Create output object.
    $output = new Output();
    $output->setStatus('success');
    $output->fromArray([
      'trigger_type' => $trigger_type,
      'trigger_data' => $processed_data,
      'workflow_id' => $workflow_id,
      'pipeline_id' => $pipeline_id,
      'timestamp' => time(),
      'execution_id' => $this->generateExecutionId(),
    ]);

    return $output;
  }

  /**
   * Get the trigger type for this implementation.
   *
   * @return string
   *   The trigger type (manual, webhook, schedule, event, etc.).
   */
  abstract protected function getTriggerType(): string;

  /**
   * Process trigger data for this specific trigger type.
   *
   * @param array $trigger_data
   *   The configured trigger data.
   * @param array $inputs
   *   The input data passed to the trigger.
   *
   * @return array
   *   The processed trigger data.
   */
  abstract protected function processTriggerData(array $trigger_data, array $inputs): array;

  /**
   * Generate a unique execution ID for this trigger execution.
   *
   * @return string
   *   A unique execution ID.
   */
  protected function generateExecutionId(): string {
    return uniqid('trigger_' . $this->getTriggerType() . '_', TRUE);
  }

  /**
   * Get a configuration value with default.
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
    return $config->getConfig($key, $default);
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Triggers can accept any inputs or none
    // The validation is more about the trigger configuration than inputs.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'trigger_type' => [
          'type' => 'string',
          'description' => 'The type of trigger that was executed',
        ],
        'trigger_data' => [
          'type' => 'object',
          'description' => 'The processed trigger data',
        ],
        'workflow_id' => [
          'type' => 'string',
          'description' => 'The ID of the workflow being triggered',
        ],
        'pipeline_id' => [
          'type' => 'string',
          'description' => 'The ID of the pipeline being triggered',
        ],
        'timestamp' => [
          'type' => 'integer',
          'description' => 'The timestamp when the trigger was executed',
        ],
        'execution_id' => [
          'type' => 'string',
          'description' => 'Unique execution ID for this trigger execution',
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
        'triggerData' => [
          'type' => 'object',
          'title' => 'Trigger Data',
          'description' => 'Default data to use when the trigger is activated',
          'default' => [],
        ],
        'workflowId' => [
          'type' => 'string',
          'title' => 'Workflow ID',
          'description' => 'The ID of the workflow to trigger',
          'default' => '',
        ],
        'pipelineId' => [
          'type' => 'string',
          'title' => 'Pipeline ID',
          'description' => 'The ID of the pipeline to trigger',
          'default' => '',
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Optional description for this trigger',
          'default' => '',
        ],
        'enabled' => [
          'type' => 'boolean',
          'title' => 'Enabled',
          'description' => 'Whether this trigger is enabled',
          'default' => TRUE,
        ],
      ],
      'required' => [],
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
          'type' => 'mixed',
          'title' => 'Trigger Input Data',
          'description' => 'Input data for the trigger',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
