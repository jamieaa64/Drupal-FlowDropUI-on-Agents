<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Orchestrator;

/**
 * Request to orchestrate a workflow.
 */
class OrchestrationRequest {

  public function __construct(
    private readonly string $workflowId,
    private readonly string $pipelineId,
    private readonly array $workflow,
    private readonly array $initialData,
    private readonly array $options,
  ) {}

  /**
   * Get the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId(): string {
    return $this->workflowId;
  }

  /**
   * Get the pipeline ID.
   *
   * @return string
   *   The pipeline ID.
   */
  public function getPipelineId(): string {
    return $this->pipelineId;
  }

  /**
   * Get the workflow definition.
   *
   * @return array
   *   The workflow definition.
   */
  public function getWorkflow(): array {
    return $this->workflow;
  }

  /**
   * Get the initial data.
   *
   * @return array
   *   The initial data.
   */
  public function getInitialData(): array {
    return $this->initialData;
  }

  /**
   * Get the orchestration options.
   *
   * @return array
   *   The orchestration options.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the request.
   */
  public function toArray(): array {
    return [
      'workflow_id' => $this->workflowId,
      'pipeline_id' => $this->pipelineId,
      'workflow' => $this->workflow,
      'initial_data' => $this->initialData,
      'options' => $this->options,
    ];
  }

}
