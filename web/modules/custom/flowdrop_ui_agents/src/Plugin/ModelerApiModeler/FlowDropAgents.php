<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Plugin\ModelerApiModeler;

use Drupal\Component\Utility\Random;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\flowdrop_ui_agents\Service\AgentWorkflowMapper;
use Drupal\flowdrop_ui_agents\Service\WorkflowParser;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;

/**
 * FlowDrop modeler plugin specifically for AI Agents.
 *
 * This modeler provides a FlowDrop-based visual editor for AI Agents,
 * converting between AI Agent config entities and FlowDrop workflow format.
 */
#[Modeler(
  id: "flowdrop_agents",
  label: new TranslatableMarkup("FlowDrop for AI Agents"),
  description: new TranslatableMarkup("Visual editor for AI Agents using FlowDrop UI.")
)]
class FlowDropAgents extends ModelerBase {

  /**
   * The agent workflow mapper service.
   */
  protected AgentWorkflowMapper $agentWorkflowMapper;

  /**
   * The workflow parser service.
   */
  protected WorkflowParser $workflowParser;

  /**
   * Parsed workflow data from raw JSON.
   */
  protected array $parsedData = [];

  /**
   * Get the agent workflow mapper service.
   */
  protected function agentWorkflowMapper(): AgentWorkflowMapper {
    if (!isset($this->agentWorkflowMapper)) {
      $this->agentWorkflowMapper = $this->getContainer()->get('flowdrop_ui_agents.agent_workflow_mapper');
    }
    return $this->agentWorkflowMapper;
  }

  /**
   * Get the workflow parser service.
   */
  protected function workflowParser(): WorkflowParser {
    if (!isset($this->workflowParser)) {
      $this->workflowParser = $this->getContainer()->get('flowdrop_ui_agents.workflow_parser');
    }
    return $this->workflowParser;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawFileExtension(): ?string {
    return 'json';
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    // Parse existing data if available.
    $workflow = [];
    if (!empty($data)) {
      $workflow = json_decode($data, TRUE) ?? [];
    }

    // Get available tools for the sidebar.
    $availableTools = $this->agentWorkflowMapper()->getAvailableTools($owner);

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'flowdrop-agents-editor',
        'class' => ['flowdrop-agents-editor-container'],
        'style' => 'height: calc(100vh - 240px); min-height: 600px; width: 100%; border: 1px solid #efefef;',
        'data-workflow-id' => $id,
        'data-is-new' => $isNew ? 'true' : 'false',
        'data-read-only' => $readOnly ? 'true' : 'false',
      ],
      '#attached' => [
        'library' => [
          'flowdrop_ui_agents/editor',
        ],
        'drupalSettings' => [
          'flowdrop_agents' => [
            'workflowId' => $id,
            'isNew' => $isNew,
            'readOnly' => $readOnly,
            'workflow' => $workflow,
            'availableTools' => $availableTools,
            'modelOwner' => $owner->getPluginId(),
            'modeler' => 'flowdrop_agents',
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array {
    // Convert AI Agent entity to FlowDrop workflow format.
    $workflowData = $this->agentWorkflowMapper()->agentToWorkflow($model);

    // Get available tools for sidebar.
    $availableTools = $this->agentWorkflowMapper()->getAvailableTools($owner);

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'flowdrop-agents-editor',
        'class' => ['flowdrop-agents-editor-container'],
        'style' => 'height: calc(100vh - 240px); min-height: 600px; width: 100%; border: 1px solid #efefef;',
        'data-workflow-id' => $model->id(),
        'data-is-new' => 'false',
        'data-read-only' => $readOnly ? 'true' : 'false',
      ],
      '#attached' => [
        'library' => [
          'flowdrop_ui_agents/editor',
        ],
        'drupalSettings' => [
          'flowdrop_agents' => [
            'workflowId' => $model->id(),
            'isNew' => FALSE,
            'readOnly' => $readOnly,
            'workflow' => $workflowData,
            'availableTools' => $availableTools,
            'modelOwner' => $owner->getPluginId(),
            'modeler' => 'flowdrop_agents',
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function generateId(): string {
    $random = new Random();
    return 'agent_' . strtolower($random->name(8));
  }

  /**
   * {@inheritdoc}
   */
  public function enable(ModelOwnerInterface $owner): ModelerInterface {
    // Nothing special needed for enable.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(ModelOwnerInterface $owner): ModelerInterface {
    // Nothing special needed for disable.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): ModelerInterface {
    // Update the parsed data with new ID and label.
    if (!empty($this->parsedData)) {
      $this->parsedData['id'] = $id;
      $this->parsedData['label'] = $label;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEmptyModelData(string &$id): string {
    $id = $this->generateId();

    // Return empty workflow structure.
    $emptyWorkflow = [
      'id' => $id,
      'label' => 'New Agent',
      'nodes' => [],
      'edges' => [],
      'metadata' => [
        'agentConfig' => [
          'system_prompt' => '',
          'description' => '',
          'max_loops' => 3,
        ],
      ],
    ];

    return json_encode($emptyWorkflow);
  }

  /**
   * The model owner for component creation.
   */
  protected ?ModelOwnerInterface $modelOwner = NULL;

  /**
   * {@inheritdoc}
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void {
    $this->modelOwner = $owner;
    $this->parsedData = $this->workflowParser()->parse($data);
    // Set the owner on the parser for component creation.
    $this->workflowParser()->setOwner($owner);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->parsedData['id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->parsedData['label'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->parsedData['metadata']['tags'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return $this->parsedData['metadata']['changelog'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(): string {
    return $this->parsedData['metadata']['storage'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return $this->parsedData['metadata']['documentation'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->parsedData['metadata']['status'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->parsedData['metadata']['version'] ?? '1.0.0';
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData(): string {
    return json_encode($this->parsedData);
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(): array {
    return $this->workflowParser()->toComponents($this->parsedData);
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponents(ModelOwnerInterface $owner): bool {
    // No update needed for now.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(ModelOwnerInterface $owner): AjaxResponse {
    $response = new AjaxResponse();
    // TODO: Implement config form for node configuration.
    // This should open a Drupal form in off-canvas for configuring
    // agent/tool properties.
    return $response;
  }

}
