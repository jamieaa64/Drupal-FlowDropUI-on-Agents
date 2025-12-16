<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Orchestrator;

use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationRequest;
use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationResponse;

/**
 * Interface for workflow orchestrators.
 */
interface OrchestratorInterface {

  /**
   * Get the orchestrator type.
   */
  public function getType(): string;

  /**
   * Execute a workflow using this orchestrator.
   */
  public function orchestrate(OrchestrationRequest $request): OrchestrationResponse;

  /**
   * Check if this orchestrator supports the given workflow.
   */
  public function supportsWorkflow(array $workflow): bool;

  /**
   * Get orchestrator capabilities.
   */
  public function getCapabilities(): array;

}
