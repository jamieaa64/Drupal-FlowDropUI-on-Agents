<?php

namespace Drupal\flowdrop_modeler\Service;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;

/**
 * Orchestrator service for workflow operations.
 *
 * This service coordinates all workflow-related operations and provides
 * a single entry point for workflow processing.
 */
class WorkflowOrchestratorService {

  public function __construct(
    protected BpmnToWorkflowMapper $bpmnMapper,
    protected WorkflowLayoutService $layoutService,
    protected WorkflowDataTransformer $transformer,
    protected AvailableNodeTypesService $nodeTypesService,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {}

  /**
   * Processes a complete workflow from BPMN model to FlowDrop editor data.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner interface.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The BPMN model entity.
   * @param array $options
   *   Optional processing options.
   *
   * @return array
   *   The complete workflow data for the FlowDrop editor.
   */
  public function processWorkflow(ModelOwnerInterface $owner, ConfigEntityInterface $model, array $options = []): array {
    try {
      // Step 1: Map BPMN to workflow format.
      $workflowData = $this->bpmnMapper->mapToWorkflow(
        $model,
        $owner->label(),
        $options['description'] ?? 'Workflow generated from BPMN model'
      );

      // Step 2: Calculate node positions.
      $workflowData = $this->layoutService->calculateNodePositions(
        $workflowData,
        $options['spacing'] ?? []
      );

      // Step 3: Transform for FlowDrop editor.
      $workflowData = $this->transformer->transformForFlowDrop($workflowData);

      // Step 4: Get available node types.
      $availableNodeTypes = $this->nodeTypesService->getAvailableNodeTypes($owner);

      return [
        'workflow' => $workflowData,
        'availableNodeTypes' => $availableNodeTypes,
      ];

    }
    catch (\Exception $e) {
      $this->loggerChannelFactory->get('flowdrop_modeler')->error('Workflow processing failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
