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
 * Executor for Event Trigger nodes.
 *
 * Event triggers respond to system events and start workflow execution
 * when specific events occur in the system.
 */
#[FlowDropNodeProcessor(
  id: "event_trigger",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Event Trigger"),
  type: "trigger",
  supportedTypes: ["default"],
  category: "trigger",
  description: "Trigger workflows based on system events",
  version: "1.0.0",
  tags: ["trigger", "event", "workflow"]
)]
class EventTrigger extends AbstractTrigger {

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
    return 'event';
  }

  /**
   * {@inheritdoc}
   */
  protected function processTriggerData(array $trigger_data, array $inputs): array {
    $event_data = [];

    if (!empty($inputs)) {
      $event_data = [
        'event_type' => $inputs['event_type'] ?? 'unknown',
        'event_data' => $inputs['event_data'] ?? [],
        'source' => $inputs['source'] ?? 'system',
        'timestamp' => $inputs['timestamp'] ?? time(),
        'event_id' => $inputs['event_id'] ?? uniqid('event_', TRUE),
        'priority' => $inputs['priority'] ?? 'normal',
        'category' => $inputs['category'] ?? 'general',
        'severity' => $inputs['severity'] ?? 'info',
      ];
    }

    // Add event trigger specific metadata.
    $event_data['event_execution'] = TRUE;
    $event_data['execution_source'] = 'event';
    $event_data['event_timestamp'] = time();

    // Merge with configured trigger data.
    return array_merge($trigger_data, $event_data);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    $base_schema = parent::getConfigSchema();

    // Add event trigger specific configuration.
    $base_schema['properties']['eventTypes'] = [
      'type' => 'array',
      'title' => 'Event Types',
      'description' => 'Types of events that should trigger this workflow',
      'items' => [
        'type' => 'string',
      ],
      'default' => [],
    ];

    $base_schema['properties']['eventSources'] = [
      'type' => 'array',
      'title' => 'Event Sources',
      'description' => 'Sources of events that should trigger this workflow',
      'items' => [
        'type' => 'string',
      ],
      'default' => [],
    ];

    $base_schema['properties']['eventCategories'] = [
      'type' => 'array',
      'title' => 'Event Categories',
      'description' => 'Categories of events that should trigger this workflow',
      'items' => [
        'type' => 'string',
      ],
      'default' => [],
    ];

    $base_schema['properties']['minSeverity'] = [
      'type' => 'string',
      'title' => 'Minimum Severity',
      'description' => 'Minimum severity level for events to trigger this workflow',
      'enum' => ['debug', 'info', 'warning', 'error', 'critical'],
      'default' => 'info',
    ];

    $base_schema['properties']['eventFilters'] = [
      'type' => 'object',
      'title' => 'Event Filters',
      'description' => 'Additional filters for events',
      'default' => [],
    ];

    $base_schema['properties']['eventTimeout'] = [
      'type' => 'integer',
      'title' => 'Event Timeout',
      'description' => 'Timeout in seconds for event processing',
      'minimum' => 1,
      'default' => 30,
    ];

    $base_schema['properties']['eventRetryCount'] = [
      'type' => 'integer',
      'title' => 'Event Retry Count',
      'description' => 'Number of times to retry failed event processing',
      'minimum' => 0,
      'default' => 3,
    ];

    $base_schema['properties']['eventRetryDelay'] = [
      'type' => 'integer',
      'title' => 'Event Retry Delay',
      'description' => 'Delay in seconds between event retries',
      'minimum' => 1,
      'default' => 5,
    ];

    return $base_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    $base_schema = parent::getInputSchema();

    // Add event trigger specific input fields.
    $base_schema['properties']['event_data'] = [
      'type' => 'object',
      'title' => 'Event Data',
      'description' => 'The complete event data payload',
      'required' => FALSE,
    ];

    $base_schema['properties']['event_type'] = [
      'type' => 'string',
      'title' => 'Event Type',
      'description' => 'The type of event that occurred',
      'required' => FALSE,
    ];

    $base_schema['properties']['source'] = [
      'type' => 'string',
      'title' => 'Event Source',
      'description' => 'The source of the event',
      'required' => FALSE,
    ];

    $base_schema['properties']['event_id'] = [
      'type' => 'string',
      'title' => 'Event ID',
      'description' => 'Unique identifier for the event',
      'required' => FALSE,
    ];

    $base_schema['properties']['priority'] = [
      'type' => 'string',
      'title' => 'Event Priority',
      'description' => 'Priority level of the event',
      'enum' => ['low', 'normal', 'high', 'urgent'],
      'required' => FALSE,
    ];

    $base_schema['properties']['category'] = [
      'type' => 'string',
      'title' => 'Event Category',
      'description' => 'Category of the event',
      'required' => FALSE,
    ];

    $base_schema['properties']['severity'] = [
      'type' => 'string',
      'title' => 'Event Severity',
      'description' => 'Severity level of the event',
      'enum' => ['debug', 'info', 'warning', 'error', 'critical'],
      'required' => FALSE,
    ];

    $base_schema['properties']['context'] = [
      'type' => 'object',
      'title' => 'Event Context',
      'description' => 'Additional context information about the event',
      'required' => FALSE,
    ];

    return $base_schema;
  }

  /**
   * Check if an event matches the configured filters.
   *
   * @param array $event_data
   *   The event data to check.
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The trigger configuration.
   *
   * @return bool
   *   TRUE if the event matches the filters.
   */
  public function eventMatchesFilters(array $event_data, ConfigInterface $config): bool {
    // Check event type filter.
    $event_types = $config->getConfig('eventTypes', []);
    if (!empty($event_types)) {
      $event_type = $event_data['event_type'] ?? '';
      if (!in_array($event_type, $event_types)) {
        return FALSE;
      }
    }

    // Check event source filter.
    $event_sources = $config->getConfig('eventSources', []);
    if (!empty($event_sources)) {
      $source = $event_data['source'] ?? '';
      if (!in_array($source, $event_sources)) {
        return FALSE;
      }
    }

    // Check event category filter.
    $event_categories = $config->getConfig('eventCategories', []);
    if (!empty($event_categories)) {
      $category = $event_data['category'] ?? '';
      if (!in_array($category, $event_categories)) {
        return FALSE;
      }
    }

    // Check minimum severity filter.
    $min_severity = $config->getConfig('minSeverity', 'info');
    $severity_levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];
    $event_severity = $event_data['severity'] ?? 'info';

    if (isset($severity_levels[$event_severity]) && isset($severity_levels[$min_severity])) {
      if ($severity_levels[$event_severity] < $severity_levels[$min_severity]) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
