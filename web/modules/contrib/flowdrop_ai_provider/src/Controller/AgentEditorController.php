<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\flowdrop_ai_provider\Service\FlowDropAiEndpointConfigService;
use Drupal\flowdrop_ai_provider\Services\FlowDropAgentMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the FlowDrop AI Agent editor pages.
 *
 * Provides pages for creating and editing AI Agents using the FlowDrop UI.
 */
final class AgentEditorController extends ControllerBase {

  /**
   * The FlowDrop AI endpoint config service.
   *
   * @var \Drupal\flowdrop_ai_provider\Service\FlowDropAiEndpointConfigService
   */
  protected FlowDropAiEndpointConfigService $endpointConfigService;

  /**
   * The FlowDrop agent mapper service.
   *
   * @var \Drupal\flowdrop_ai_provider\Services\FlowDropAgentMapperInterface
   */
  protected FlowDropAgentMapperInterface $agentMapper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->endpointConfigService = $container->get('flowdrop_ai_provider.endpoint_config');
    $instance->agentMapper = $container->get('flowdrop_ai_provider.agent_mapper');
    return $instance;
  }

  /**
   * List page showing all AI Agents with option to edit in FlowDrop.
   *
   * @return array
   *   The render array for the list page.
   */
  public function listAgents(): array {
    $storage = $this->entityTypeManager()->getStorage('ai_agent');
    $agents = $storage->loadMultiple();

    $rows = [];
    foreach ($agents as $agent) {
      $edit_url = Url::fromRoute('flowdrop_ai_provider.agent.edit', [
        'agent_id' => $agent->id(),
      ]);
      $standard_edit_url = Url::fromRoute('entity.ai_agent.edit_form', [
        'ai_agent' => $agent->id(),
      ]);

      $rows[] = [
        'id' => $agent->id(),
        'label' => $agent->label(),
        'description' => $agent->get('description') ?? '',
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'flowdrop_edit' => [
                'title' => $this->t('Edit in FlowDrop'),
                'url' => $edit_url,
              ],
              'standard_edit' => [
                'title' => $this->t('Edit form'),
                'url' => $standard_edit_url,
              ],
            ],
          ],
        ],
      ];
    }

    $build['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Edit AI Agents using the FlowDrop visual editor. Changes are saved directly to AI Agent configuration entities.'),
    ];

    $build['create_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Create new AI Agent'),
      '#url' => Url::fromRoute('flowdrop_ai_provider.agent.create'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $build['agents_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Label'),
        $this->t('Description'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No AI Agents found. Create one to get started.'),
    ];

    return $build;
  }

  /**
   * Page for creating a new AI Agent with FlowDrop editor.
   *
   * @return array
   *   The render array for the create page.
   */
  public function createAgent(): array {
    // Create empty workflow data for new agent.
    $workflow_data = [
      'id' => 'new_agent_' . time(),
      'name' => 'New AI Agent',
      'description' => 'A new AI Agent created with FlowDrop',
      'nodes' => [],
      'edges' => [],
      'metadata' => [
        'version' => '1.0.0',
        'created' => date('c'),
        'changed' => date('c'),
        'isNew' => TRUE,
      ],
    ];

    return $this->buildEditorPage($workflow_data, $this->t('Create AI Agent'));
  }

  /**
   * Page for editing an existing AI Agent with FlowDrop editor.
   *
   * @param string $agent_id
   *   The AI Agent ID to edit.
   *
   * @return array
   *   The render array for the edit page.
   */
  public function editAgent(string $agent_id): array {
    $storage = $this->entityTypeManager()->getStorage('ai_agent');
    $agent = $storage->load($agent_id);

    if (!$agent) {
      throw new NotFoundHttpException('AI Agent not found');
    }

    // Convert agent to workflow format for FlowDrop.
    $workflow = $this->agentMapper->agentConfigsToWorkflow([$agent_id]);
    $workflow_data = $this->workflowToArray($workflow);

    // Add metadata.
    $workflow_data['metadata']['isNew'] = FALSE;
    $workflow_data['metadata']['agentId'] = $agent_id;

    return $this->buildEditorPage($workflow_data, $this->t('Edit: @label', ['@label' => $agent->label()]));
  }

  /**
   * Title callback for the edit page.
   *
   * @param string $agent_id
   *   The AI Agent ID.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function editAgentTitle(string $agent_id) {
    $storage = $this->entityTypeManager()->getStorage('ai_agent');
    $agent = $storage->load($agent_id);

    if ($agent) {
      return $this->t('Edit AI Agent: @label', ['@label' => $agent->label()]);
    }

    return $this->t('Edit AI Agent');
  }

  /**
   * Build the editor page render array.
   *
   * @param array $workflow_data
   *   The workflow data for the editor.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The page title.
   *
   * @return array
   *   The render array.
   */
  protected function buildEditorPage(array $workflow_data, $title): array {
    $url_options = [
      'absolute' => TRUE,
      'language' => $this->languageManager()->getCurrentLanguage(),
    ];
    $base_url = Url::fromRoute('<front>', [], $url_options)->toString() . '/api/flowdrop-ai';

    $build['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'flowdrop-ai-editor',
        'class' => ['flowdrop-editor-container', 'flowdrop-ai-editor-container'],
        'data-agent-id' => $workflow_data['metadata']['agentId'] ?? 'new',
      ],
      '#attached' => [
        'library' => [
          'flowdrop_ai_provider/editor',
        ],
        'drupalSettings' => [
          'flowdropAi' => [
            'apiBaseUrl' => $base_url,
            'endpointConfig' => $this->endpointConfigService->generateEndpointConfig($base_url),
            'workflow' => $workflow_data,
            'listUrl' => Url::fromRoute('flowdrop_ai_provider.agents.list')->toString(),
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Convert WorkflowDTO to array format for FlowDrop frontend.
   *
   * @param \Drupal\flowdrop_workflow\DTO\WorkflowDTO $workflow
   *   The workflow DTO.
   *
   * @return array
   *   The workflow as an array in FlowDrop frontend format.
   */
  protected function workflowToArray($workflow): array {
    // Get nodes in frontend format (using getRawData which has the correct structure).
    $nodes = [];
    foreach ($workflow->getNodes() as $node) {
      $nodes[] = $node->getRawData();
    }

    // Get edges in frontend format.
    $edges = [];
    foreach ($workflow->getEdges() as $edge) {
      $edges[] = [
        'id' => $edge->getId(),
        'source' => $edge->getSource(),
        'target' => $edge->getTarget(),
        'sourceHandle' => $edge->getSourceHandle(),
        'targetHandle' => $edge->getTargetHandle(),
        'isTrigger' => $edge->isTrigger(),
        'branchName' => $edge->getBranchName(),
      ];
    }

    return [
      'id' => $workflow->getId(),
      'name' => $workflow->getName(),
      'label' => $workflow->getName(),
      'description' => $workflow->getDescription(),
      'nodes' => $nodes,
      'edges' => $edges,
      'metadata' => $workflow->getMetadata(),
    ];
  }

}
