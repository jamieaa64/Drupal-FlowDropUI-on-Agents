<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Service;

use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Service for generating FlowDrop AI endpoint configuration.
 *
 * Provides endpoint configuration for the FlowDrop UI to communicate
 * with AI Agent API endpoints instead of flowdrop_workflow endpoints.
 */
class FlowDropAiEndpointConfigService {

  /**
   * Constructs a FlowDropAiEndpointConfigService object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Generate the endpoint configuration for FlowDrop AI Agent editing.
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
        // Nodes = Tools in AI context.
        'nodes' => [
          'list' => '/tools',
          'get' => '/tools/{id}',
          'byCategory' => '/tools/by-category',
          'metadata' => '/tools/{id}/schema',
        ],
        // Workflows = AI Agents in AI context.
        'workflows' => [
          'list' => '/agents',
          'get' => '/agents/{id}/workflow',
          'create' => '/workflow/save',
          'update' => '/workflow/save',
          'delete' => '/agents/{id}',
          'validate' => '/workflow/validate',
          'export' => '/agents/{id}/workflow',
          'import' => '/workflow/save',
        ],
        // Agent-specific endpoints.
        'agents' => [
          'list' => '/agents',
          'get' => '/agents/{id}',
          'create' => '/agents',
          'update' => '/agents/{id}',
          'delete' => '/agents/{id}',
          'asWorkflow' => '/agents/{id}/workflow',
          'available' => '/available-agents',
          'schema' => '/schema/agent',
        ],
        // Tool-specific endpoints.
        'tools' => [
          'list' => '/tools',
          'get' => '/tools/{id}',
          'byCategory' => '/tools/by-category',
          'search' => '/tools/search',
          'schema' => '/tools/{id}/schema',
          'validate' => '/tools/{id}/validate',
        ],
        // Position storage.
        'positions' => [
          'store' => '/agents/{id}/positions',
          'load' => '/agents/{id}/positions',
        ],
        // Executions not supported yet but included for compatibility.
        'executions' => [
          'execute' => '/agents/{id}/execute',
          'status' => '/executions/{id}',
          'cancel' => '/executions/{id}/cancel',
          'logs' => '/executions/{id}/logs',
          'history' => '/executions',
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
      // Custom settings for AI Agent mode.
      'aiAgentMode' => TRUE,
    ];
  }

}
