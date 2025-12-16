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
 * Executor for Scheduled Trigger nodes.
 *
 * Scheduled triggers execute workflows on a schedule
 * using cron-like expressions.
 * They provide time-based workflow automation.
 */
#[FlowDropNodeProcessor(
  id: "scheduled_trigger",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Scheduled Trigger"),
  type: "trigger",
  supportedTypes: ["default"],
  category: "trigger",
  description: "Trigger workflows on schedule",
  version: "1.0.0",
  tags: ["trigger", "schedule", "workflow"]
)]
class ScheduledTrigger extends AbstractTrigger {

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
    return 'schedule';
  }

  /**
   * {@inheritdoc}
   */
  protected function processTriggerData(array $trigger_data, array $inputs): array {
    $now = new \DateTime();

    $schedule_data = [
      'scheduled_time' => $now->format('Y-m-d H:i:s'),
      'timestamp' => $now->getTimestamp(),
      'timezone' => $now->getTimezone()->getName(),
      'year' => (int) $now->format('Y'),
      'month' => (int) $now->format('n'),
      'day' => (int) $now->format('j'),
      'hour' => (int) $now->format('G'),
      'minute' => (int) $now->format('i'),
      'second' => (int) $now->format('s'),
      'day_of_week' => (int) $now->format('w'),
      'day_of_year' => (int) $now->format('z'),
      'week_of_year' => (int) $now->format('W'),
    ];

    // Add schedule trigger specific metadata.
    $schedule_data['schedule_execution'] = TRUE;
    $schedule_data['execution_source'] = 'schedule';
    $schedule_data['schedule_expression'] = $this->configuration['scheduleExpression'] ?? '';

    // Merge with configured trigger data and inputs.
    return array_merge($trigger_data, $schedule_data, $inputs);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    $base_schema = parent::getConfigSchema();

    // Add scheduled trigger specific configuration.
    $base_schema['properties']['scheduleExpression'] = [
      'type' => 'string',
      'title' => 'Schedule Expression',
      'description' => 'Cron expression for scheduled execution (e.g., "0 2 * * *" for daily at 2 AM)',
      'pattern' => '^(\*|[0-9,\-*/]+)\s+(\*|[0-9,\-*/]+)\s+(\*|[0-9,\-*/]+)\s+(\*|[0-9,\-*/]+)\s+(\*|[0-9,\-*/]+)$',
    // Daily at midnight.
      'default' => '0 0 * * *',
    ];

    $base_schema['properties']['timezone'] = [
      'type' => 'string',
      'title' => 'Timezone',
      'description' => 'Timezone for the schedule (e.g., "UTC", "America/New_York")',
      'default' => 'UTC',
    ];

    $base_schema['properties']['startDate'] = [
      'type' => 'string',
      'title' => 'Start Date',
      'description' => 'Date when the schedule should start (YYYY-MM-DD)',
      'format' => 'date',
      'default' => '',
    ];

    $base_schema['properties']['endDate'] = [
      'type' => 'string',
      'title' => 'End Date',
      'description' => 'Date when the schedule should end (YYYY-MM-DD)',
      'format' => 'date',
      'default' => '',
    ];

    $base_schema['properties']['maxExecutions'] = [
      'type' => 'integer',
      'title' => 'Max Executions',
      'description' => 'Maximum number of times this schedule can execute (0 = unlimited)',
      'minimum' => 0,
      'default' => 0,
    ];

    $base_schema['properties']['executionCount'] = [
      'type' => 'integer',
      'title' => 'Execution Count',
      'description' => 'Current number of executions (read-only)',
      'minimum' => 0,
      'default' => 0,
    ];

    $base_schema['properties']['lastExecution'] = [
      'type' => 'string',
      'title' => 'Last Execution',
      'description' => 'Timestamp of the last execution',
      'format' => 'date-time',
      'default' => '',
    ];

    $base_schema['properties']['nextExecution'] = [
      'type' => 'string',
      'title' => 'Next Execution',
      'description' => 'Timestamp of the next scheduled execution',
      'format' => 'date-time',
      'default' => '',
    ];

    return $base_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    $base_schema = parent::getInputSchema();

    // Add scheduled trigger specific input fields.
    $base_schema['properties']['schedule_data'] = [
      'type' => 'object',
      'title' => 'Schedule Data',
      'description' => 'Data specific to the scheduled execution',
      'required' => FALSE,
    ];

    $base_schema['properties']['execution_context'] = [
      'type' => 'object',
      'title' => 'Execution Context',
      'description' => 'Context information about the scheduled execution',
      'required' => FALSE,
    ];

    return $base_schema;
  }

  /**
   * Validate a cron expression.
   *
   * @param string $expression
   *   The cron expression to validate.
   *
   * @return bool
   *   TRUE if the expression is valid.
   */
  public function validateCronExpression(string $expression): bool {
    // Basic cron expression validation.
    $parts = explode(' ', trim($expression));
    if (count($parts) !== 5) {
      return FALSE;
    }

    // Validate each part.
    foreach ($parts as $part) {
      if (!$this->validateCronPart($part)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Validate a single cron expression part.
   *
   * @param string $part
   *   The cron expression part to validate.
   *
   * @return bool
   *   TRUE if the part is valid.
   */
  protected function validateCronPart(string $part): bool {
    if ($part === '*') {
      return TRUE;
    }

    // Check for ranges (e.g., 1-5)
    if (strpos($part, '-') !== FALSE) {
      $range = explode('-', $part);
      if (count($range) !== 2 || !is_numeric($range[0]) || !is_numeric($range[1])) {
        return FALSE;
      }
      return TRUE;
    }

    // Check for lists (e.g., 1,3,5)
    if (strpos($part, ',') !== FALSE) {
      $list = explode(',', $part);
      foreach ($list as $item) {
        if (!is_numeric($item)) {
          return FALSE;
        }
      }
      return TRUE;
    }

    // Check for step values (e.g., */5)
    if (strpos($part, '/') !== FALSE) {
      $step = explode('/', $part);
      if (count($step) !== 2 || !is_numeric($step[1])) {
        return FALSE;
      }
      return TRUE;
    }

    // Check for single numeric values.
    return is_numeric($part);
  }

}
