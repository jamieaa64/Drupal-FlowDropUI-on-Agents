<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Orchestrator;

/**
 * Response from workflow orchestration.
 */
class OrchestrationResponse {

  public function __construct(
    private readonly string $executionId,
    private readonly string $status,
    private readonly array $results,
    private readonly float $executionTime,
    private readonly array $metadata,
  ) {}

  /**
   * Get the execution ID.
   *
   * @return string
   *   The execution ID.
   */
  public function getExecutionId(): string {
    return $this->executionId;
  }

  /**
   * Get the execution status.
   *
   * @return string
   *   The execution status.
   */
  public function getStatus(): string {
    return $this->status;
  }

  /**
   * Get the execution results.
   *
   * @return array
   *   The execution results.
   */
  public function getResults(): array {
    return $this->results;
  }

  /**
   * Get the total execution time.
   *
   * @return float
   *   The execution time in seconds.
   */
  public function getExecutionTime(): float {
    return $this->executionTime;
  }

  /**
   * Get the execution metadata.
   *
   * @return array
   *   The execution metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the response.
   */
  public function toArray(): array {
    return [
      'execution_id' => $this->executionId,
      'status' => $this->status,
      'results' => $this->results,
      'execution_time' => $this->executionTime,
      'metadata' => $this->metadata,
    ];
  }

}
