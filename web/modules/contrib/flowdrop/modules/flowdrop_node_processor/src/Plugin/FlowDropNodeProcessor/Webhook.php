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
 * Executor for Webhook nodes.
 */
#[FlowDropNodeProcessor(
  id: "webhook",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Webhook"),
  type: "default",
  supportedTypes: ["default"],
  category: "network",
  description: "Webhook operations",
  version: "1.0.0",
  tags: ["webhook", "network", "http"]
)]
class Webhook extends AbstractFlowDropNodeProcessor {

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
    $url = $config->getConfig('url', '');
    $method = $config->getConfig('method', 'POST');
    $headers = $config->getConfig('headers', []);
    $timeout = $config->getConfig('timeout', 30);

    // Get the payload.
    $payload = $inputs->get('payload') ?: [];

    $response = [
      'success' => FALSE,
      'status_code' => 0,
      'response_body' => '',
      'error' => '',
    ];

    if (!empty($url)) {
      try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
          $jsonPayload = json_encode($payload);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
          $headers['Content-Type'] = 'application/json';
          $headers['Content-Length'] = strlen($jsonPayload);
        }

        if (!empty($headers)) {
          $headerLines = [];
          foreach ($headers as $key => $value) {
            $headerLines[] = "$key: $value";
          }
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $response = [
          'success' => $httpCode >= 200 && $httpCode < 300 && empty($error),
          'status_code' => $httpCode,
          'response_body' => $responseBody,
          'error' => $error,
        ];

      }
      catch (\Exception $e) {
        $response['error'] = $e->getMessage();
      }
    }
    else {
      $response['error'] = 'No URL provided';
    }

    $this->getLogger()->info('Webhook executed successfully', [
      'url' => $url,
      'method' => $method,
      'status_code' => $response['status_code'],
      'success' => $response['success'],
    ]);

    return [
      'response' => $response,
      'url' => $url,
      'method' => $method,
      'payload' => $payload,
      'headers' => $headers,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Webhook nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'response' => [
          'type' => 'object',
          'description' => 'The webhook response',
        ],
        'url' => [
          'type' => 'string',
          'description' => 'The webhook URL',
        ],
        'method' => [
          'type' => 'string',
          'description' => 'The HTTP method used',
        ],
        'payload' => [
          'type' => 'object',
          'description' => 'The payload sent',
        ],
        'headers' => [
          'type' => 'object',
          'description' => 'The headers sent',
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
        'url' => [
          'type' => 'string',
          'title' => 'URL',
          'description' => 'The webhook URL',
          'default' => '',
        ],
        'method' => [
          'type' => 'string',
          'title' => 'Method',
          'description' => 'HTTP method to use',
          'default' => 'POST',
          'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        ],
        'headers' => [
          'type' => 'object',
          'title' => 'Headers',
          'description' => 'HTTP headers to send',
          'default' => [],
        ],
        'timeout' => [
          'type' => 'integer',
          'title' => 'Timeout',
          'description' => 'Request timeout in seconds',
          'default' => 30,
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
        'payload' => [
          'type' => 'object',
          'title' => 'Payload',
          'description' => 'The data to send in the webhook',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
