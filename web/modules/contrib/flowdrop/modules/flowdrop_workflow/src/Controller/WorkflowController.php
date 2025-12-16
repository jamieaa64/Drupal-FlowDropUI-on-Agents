<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\Controller;

use Drupal\flowdrop_workflow\Entity\FlowDropWorkflow;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for flowdrop routes.
 */
final class WorkflowController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The FlowDrop endpoint config service.
   *
   * @var \Drupal\flowdrop_ui\Service\FlowDropEndpointConfigService
   */
  protected $endpointConfigService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->endpointConfigService = $container->get('flowdrop_ui.endpoint_config');
    return $instance;
  }

  /**
   * Opens a specific workflow entity in the FlowDrop editor.
   *
   * @param \Drupal\flowdrop_workflow\Entity\FlowDropWorkflow $flowdrop_workflow
   *   The flowdrop workflow entity to open.
   *
   * @return array
   *   The render array for the editor page with workflow data.
   */
  public function openWorkflowEntity(FlowDropWorkflow $flowdrop_workflow): array {
    // Prepare workflow data for the frontend.
    $workflow_data = [
      'id' => $flowdrop_workflow->id(),
      'label' => $flowdrop_workflow->label(),
      'description' => $flowdrop_workflow->getDescription(),
      'nodes' => $flowdrop_workflow->getNodes(),
      'edges' => $flowdrop_workflow->getEdges(),
      'metadata' => $flowdrop_workflow->getMetadata(),
      'created' => $flowdrop_workflow->getCreated(),
      'changed' => $flowdrop_workflow->getChanged(),
    ];
    $url_options = [
      'absolute' => TRUE,
      'language' => $this->languageManager()->getCurrentLanguage(),
    ];
    $base_url = Url::fromRoute('<front>', [], $url_options)->toString() . '/api/flowdrop';
    $build['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'flowdrop-editor',
        'class' => ['flowdrop-editor-container'],
        'data-workflow-id' => $flowdrop_workflow->id(),
      ],
      '#attached' => [
        'library' => [
          'flowdrop_workflow/editor',
        ],
        'drupalSettings' => [
          'flowdrop' => [
            'apiBaseUrl' => $base_url,
            'endpointConfig' => $this->endpointConfigService->generateEndpointConfig($base_url),
            'workflow' => $workflow_data,
          ],
        ],
      ],
    ];

    return $build;
  }

}
