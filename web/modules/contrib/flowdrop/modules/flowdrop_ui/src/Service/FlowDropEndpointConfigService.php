<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui\Service;

use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Service for generating FlowDrop endpoint configuration.
 */
class FlowDropEndpointConfigService {

  /**
   * Constructs a FlowDropEndpointConfigService object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Generate the default endpoint configuration for FlowDrop.
   *
   * @param string $base_url
   *   The base URL for the API endpoints.
   *
   * @return array
   *   The endpoint configuration array.
   */
  public function generateEndpointConfig(string $base_url): array {
    return [
      'baseUrl' => $base_url,
      'endpoints' => [
        'nodes' => [
          'list' => '/nodes',
          'get' => '/nodes/{id}',
          'byCategory' => '/nodes?category={category}',
          'metadata' => '/nodes/{id}/metadata',
        ],
        'workflows' => [
          'list' => '/workflows',
          'get' => '/workflows/{id}',
          'create' => '/workflows',
          'update' => '/workflows/{id}',
          'delete' => '/workflows/{id}',
          'validate' => '/workflows/validate',
          'export' => '/workflows/{id}/export',
          'import' => '/workflows/import',
        ],
        'executions' => [
          'execute' => '/workflows/{id}/execute',
          'status' => '/executions/{id}',
          'cancel' => '/executions/{id}/cancel',
          'logs' => '/executions/{id}/logs',
          'history' => '/executions',
        ],
        'templates' => [
          'list' => '/templates',
          'get' => '/templates/{id}',
          'create' => '/templates',
          'update' => '/templates/{id}',
          'delete' => '/templates/{id}',
        ],
        'users' => [
          'profile' => '/users/profile',
          'preferences' => '/users/preferences',
        ],
        'system' => [
          'health' => '/system/health',
          'config' => '/system/config',
          'version' => '/system/version',
        ],
      ],
      'timeout' => 30000,
      'retry' => [
        'enabled' => TRUE,
        'maxAttempts' => 3,
        'delay' => 1000,
        'backoff' => 'exponential',
      ],
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
    ];
  }

}
