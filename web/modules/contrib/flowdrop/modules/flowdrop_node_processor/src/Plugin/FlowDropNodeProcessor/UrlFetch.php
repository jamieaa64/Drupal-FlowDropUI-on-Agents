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
 * Executor for URL Fetch nodes.
 */
#[FlowDropNodeProcessor(
  id: "url_fetch",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("URL Fetch"),
  type: "default",
  supportedTypes: ["default"],
  category: "network",
  description: "Fetch data from URLs",
  version: "1.0.0",
  tags: ["url", "fetch", "network"]
)]
class UrlFetch extends AbstractFlowDropNodeProcessor {

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
    $method = $config->getConfig('method', 'GET');
    $headers = $config->getConfig('headers', []);
    $timeout = $config->getConfig('timeout', 30);
    $followRedirects = $config->getConfig('followRedirects', TRUE);
    $parseJson = $config->getConfig('parseJson', FALSE);

    // Use input if available.
    $url = $inputs->get('url') ?: $url;

    $response = [
      'success' => FALSE,
      'status_code' => 0,
      'content' => '',
      'headers' => [],
      'error' => '',
    ];

    if (!empty($url)) {
      try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);

        // Set headers.
        if (!empty($headers)) {
          $headerLines = [];
          foreach ($headers as $key => $value) {
            $headerLines[] = "$key: $value";
          }
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (empty($error)) {
          // Split headers and body.
          $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
          $responseHeaders = substr($rawResponse, 0, $headerSize);
          $responseBody = substr($rawResponse, $headerSize);

          // Parse headers.
          $headers = [];
          foreach (explode("\n", $responseHeaders) as $line) {
            if (strpos($line, ':') !== FALSE) {
              [$key, $value] = explode(':', $line, 2);
              $headers[trim($key)] = trim($value);
            }
          }

          // Parse JSON if requested.
          $parsedContent = $responseBody;
          if ($parseJson) {
            $jsonData = json_decode($responseBody, TRUE);
            if (json_last_error() === JSON_ERROR_NONE) {
              $parsedContent = $jsonData;
            }
          }

          $response = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status_code' => $httpCode,
            'content' => $parsedContent,
            'raw_content' => $responseBody,
            'headers' => $headers,
            'error' => '',
          ];
        }
        else {
          $response['error'] = $error;
        }

      }
      catch (\Exception $e) {
        $response['error'] = $e->getMessage();
      }
    }
    else {
      $response['error'] = 'No URL provided';
    }

    $this->getLogger()->info('URL fetch executed successfully', [
      'url' => $url,
      'method' => $method,
      'status_code' => $response['status_code'],
      'success' => $response['success'],
    ]);

    return [
      'response' => $response,
      'url' => $url,
      'method' => $method,
      'timeout' => $timeout,
      'follow_redirects' => $followRedirects,
      'parse_json' => $parseJson,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // URL fetch nodes can accept any inputs or none.
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
          'description' => 'The HTTP response',
        ],
        'url' => [
          'type' => 'string',
          'description' => 'The URL that was fetched',
        ],
        'method' => [
          'type' => 'string',
          'description' => 'The HTTP method used',
        ],
        'timeout' => [
          'type' => 'integer',
          'description' => 'The timeout used',
        ],
        'follow_redirects' => [
          'type' => 'boolean',
          'description' => 'Whether redirects were followed',
        ],
        'parse_json' => [
          'type' => 'boolean',
          'description' => 'Whether JSON was parsed',
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
          'description' => 'The URL to fetch',
          'default' => '',
        ],
        'method' => [
          'type' => 'string',
          'title' => 'Method',
          'description' => 'HTTP method to use',
          'default' => 'GET',
          'enum' => ['GET', 'POST', 'PUT', 'DELETE'],
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
        'followRedirects' => [
          'type' => 'boolean',
          'title' => 'Follow Redirects',
          'description' => 'Whether to follow HTTP redirects',
          'default' => TRUE,
        ],
        'parseJson' => [
          'type' => 'boolean',
          'title' => 'Parse JSON',
          'description' => 'Whether to parse JSON response',
          'default' => FALSE,
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
        'url' => [
          'type' => 'string',
          'title' => 'URL',
          'description' => 'The URL to fetch',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
