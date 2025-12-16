<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Promise\Utils;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for HTTP Request nodes.
 */
#[FlowDropNodeProcessor(
  id: "http_request",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("HTTP Request"),
  type: "default",
  supportedTypes: ["default"],
  category: "network",
  description: "HTTP request operations",
  version: "1.0.0",
  tags: ["http", "network", "request"]
)]
class HttpRequest extends AbstractFlowDropNodeProcessor {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelFactoryInterface $loggerFactory,
    private readonly ClientFactory $clientFactory,
  ) {
    $this->httpClient = $this->clientFactory->fromOptions([
      'timeout' => 30,
      'connect_timeout' => 10,
    ]);
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
      $container->get('logger.factory'),
      $container->get('http_client_factory')
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
    // Allow runtime inputs to override config values.
    $url = $inputs->get('url') ?? $config->getConfig('url', '');
    $method = strtoupper($inputs->get('method') ?? $config->getConfig('method', 'GET'));

    // Merge config headers with runtime headers (runtime takes precedence).
    $config_headers = $config->getConfig('headers', []);
    $runtime_headers = $inputs->get('headers', []);
    $headers = array_merge($config_headers, $runtime_headers);

    $body = $inputs->get('body') ?? $config->getConfig('body', '');
    $timeout = $config->getConfig('timeout', 30);

    if (empty($url)) {
      throw new \Exception('No URL provided for HTTP request');
    }

    // Validate URL.
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \Exception('Invalid URL provided: ' . $url);
    }

    try {
      // Prepare request options.
      $options = [
        'timeout' => $timeout,
        'headers' => $headers,
      ];

      // Add body for POST/PUT requests.
      if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($body)) {
        $options['body'] = $body;
      }

      // Make the request.
      $response = $this->httpClient->request($method, $url, $options);

      // Get response data.
      $status_code = $response->getStatusCode();
      $response_headers = $response->getHeaders();
      $response_body = $response->getBody()->getContents();

      // Try to parse JSON response.
      $json_data = NULL;
      if (strpos($response_headers['Content-Type'][0] ?? '', 'application/json') !== FALSE) {
        $json_data = json_decode($response_body, TRUE);
      }

      $this->getLogger()->info('HTTP request executed successfully: @method @url (@status)', [
        '@method' => $method,
        '@url' => $url,
        '@status' => $status_code,
      ]);

      return [
        'status_code' => $status_code,
        'headers' => $response_headers,
        'body' => $response_body,
        'json' => $json_data,
        'url' => $url,
        'method' => $method,
        'request_time' => microtime(TRUE),
      ];

    }
    catch (RequestException $e) {
      $this->getLogger()->error('HTTP request failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw new \Exception('HTTP request failed: ' . $e->getMessage());
    }
    catch (\Exception $e) {
      $this->getLogger()->error('HTTP request error: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // HTTP Request nodes don't require specific inputs.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'status_code' => [
          'type' => 'integer',
          'description' => 'HTTP response status code',
        ],
        'headers' => [
          'type' => 'array',
          'description' => 'HTTP response headers',
        ],
        'body' => [
          'type' => 'string',
          'description' => 'HTTP response body',
        ],
        'json' => [
          'type' => 'array',
          'description' => 'Parsed JSON response (if applicable)',
        ],
        'url' => [
          'type' => 'string',
          'description' => 'The URL that was requested',
        ],
        'method' => [
          'type' => 'string',
          'description' => 'The HTTP method used',
        ],
        'request_time' => [
          'type' => 'float',
          'description' => 'Timestamp when request was made',
        ],
      ],
    ];
  }

  /**
   * Test connection to a URL.
   *
   * @param string $url
   *   The URL to test.
   * @param int $timeout
   *   Connection timeout.
   *
   * @return array
   *   Test results.
   */
  protected function testConnection(string $url, int $timeout): array {
    try {
      $start_time = microtime(TRUE);

      // Use HEAD request for faster connection test.
      $response = $this->httpClient->request('HEAD', $url, [
        'timeout' => $timeout,
        'connect_timeout' => $timeout,
      ]);

      $response_time = microtime(TRUE) - $start_time;

      return [
        'reachable' => TRUE,
        'status_code' => $response->getStatusCode(),
        'response_time' => $response_time,
        'url' => $url,
      ];
    }
    catch (\Exception $e) {
      return [
        'reachable' => FALSE,
        'error' => $e->getMessage(),
        'url' => $url,
      ];
    }
  }

  /**
   * Validate URL format and accessibility.
   *
   * @param string $url
   *   The URL to validate.
   * @param bool $check_ssl
   *   Whether to check SSL certificate.
   *
   * @return array
   *   Validation results.
   */
  protected function validateUrl(string $url, bool $check_ssl): array {
    $errors = [];
    $warnings = [];

    // Check URL format.
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      $errors[] = 'Invalid URL format';
    }

    // Check protocol.
    $parsed_url = parse_url($url);
    if ($parsed_url && isset($parsed_url['scheme'])) {
      if (!in_array($parsed_url['scheme'], ['http', 'https'])) {
        $errors[] = 'Unsupported protocol: ' . $parsed_url['scheme'];
      }
    }

    // Test connection if URL format is valid.
    if (empty($errors)) {
      $connection_test = $this->testConnection($url, 10);

      if (!$connection_test['reachable']) {
        $errors[] = 'URL is not reachable: ' . $connection_test['error'];
      }
      else {
        // Check SSL certificate for HTTPS URLs.
        if ($check_ssl && strpos($url, 'https://') === 0) {
          $ssl_test = $this->testSslCertificate($url);
          if (!$ssl_test['valid']) {
            $warnings[] = 'SSL certificate issue: ' . $ssl_test['error'];
          }
        }
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'warnings' => $warnings,
      'url' => $url,
    ];
  }

  /**
   * Test SSL certificate for HTTPS URLs.
   *
   * @param string $url
   *   The HTTPS URL to test.
   *
   * @return array
   *   SSL test results.
   */
  protected function testSslCertificate(string $url): array {
    try {
      $context = stream_context_create([
        'ssl' => [
          'capture_peer_cert' => TRUE,
          'verify_peer' => FALSE,
          'verify_peer_name' => FALSE,
        ],
      ]);

      $parsed_url = parse_url($url);
      $host = $parsed_url['host'];
      $port = $parsed_url['port'] ?? 443;

      $socket = stream_socket_client(
        "ssl://{$host}:{$port}",
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
      );

      if (!$socket) {
        return [
          'valid' => FALSE,
          'error' => "Failed to connect: {$errstr}",
        ];
      }

      $cert_data = stream_context_get_params($socket);
      fclose($socket);

      return [
        'valid' => TRUE,
        'certificate' => $cert_data,
      ];
    }
    catch (\Exception $e) {
      return [
        'valid' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Make multiple HTTP requests in parallel.
   *
   * @param array $urls
   *   Array of URLs to request.
   * @param string $method
   *   HTTP method for all requests.
   * @param int $concurrency
   *   Maximum concurrent requests.
   *
   * @return array
   *   Batch request results.
   */
  protected function batchRequest(array $urls, string $method, int $concurrency): array {
    $results = [];
    $chunks = array_chunk($urls, $concurrency);

    foreach ($chunks as $chunk) {
      $promises = [];

      foreach ($chunk as $url) {
        $promises[$url] = $this->httpClient->requestAsync($method, $url);
      }

      // Wait for all promises in this chunk.
      $responses = Utils::unwrap($promises);

      foreach ($responses as $url => $response) {
        $results[$url] = [
          'status_code' => $response->getStatusCode(),
          'body' => $response->getBody()->getContents(),
          'headers' => $response->getHeaders(),
        ];
      }
    }

    return [
      'total_requests' => count($urls),
      'successful_requests' => count(array_filter($results, fn($r) => $r['status_code'] < 400)),
      'failed_requests' => count(array_filter($results, fn($r) => $r['status_code'] >= 400)),
      'results' => $results,
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
          'description' => 'The URL to request',
          'default' => '',
        ],
        'method' => [
          'type' => 'string',
          'title' => 'HTTP Method',
          'description' => 'The HTTP method to use',
          'default' => 'GET',
          'enum' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'],
        ],
        'headers' => [
          'type' => 'object',
          'title' => 'Headers',
          'description' => 'HTTP headers to send',
          'default' => [],
        ],
        'body' => [
          'type' => 'string',
          'title' => 'Request Body',
          'description' => 'Request body for POST/PUT requests',
          'default' => '',
        ],
        'timeout' => [
          'type' => 'integer',
          'title' => 'Timeout',
          'description' => 'Request timeout in seconds',
          'default' => 30,
          'minimum' => 1,
          'maximum' => 300,
        ],
        'follow_redirects' => [
          'type' => 'boolean',
          'title' => 'Follow Redirects',
          'description' => 'Whether to follow HTTP redirects',
          'default' => TRUE,
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
          'description' => 'The URL to request (overrides config)',
          'required' => FALSE,
        ],
        'method' => [
          'type' => 'string',
          'title' => 'HTTP Method',
          'description' => 'The HTTP method to use (overrides config)',
          'required' => FALSE,
        ],
        'headers' => [
          'type' => 'object',
          'title' => 'Headers',
          'description' => 'Additional headers to send',
          'required' => FALSE,
        ],
        'body' => [
          'type' => 'string',
          'title' => 'Request Body',
          'description' => 'Request body (overrides config)',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
