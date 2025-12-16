<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Services;

use Drupal\ai_agents\Entity\AiAgent;
use Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModel;

/**
 * Interface for AI Agent entity CRUD operations.
 *
 * This service handles creating, updating, and loading AI Agent
 * entities from FlowDrop model data.
 *
 * @see \Drupal\ai_integration_eca_agents\Services\EcaRepository\EcaRepository
 */
interface AgentRepositoryInterface {

  /**
   * Creates or updates an AI Agent from model data.
   *
   * @param array $data
   *   The model data array.
   * @param bool $save
   *   Whether to save the entity. Defaults to TRUE.
   * @param string|null $id
   *   Optional existing agent ID for updates.
   *
   * @return \Drupal\ai_agents\Entity\AiAgent
   *   The created or updated AI Agent entity.
   *
   * @throws \Drupal\flowdrop_ai_provider\Exception\ValidationException
   *   If the data fails validation.
   * @throws \Drupal\flowdrop_ai_provider\Exception\EntityException
   *   If the entity cannot be created or saved.
   */
  public function build(array $data, bool $save = TRUE, ?string $id = NULL): AiAgent;

  /**
   * Creates or updates multiple AI Agents from workflow data.
   *
   * @param array $agentConfigs
   *   Array of agent config arrays, keyed by agent ID.
   * @param bool $save
   *   Whether to save the entities. Defaults to TRUE.
   *
   * @return array
   *   Array of AI Agent entities, keyed by agent ID.
   *
   * @throws \Drupal\flowdrop_ai_provider\Exception\ValidationException
   *   If any agent config fails validation.
   */
  public function buildMultiple(array $agentConfigs, bool $save = TRUE): array;

  /**
   * Loads an AI Agent by ID.
   *
   * @param string $id
   *   The AI Agent ID.
   *
   * @return \Drupal\ai_agents\Entity\AiAgent|null
   *   The AI Agent entity, or NULL if not found.
   */
  public function load(string $id): ?AiAgent;

  /**
   * Loads multiple AI Agents by IDs.
   *
   * @param array $ids
   *   Array of AI Agent IDs.
   *
   * @return array
   *   Array of AI Agent entities, keyed by ID.
   */
  public function loadMultiple(array $ids): array;

  /**
   * Gets all available AI Agents.
   *
   * @param array $filters
   *   Optional filters:
   *   - 'type': 'orchestration', 'triage', or 'worker'
   *   - 'has_tools': boolean
   *   - 'created_by_flowdrop': boolean
   *
   * @return array
   *   Array of AI Agent entities.
   */
  public function getAll(array $filters = []): array;

  /**
   * Deletes an AI Agent.
   *
   * @param string $id
   *   The AI Agent ID.
   *
   * @return bool
   *   TRUE if deleted, FALSE if not found.
   */
  public function delete(string $id): bool;

  /**
   * Checks if an AI Agent exists.
   *
   * @param string $id
   *   The AI Agent ID.
   *
   * @return bool
   *   TRUE if exists, FALSE otherwise.
   */
  public function exists(string $id): bool;

  /**
   * Validates agent data without saving.
   *
   * @param array $data
   *   The model data array.
   *
   * @return array
   *   Array of validation errors, empty if valid.
   */
  public function validate(array $data): array;

  /**
   * Gets agents that were created by FlowDrop.
   *
   * Identified by presence of flowdrop_ai_provider third-party settings.
   *
   * @return array
   *   Array of AI Agent entities created by FlowDrop.
   */
  public function getFlowDropAgents(): array;

}
