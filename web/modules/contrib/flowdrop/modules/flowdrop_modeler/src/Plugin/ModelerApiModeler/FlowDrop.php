<?php

namespace Drupal\flowdrop_modeler\Plugin\ModelerApiModeler;

use Drupal\bpmn_io\Service\Parser;
use Drupal\bpmn_io\Service\PrepareComponents;
use Drupal\Component\Utility\Random;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\bpmn_io\Form\Modeler as ModelerForm;
use Drupal\Core\Url;
use Drupal\flowdrop_modeler\Service\WorkflowOrchestratorService;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;

/**
 * Plugin implementation of the Modeler API.
 */
#[Modeler(
  id: "flowdrop_modeler",
  label: new TranslatableMarkup("FlowDrop Modeler"),
  description: new TranslatableMarkup("FlowDrop modeler with a feature-rich UI.")
)]
class FlowDrop extends ModelerBase {
  public const SUPPORTED_COMPONENT_TYPES = [
    Api::COMPONENT_TYPE_START => 'flowdrop:default',
    Api::COMPONENT_TYPE_LINK => 'flowdrop:default',
    Api::COMPONENT_TYPE_ELEMENT => 'flowdrop:default',
    Api::COMPONENT_TYPE_GATEWAY => 'flowdrop:default',
    Api::COMPONENT_TYPE_SUBPROCESS => 'flowdrop:default',
    Api::COMPONENT_TYPE_SWIMLANE => 'flowdrop:default',
    Api::COMPONENT_TYPE_ANNOTATION => 'flowdrop:default',
  ];

  /**
   * The prepare components service.
   *
   * @var \Drupal\bpmn_io\Service\PrepareComponents
   */
  protected PrepareComponents $prepareComponents;

  /**
   * The BPMN parser.
   *
   * @var \Drupal\bpmn_io\Service\Parser
   */
  protected Parser $parser;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The workflow orchestrator service.
   *
   * @var \Drupal\flowdrop_modeler\Service\WorkflowOrchestratorService
   */
  protected WorkflowOrchestratorService $workflowOrchestrator;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Get the prepare components service.
   *
   * @return \Drupal\bpmn_io\Service\PrepareComponents
   *   The prepare components service.
   */
  protected function prepareComponents(): PrepareComponents {
    if (!isset($this->prepareComponents)) {
      $this->prepareComponents = $this->getContainer()->get('bpmn_io.prepare_components');
    }
    return $this->prepareComponents;
  }

  /**
   * Get the BPMN parser.
   *
   * @return \Drupal\bpmn_io\Service\Parser
   *   The BPMN parser.
   */
  protected function parser(): Parser {
    if (!isset($this->parser)) {
      $this->parser = $this->getContainer()->get('bpmn_io.parser');
    }
    return $this->parser;
  }

  /**
   * Get Logger Channel.
   */
  protected function logger(): LoggerChannelInterface {
    if (!isset($this->logger)) {
      $this->logger = $this->getContainer()->get('logger.factory')->get('flowdrop_modeler');
    }
    return $this->logger;
  }

  /**
   * Get the workflow orchestrator service.
   */
  protected function workflowOrchestrator(): WorkflowOrchestratorService {
    if (!isset($this->workflowOrchestrator)) {
      $this->workflowOrchestrator = $this->getContainer()->get('flowdrop_modeler.workflow_orchestrator');
    }
    return $this->workflowOrchestrator;
  }

  /**
   * Get the language manager service.
   */
  protected function languageManager(): LanguageManagerInterface {
    if (!isset($this->languageManager)) {
      $this->languageManager = $this->getContainer()->get('language_manager');
    }
    return $this->languageManager;
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
   * Returns a render array with everything required for model editing.
   *
   * @return array
   *   The render array.
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    $form = $this->formBuilder->getForm(ModelerForm::class, $owner, $id, $readOnly);
    return $form;
  }

  /**
   * Returns a render array with everything required for model editing.
   *
   * @return array
   *   The render array.
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array {
    return $this->convertToFlowdropWorkflow($owner, $model);
  }

  /**
   * Convert to FlowDrop Workflow.
   */
  public function convertToFlowdropWorkflow(ModelOwnerInterface $owner, ConfigEntityInterface $model) {
    // Get the workflow orchestrator service.
    $orchestrator = $this->workflowOrchestrator();

    // Process the complete workflow.
    $workflowResult = $orchestrator->processWorkflow($owner, $model, [
      'description' => 'Workflow generated from BPMN model',
      'spacing' => [
        'horizontal' => 500,
        'vertical' => 750,
      ],
    ]);

    $workflow_data = $workflowResult['workflow'];
    $available_node_types = $workflowResult['availableNodeTypes'];

    $url_options = [
      'absolute' => TRUE,
      'language' => $this->languageManager()->getCurrentLanguage(),
    ];
    $base_url = Url::fromRoute('<front>', [], $url_options)->toString() . '/api/flowdrop';
    $build['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'flowdrop-editor',
        'style' => 'height: calc(100vh - 240px); min-height: 600px; width: 100%; min-width: 800px; border: 1px solid #efefef; box-shadow: none;',
        'class' => ['flowdrop-editor-container'],
        'data-workflow-id' => $model->id(),
      ],
      '#attached' => [
        'library' => [
          'flowdrop_modeler/modeler-editor',
        ],
        'drupalSettings' => [
          'flowdrop' => [
            'apiBaseUrl' => $base_url,
            'endpointConfig' => $this->container->get('flowdrop_ui.endpoint_config')->generateEndpointConfig($base_url),
            'workflow' => $workflow_data,
            'availableNodeTypes' => array_values($available_node_types),
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
    return 'FlowDrop_' . $random->name(7);
  }

  /**
   * {@inheritdoc}
   */
  public function enable(ModelOwnerInterface $owner): ModelerInterface {
    $this->parser()->enable($owner);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(ModelOwnerInterface $owner): ModelerInterface {
    $this->parser()->disable($owner);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): ModelerInterface {
    $this->parser()->clone($owner, $id, $label);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEmptyModelData(string &$id): string {
    $id = $this->generateId();
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->parser()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->parser()->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->parser()->getTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return $this->parser()->getChangelog();
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return $this->parser()->getDocumentation();
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->parser()->getStatus();
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->parser()->getVersion();
  }

  /**
   * {@inheritdoc}
   */
  final public function getRawData(): string {
    return $this->parser()->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void {
    $this->parser()->setData($owner, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(): array {
    return $this->parser()->getComponents();
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponents(ModelOwnerInterface $owner): bool {
    $changed = $this->parser()->updateComponents($owner, $this->prepareComponents()->getTemplates($owner));
    $this->parseData($owner, $this->parser()->getData());
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(ModelOwnerInterface $owner): AjaxResponse {
    $response = new AjaxResponse();
    return $response;
  }

}
