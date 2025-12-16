<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Services;

use Drupal\ai_agents\Entity\AiAgent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\flowdrop_ai_provider\Exception\ValidationException;

/**
 * Repository for AI Agent entity CRUD operations.
 *
 * This service handles creating, updating, and loading AI Agent
 * entities from FlowDrop model data.
 *
 * @see \Drupal\ai_integration_eca_agents\Services\EcaRepository\EcaRepository
 */
class AgentRepository implements AgentRepositoryInterface {

  /**
   * The third-party settings key for FlowDrop metadata.
   */
  const THIRD_PARTY_KEY = 'flowdrop_ai_provider';

  /**
   * Constructs an AgentRepository.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TypedDataManagerInterface $typedDataManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(array $data, bool $save = TRUE, ?string $id = NULL): AiAgent {
    // Validate the data first.
    $errors = $this->validate($data);
    if (!empty($errors)) {
      throw ValidationException::fromMessages($errors);
    }

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('ai_agent');

    // Load existing or create new entity.
    if ($id) {
      /** @var \Drupal\ai_agents\Entity\AiAgent|null $agent */
      $agent = $storage->load($id);
      if (!$agent) {
        // Create new with specified ID.
        /** @var \Drupal\ai_agents\Entity\AiAgent $agent */
        $agent = $storage->create(['id' => $id]);
      }
    }
    else {
      // Create new entity.
      /** @var \Drupal\ai_agents\Entity\AiAgent $agent */
      $agent = $storage->create();
    }

    // Map data to entity.
    $this->mapDataToEntity($data, $agent);

    // Validate the entity.
    $definition = $this->typedDataManager->createDataDefinition('entity:ai_agent');
    $violations = $this->typedDataManager->create($definition, $agent)->validate();
    if ($violations->count()) {
      throw ValidationException::fromViolations($violations);
    }

    // Save if requested.
    if ($save) {
      $agent->save();
    }

    return $agent;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $agentConfigs, bool $save = TRUE): array {
    $agents = [];

    foreach ($agentConfigs as $agentId => $config) {
      $agents[$agentId] = $this->build($config, $save, $agentId);
    }

    return $agents;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): ?AiAgent {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $agent = $storage->load($id);
    return $agent instanceof AiAgent ? $agent : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $agents = $storage->loadMultiple($ids);

    // Filter to only AiAgent instances.
    return array_filter($agents, fn($agent) => $agent instanceof AiAgent);
  }

  /**
   * {@inheritdoc}
   */
  public function getAll(array $filters = []): array {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $agents = $storage->loadMultiple();

    // Apply filters.
    if (!empty($filters)) {
      $agents = array_filter($agents, function (AiAgent $agent) use ($filters) {
        return $this->matchesFilters($agent, $filters);
      });
    }

    return $agents;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $id): bool {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    $agent = $storage->load($id);

    if (!$agent) {
      return FALSE;
    }

    $agent->delete();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(string $id): bool {
    $storage = $this->entityTypeManager->getStorage('ai_agent');
    return $storage->load($id) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $data): array {
    $errors = [];

    // Check required fields.
    if (empty($data['id']) && empty($data['model_id'])) {
      $errors[] = 'Agent ID is required';
    }

    if (empty($data['label'])) {
      $errors[] = 'Agent label is required';
    }

    if (empty($data['description'])) {
      $errors[] = 'Agent description is required';
    }

    if (empty($data['system_prompt'])) {
      $errors[] = 'System prompt is required';
    }

    // Validate ID format.
    $id = $data['id'] ?? $data['model_id'] ?? '';
    if ($id && !preg_match('/^[a-z0-9_]+$/', $id)) {
      $errors[] = 'Agent ID must contain only lowercase letters, numbers, and underscores';
    }

    // Validate max_loops.
    if (isset($data['max_loops']) && ($data['max_loops'] < 1 || $data['max_loops'] > 100)) {
      $errors[] = 'Max loops must be between 1 and 100';
    }

    // Validate tools format.
    if (isset($data['tools']) && !is_array($data['tools'])) {
      $errors[] = 'Tools must be an array';
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlowDropAgents(): array {
    $allAgents = $this->getAll();

    return array_filter($allAgents, function (AiAgent $agent) {
      // Check if agent has FlowDrop third-party settings.
      $settings = $agent->getThirdPartySettings(self::THIRD_PARTY_KEY);
      return !empty($settings);
    });
  }

  /**
   * Maps data array to AI Agent entity.
   *
   * @param array $data
   *   The data array.
   * @param \Drupal\ai_agents\Entity\AiAgent $agent
   *   The agent entity to populate.
   */
  protected function mapDataToEntity(array $data, AiAgent $agent): void {
    // Set ID.
    $id = $data['id'] ?? $data['model_id'] ?? NULL;
    if ($id && $agent->isNew()) {
      $agent->set('id', $id);
    }

    // Set label.
    if (isset($data['label'])) {
      $agent->set('label', $data['label']);
    }

    // Set description.
    if (isset($data['description'])) {
      $agent->set('description', $data['description']);
    }

    // Set system prompt.
    if (isset($data['system_prompt'])) {
      $agent->set('system_prompt', $data['system_prompt']);
    }

    // Set secured system prompt.
    if (isset($data['secured_system_prompt'])) {
      $agent->set('secured_system_prompt', $data['secured_system_prompt']);
    }

    // Set tools.
    if (isset($data['tools'])) {
      $agent->set('tools', $data['tools']);
    }

    // Set tool settings.
    if (isset($data['tool_settings'])) {
      $agent->set('tool_settings', $data['tool_settings']);
    }

    // Set tool usage limits.
    if (isset($data['tool_usage_limits'])) {
      $agent->set('tool_usage_limits', $data['tool_usage_limits']);
    }

    // Set default information tools.
    if (isset($data['default_information_tools'])) {
      $agent->set('default_information_tools', $data['default_information_tools']);
    }

    // Set orchestration agent flag.
    if (isset($data['orchestration_agent'])) {
      $agent->set('orchestration_agent', (bool) $data['orchestration_agent']);
    }

    // Set triage agent flag.
    if (isset($data['triage_agent'])) {
      $agent->set('triage_agent', (bool) $data['triage_agent']);
    }

    // Set max loops.
    if (isset($data['max_loops'])) {
      $agent->set('max_loops', (int) $data['max_loops']);
    }

    // Set masquerade roles.
    if (isset($data['masquerade_roles'])) {
      $agent->set('masquerade_roles', $data['masquerade_roles']);
    }

    // Set exclude users role.
    if (isset($data['exclude_users_role'])) {
      $agent->set('exclude_users_role', (bool) $data['exclude_users_role']);
    }

    // Set structured output settings.
    if (isset($data['structured_output_enabled'])) {
      $agent->set('structured_output_enabled', (bool) $data['structured_output_enabled']);
    }

    if (isset($data['structured_output_schema'])) {
      $agent->set('structured_output_schema', $data['structured_output_schema']);
    }

    // Set FlowDrop third-party settings to mark as created by FlowDrop.
    $agent->setThirdPartySetting(self::THIRD_PARTY_KEY, 'created', TRUE);
    $agent->setThirdPartySetting(self::THIRD_PARTY_KEY, 'updated', time());

    // Store UI positions if provided.
    if (isset($data['ui_metadata']['positions'])) {
      $agent->setThirdPartySetting(self::THIRD_PARTY_KEY, 'positions', $data['ui_metadata']['positions']);
    }
  }

  /**
   * Checks if an agent matches the given filters.
   *
   * @param \Drupal\ai_agents\Entity\AiAgent $agent
   *   The agent to check.
   * @param array $filters
   *   The filters to apply.
   *
   * @return bool
   *   TRUE if the agent matches all filters.
   */
  protected function matchesFilters(AiAgent $agent, array $filters): bool {
    // Filter by type.
    if (isset($filters['type'])) {
      $isOrchestration = $agent->get('orchestration_agent');
      $isTriage = $agent->get('triage_agent');

      switch ($filters['type']) {
        case 'orchestration':
          if (!$isOrchestration) {
            return FALSE;
          }
          break;

        case 'triage':
          if (!$isTriage) {
            return FALSE;
          }
          break;

        case 'worker':
          if ($isOrchestration || $isTriage) {
            return FALSE;
          }
          break;
      }
    }

    // Filter by has_tools.
    if (isset($filters['has_tools'])) {
      $tools = $agent->get('tools') ?? [];
      $hasTools = !empty(array_filter($tools));
      if ($filters['has_tools'] !== $hasTools) {
        return FALSE;
      }
    }

    // Filter by created_by_flowdrop.
    if (isset($filters['created_by_flowdrop'])) {
      $createdByFlowDrop = !empty($agent->getThirdPartySettings(self::THIRD_PARTY_KEY));
      if ($filters['created_by_flowdrop'] !== $createdByFlowDrop) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
