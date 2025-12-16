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
 * Executor for Webhook Trigger nodes.
 *
 * Webhook triggers process incoming HTTP requests and start workflow execution
 * with the webhook payload data.
 */
#[FlowDropNodeProcessor(
  id: "webhook_trigger",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Webhook Trigger"),
  type: "trigger",
  supportedTypes: ["default"],
  category: "trigger",
  description: "Trigger workflows via webhooks",
  version: "1.0.0",
  tags: ["trigger", "webhook", "workflow"]
)]
class WebhookTrigger extends AbstractTrigger {

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
    return 'webhook';
  }

  /**
   * {@inheritdoc}
   */
  protected function processTriggerData(array $trigger_data, array $inputs): array {
    $webhook_data = [];

    // Extract common webhook fields.
    if (!empty($inputs)) {
      $webhook_data = [
        'method' => $inputs['method'] ?? 'POST',
        'headers' => $inputs['headers'] ?? [],
        'body' => $inputs['body'] ?? [],
        'query_params' => $inputs['query_params'] ?? [],
        'path' => $inputs['path'] ?? '',
        'ip' => $inputs['ip'] ?? '',
        'user_agent' => $inputs['user_agent'] ?? '',
        'content_type' => $inputs['content_type'] ?? 'application/json',
        'content_length' => $inputs['content_length'] ?? 0,
      ];
    }

    // Add webhook trigger specific metadata.
    $webhook_data['webhook_execution'] = TRUE;
    $webhook_data['execution_source'] = 'webhook';
    $webhook_data['webhook_timestamp'] = time();

    // Merge with configured trigger data.
    return array_merge($trigger_data, $webhook_data);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    $base_schema = parent::getConfigSchema();

    // Add webhook trigger specific configuration.
    $base_schema['properties']['webhookUrl'] = [
      'type' => 'string',
      'title' => 'Webhook URL',
      'description' => 'The webhook URL endpoint for this trigger',
      'format' => 'uri',
      'default' => '',
    ];

    $base_schema['properties']['allowedMethods'] = [
      'type' => 'array',
      'title' => 'Allowed HTTP Methods',
      'description' => 'HTTP methods that are allowed for this webhook',
      'items' => [
        'type' => 'string',
        'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
      ],
      'default' => ['POST'],
    ];

    $base_schema['properties']['requireAuthentication'] = [
      'type' => 'boolean',
      'title' => 'Require Authentication',
      'description' => 'Whether to require authentication for webhook requests',
      'default' => FALSE,
    ];

    $base_schema['properties']['authenticationToken'] = [
      'type' => 'string',
      'title' => 'Authentication Token',
      'description' => 'Token for webhook authentication',
      'default' => '',
    ];

    $base_schema['properties']['validateSignature'] = [
      'type' => 'boolean',
      'title' => 'Validate Signature',
      'description' => 'Whether to validate webhook signatures',
      'default' => FALSE,
    ];

    $base_schema['properties']['signatureSecret'] = [
      'type' => 'string',
      'title' => 'Signature Secret',
      'description' => 'Secret for validating webhook signatures',
      'default' => '',
    ];

    $base_schema['properties']['maxPayloadSize'] = [
      'type' => 'integer',
      'title' => 'Max Payload Size',
      'description' => 'Maximum payload size in bytes',
    // 1MB
      'default' => 1048576,
    ];

    return $base_schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    $base_schema = parent::getInputSchema();

    // Add webhook trigger specific input fields.
    $base_schema['properties']['webhook_payload'] = [
      'type' => 'object',
      'title' => 'Webhook Payload',
      'description' => 'The complete webhook payload data',
      'required' => FALSE,
    ];

    $base_schema['properties']['method'] = [
      'type' => 'string',
      'title' => 'HTTP Method',
      'description' => 'The HTTP method used for the webhook request',
      'required' => FALSE,
    ];

    $base_schema['properties']['headers'] = [
      'type' => 'object',
      'title' => 'HTTP Headers',
      'description' => 'HTTP headers from the webhook request',
      'required' => FALSE,
    ];

    $base_schema['properties']['body'] = [
      'type' => 'mixed',
      'title' => 'Request Body',
      'description' => 'The request body from the webhook',
      'required' => FALSE,
    ];

    $base_schema['properties']['query_params'] = [
      'type' => 'object',
      'title' => 'Query Parameters',
      'description' => 'Query parameters from the webhook URL',
      'required' => FALSE,
    ];

    $base_schema['properties']['ip'] = [
      'type' => 'string',
      'title' => 'Client IP',
      'description' => 'The IP address of the webhook sender',
      'required' => FALSE,
    ];

    $base_schema['properties']['user_agent'] = [
      'type' => 'string',
      'title' => 'User Agent',
      'description' => 'The user agent from the webhook request',
      'required' => FALSE,
    ];

    return $base_schema;
  }

}
