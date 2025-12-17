<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Controller;

use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\Core\Controller\ControllerBase;
use Drupal\flowdrop_ui_agents\Service\AgentWorkflowMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for editing AI Assistants with FlowDrop.
 *
 * This controller loads the AI Assistant's linked AI Agent and renders
 * the FlowDrop editor for it, while also providing access to Assistant-specific
 * settings.
 *
 * Assistants can only have:
 * - Other AI Agents as tools (sub-agents)
 * - RAG search (if ai_search module is enabled)
 */
class AssistantEditorController extends ControllerBase {

  /**
   * The agent workflow mapper service.
   */
  protected AgentWorkflowMapper $agentWorkflowMapper;

  /**
   * Constructs the controller.
   */
  public function __construct(AgentWorkflowMapper $agentWorkflowMapper) {
    $this->agentWorkflowMapper = $agentWorkflowMapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_ui_agents.agent_workflow_mapper'),
    );
  }

  /**
   * Renders the FlowDrop editor for an AI Assistant.
   *
   * @param \Drupal\ai_assistant_api\Entity\AiAssistant $ai_assistant
   *   The AI Assistant entity.
   *
   * @return array
   *   The render array for the editor.
   */
  public function edit(AiAssistant $ai_assistant): array {
    // Get the linked AI Agent ID.
    $agentId = $ai_assistant->get('ai_agent');
    if (!$agentId) {
      throw new NotFoundHttpException('This AI Assistant does not have a linked AI Agent.');
    }

    // Load the AI Agent.
    $agent = $this->entityTypeManager()->getStorage('ai_agent')->load($agentId);
    if (!$agent) {
      throw new NotFoundHttpException('The linked AI Agent could not be found.');
    }

    // Convert the agent to workflow format.
    $workflowData = $this->agentWorkflowMapper->agentToWorkflow($agent);

    // Add assistant-specific metadata to the workflow.
    $assistantConfig = $this->getAssistantConfig($ai_assistant);
    $workflowData['metadata']['assistantConfig'] = $assistantConfig;

    // Convert the main agent node to an assistant node with different visuals.
    $workflowData = $this->convertMainNodeToAssistant($workflowData, $ai_assistant, $assistantConfig);

    // Get available agents (excluding self) for the sidebar.
    $availableAgents = $this->getAvailableAgents($ai_assistant->id());

    // Get RAG tools if ai_search is enabled.
    $availableRagTools = $this->getAvailableRagTools($agent);

    // Build the editor render array.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'flowdrop-agents-editor',
        'class' => ['flowdrop-agents-editor-container'],
        'style' => 'height: calc(100vh - 240px); min-height: 600px; width: 100%; border: 1px solid #efefef;',
        'data-workflow-id' => $agent->id(),
        'data-assistant-id' => $ai_assistant->id(),
        'data-is-new' => 'false',
        'data-read-only' => 'false',
      ],
      '#attached' => [
        'library' => [
          'flowdrop_ui_agents/editor',
        ],
        'drupalSettings' => [
          'flowdrop_agents' => [
            'workflowId' => $agent->id(),
            'assistantId' => $ai_assistant->id(),
            'isNew' => FALSE,
            'readOnly' => FALSE,
            'workflow' => $workflowData,
            // For assistants, only agents and RAG are available as tools.
            'availableTools' => $availableRagTools,
            'availableAgents' => $availableAgents,
            'toolsByCategory' => $this->groupToolsByCategory($availableAgents, $availableRagTools),
            'modelOwner' => 'ai_agents_agent',
            'modeler' => 'flowdrop_agents',
            'isAssistantMode' => TRUE,
          ],
          'modeler_api' => [
            // Use custom save endpoint for assistants.
            'save_url' => '/api/flowdrop-agents/assistant/' . $ai_assistant->id() . '/save',
            'token_url' => '/session/token',
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Gets assistant configuration for the workflow metadata.
   */
  protected function getAssistantConfig(AiAssistant $ai_assistant): array {
    return [
      'id' => $ai_assistant->id(),
      'label' => $ai_assistant->label(),
      'description' => $ai_assistant->get('description') ?? '',
      'instructions' => $ai_assistant->get('instructions') ?? '',
      'system_prompt' => $ai_assistant->get('system_prompt') ?? '',
      'pre_action_prompt' => $ai_assistant->get('pre_action_prompt') ?? '',
      'allow_history' => $ai_assistant->get('allow_history') ?? 'session',
      'history_context_length' => $ai_assistant->get('history_context_length') ?? '2',
      'llm_provider' => $ai_assistant->get('llm_provider') ?? '',
      'llm_model' => $ai_assistant->get('llm_model') ?? '',
      'llm_configuration' => $ai_assistant->get('llm_configuration') ?? [],
      'error_message' => $ai_assistant->get('error_message') ?? '',
      'roles' => $ai_assistant->get('roles') ?? [],
      'use_function_calling' => $ai_assistant->get('use_function_calling') ?? FALSE,
    ];
  }

  /**
   * Gets available agents for the sidebar (excluding self).
   */
  protected function getAvailableAgents(?string $excludeId): array {
    $agents = [];
    $storage = $this->entityTypeManager()->getStorage('ai_agent');
    $allAgents = $storage->loadMultiple();

    foreach ($allAgents as $agent) {
      $agentId = $agent->id();

      // Exclude the assistant's own linked agent.
      if ($agentId === $excludeId) {
        continue;
      }

      $agents[] = [
        'id' => 'ai_agent_' . $agentId,
        'name' => $agent->label(),
        'type' => 'agent',
        'supportedTypes' => ['agent'],
        'description' => $agent->get('description') ?? '',
        'category' => 'agents',
        'icon' => 'mdi:robot',
        'color' => 'var(--color-ref-purple-500)',
        'version' => '1.0.0',
        'enabled' => TRUE,
        'tags' => ['agent'],
        'executor_plugin' => 'ai_agents::ai_agent::' . $agentId,
        'agent_id' => $agentId,
        'tool_id' => 'ai_agents::ai_agent::' . $agentId,
        'inputs' => [
          [
            'id' => 'trigger',
            'name' => 'Trigger',
            'type' => 'input',
            'dataType' => 'trigger',
            'required' => FALSE,
            'description' => 'Trigger input',
          ],
        ],
        'outputs' => [
          [
            'id' => 'response',
            'name' => 'Response',
            'type' => 'output',
            'dataType' => 'string',
            'required' => FALSE,
            'description' => 'Agent response',
          ],
        ],
        'config' => [],
        'configSchema' => [],
      ];
    }

    usort($agents, fn($a, $b) => strcmp($a['name'], $b['name']));

    return $agents;
  }

  /**
   * Gets available RAG tools if ai_search module is enabled.
   */
  protected function getAvailableRagTools($agent): array {
    $tools = [];

    if (!$this->moduleHandler()->moduleExists('ai_search')) {
      return $tools;
    }

    // Get current RAG settings from agent if configured.
    $toolUsageLimits = $agent->get('tool_usage_limits') ?? [];
    $ragSettings = $toolUsageLimits['ai_search:rag_search'] ?? [];

    // Get available search indexes for RAG.
    $indexes = $this->entityTypeManager()->getStorage('search_api_index')->loadMultiple();
    $indexOptions = [];
    foreach ($indexes as $index) {
      $indexOptions[$index->id()] = $index->label();
    }

    $tools[] = [
      'id' => 'ai_search:rag_search',
      'name' => 'RAG Search',
      'type' => 'tool',
      'supportedTypes' => ['tool'],
      'description' => 'Search knowledge base using RAG (Retrieval-Augmented Generation)',
      'category' => 'rag',
      'icon' => 'mdi:database-search',
      'color' => 'var(--color-ref-blue-500)',
      'version' => '1.0.0',
      'enabled' => TRUE,
      'tags' => ['rag', 'search'],
      'tool_id' => 'ai_search:rag_search',
      'inputs' => [
        [
          'id' => 'tool',
          'name' => 'Tool',
          'type' => 'input',
          'dataType' => 'tool',
          'required' => FALSE,
          'description' => 'Tool connection from agent',
        ],
      ],
      'outputs' => [
        [
          'id' => 'tool',
          'name' => 'Tool',
          'type' => 'output',
          'dataType' => 'tool',
          'required' => FALSE,
          'description' => 'Tool output',
        ],
      ],
      'config' => [
        'index' => $ragSettings['index']['values'][0] ?? '',
        'amount' => $ragSettings['amount']['values'][0] ?? 5,
        'min_score' => $ragSettings['min_score']['values'][0] ?? 0.5,
      ],
      'configSchema' => [
        'type' => 'object',
        'properties' => [
          'index' => [
            'type' => 'string',
            'title' => 'RAG Database',
            'description' => 'Select which search index to use',
            'enum' => array_keys($indexOptions),
            'enumNames' => array_values($indexOptions),
          ],
          'amount' => [
            'type' => 'integer',
            'title' => 'Max Results',
            'description' => 'Maximum number of results to return',
            'default' => 5,
          ],
          'min_score' => [
            'type' => 'number',
            'title' => 'Threshold',
            'description' => 'Minimum similarity score (0-1)',
            'default' => 0.5,
            'minimum' => 0,
            'maximum' => 1,
          ],
        ],
      ],
      'indexOptions' => $indexOptions,
    ];

    return $tools;
  }

  /**
   * Converts the main agent node to an assistant node.
   *
   * This transforms the primary agent node in the workflow to have:
   * - Different visual (icon, color)
   * - Assistant-specific settings in the config panel
   */
  protected function convertMainNodeToAssistant(array $workflowData, AiAssistant $ai_assistant, array $assistantConfig): array {
    $agentId = $ai_assistant->get('ai_agent');
    $mainNodeId = 'agent_' . $agentId;

    foreach ($workflowData['nodes'] as &$node) {
      if ($node['id'] === $mainNodeId) {
        // Change node type to assistant.
        $node['data']['nodeType'] = 'assistant';
        $node['data']['label'] = $ai_assistant->label();

        // Update metadata for assistant visual.
        $node['data']['metadata']['id'] = 'ai_assistant';
        $node['data']['metadata']['name'] = 'AI Assistant';
        $node['data']['metadata']['type'] = 'assistant';
        $node['data']['metadata']['icon'] = 'mdi:account-voice';
        $node['data']['metadata']['color'] = 'var(--color-ref-teal-500)';
        $node['data']['metadata']['supportedTypes'] = ['assistant', 'agent', 'simple', 'default'];

        // Add assistant-specific config fields.
        $node['data']['config'] = array_merge($node['data']['config'], [
          'assistantId' => $ai_assistant->id(),
          // LLM settings.
          'llmProvider' => $assistantConfig['llm_provider'],
          'llmModel' => $assistantConfig['llm_model'],
          'llmConfiguration' => $assistantConfig['llm_configuration'],
          // History settings.
          'allowHistory' => $assistantConfig['allow_history'],
          'historyContextLength' => $assistantConfig['history_context_length'],
          // Other assistant settings.
          'instructions' => $assistantConfig['instructions'],
          'errorMessage' => $assistantConfig['error_message'],
          'roles' => $assistantConfig['roles'],
        ]);

        // Update config schema for assistant-specific fields.
        $node['data']['metadata']['configSchema'] = $this->getAssistantConfigSchema();

        break;
      }
    }

    return $workflowData;
  }

  /**
   * Returns JSON Schema for assistant node configuration.
   */
  protected function getAssistantConfigSchema(): array {
    // Get available LLM providers.
    $providers = $this->getAvailableLlmProviders();
    $providerOptions = array_keys($providers);
    $providerNames = array_values($providers);

    return [
      'type' => 'object',
      'properties' => [
        'label' => [
          'type' => 'string',
          'title' => 'Label',
          'description' => 'Human-readable name for the assistant',
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Description of what this assistant does',
        ],
        'instructions' => [
          'type' => 'string',
          'format' => 'textarea',
          'title' => 'Instructions',
          'description' => 'Additional instructions for the assistant',
        ],
        'systemPrompt' => [
          'type' => 'string',
          'format' => 'textarea',
          'title' => 'System Prompt',
          'description' => 'Core instructions for assistant behavior',
        ],
        'llmProvider' => [
          'type' => 'string',
          'title' => 'LLM Provider',
          'description' => 'AI provider to use (e.g., OpenAI, Anthropic)',
          'enum' => $providerOptions,
          'enumNames' => $providerNames,
        ],
        'llmModel' => [
          'type' => 'string',
          'title' => 'LLM Model',
          'description' => 'Specific model to use (e.g., gpt-4, claude-3)',
        ],
        'allowHistory' => [
          'type' => 'string',
          'title' => 'Conversation History',
          'description' => 'How to handle conversation history',
          'enum' => ['none', 'session', 'persistent'],
          'enumNames' => ['None', 'Session Only', 'Persistent'],
          'default' => 'session',
        ],
        'historyContextLength' => [
          'type' => 'string',
          'title' => 'History Length',
          'description' => 'Number of previous messages to include',
          'enum' => ['0', '2', '5', '10', '20', '50'],
          'enumNames' => ['0', '2', '5', '10', '20', '50'],
          'default' => '2',
        ],
        'errorMessage' => [
          'type' => 'string',
          'title' => 'Error Message',
          'description' => 'Message shown when an error occurs',
        ],
        'maxLoops' => [
          'type' => 'integer',
          'title' => 'Max Loops',
          'description' => 'Maximum iterations before stopping (1-100)',
          'default' => 3,
        ],
      ],
      'required' => ['label', 'description'],
    ];
  }

  /**
   * Gets available LLM providers.
   */
  protected function getAvailableLlmProviders(): array {
    $providers = [];

    try {
      // Try to get providers from ai_provider plugin manager.
      if ($this->moduleHandler()->moduleExists('ai')) {
        /** @var \Drupal\ai\AiProviderPluginManager $providerManager */
        $providerManager = \Drupal::service('ai.provider');
        $definitions = $providerManager->getDefinitions();
        foreach ($definitions as $id => $definition) {
          $providers[$id] = (string) ($definition['label'] ?? $id);
        }
      }
    }
    catch (\Exception $e) {
      // Fall back to common providers if service not available.
    }

    // Always include common providers as fallback.
    if (empty($providers)) {
      $providers = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'ollama' => 'Ollama',
      ];
    }

    return $providers;
  }

  /**
   * Groups tools by category for sidebar display.
   */
  protected function groupToolsByCategory(array $agents, array $ragTools): array {
    $grouped = [];

    if (!empty($agents)) {
      $grouped['agents'] = $agents;
    }

    if (!empty($ragTools)) {
      $grouped['rag'] = $ragTools;
    }

    return $grouped;
  }

  /**
   * Title callback for the edit page.
   */
  public function editTitle(AiAssistant $ai_assistant): string {
    return (string) $this->t('Edit @label with FlowDrop', ['@label' => $ai_assistant->label()]);
  }

}
