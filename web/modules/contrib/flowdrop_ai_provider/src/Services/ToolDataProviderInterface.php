<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Services;

/**
 * Interface for providing tool data to the FlowDrop UI.
 *
 * This service provides information about available tools that can be
 * attached to AI Agents in the FlowDrop visual editor.
 *
 * @see \Drupal\ai_integration_eca_agents\Services\DataProvider\DataProvider
 */
interface ToolDataProviderInterface {

  /**
   * Gets all available tools that can be used by AI Agents.
   *
   * @param string $viewMode
   *   The view mode: 'teaser' for minimal data, 'full' for complete details.
   *
   * @return array
   *   Array of tool information, each containing:
   *   - id: Tool plugin ID (e.g., 'ai_agent:http_request')
   *   - label: Human-readable label
   *   - description: Tool description
   *   - category: Tool category for grouping
   *   - configuration: Available configuration options (in 'full' mode)
   */
  public function getAvailableTools(string $viewMode = 'teaser'): array;

  /**
   * Gets tools grouped by category.
   *
   * @return array
   *   Array keyed by category name, values are arrays of tools.
   */
  public function getToolsByCategory(): array;

  /**
   * Gets a single tool's information.
   *
   * @param string $toolId
   *   The tool plugin ID.
   *
   * @return array|null
   *   Tool information array, or NULL if not found.
   */
  public function getTool(string $toolId): ?array;

  /**
   * Gets tool configuration schema for a specific tool.
   *
   * Used to build configuration forms in the FlowDrop UI.
   *
   * @param string $toolId
   *   The tool plugin ID.
   *
   * @return array
   *   Configuration schema array.
   */
  public function getToolConfigSchema(string $toolId): array;

  /**
   * Validates tool configuration.
   *
   * @param string $toolId
   *   The tool plugin ID.
   * @param array $configuration
   *   The configuration to validate.
   *
   * @return array
   *   Array of validation errors, empty if valid.
   */
  public function validateToolConfig(string $toolId, array $configuration): array;

  /**
   * Gets available AI Agents that can be used in orchestration.
   *
   * These are agents that can be called by orchestration/triage agents.
   *
   * @param string $viewMode
   *   The view mode: 'teaser' or 'full'.
   *
   * @return array
   *   Array of agent information.
   */
  public function getAvailableAgents(string $viewMode = 'teaser'): array;

  /**
   * Gets the JSON schema for agent configuration.
   *
   * This can be used by LLMs to generate valid agent configurations.
   *
   * @return array
   *   JSON Schema array.
   */
  public function getAgentSchema(): array;

  /**
   * Searches tools by keyword.
   *
   * @param string $query
   *   Search query string.
   *
   * @return array
   *   Array of matching tools.
   */
  public function searchTools(string $query): array;

}
