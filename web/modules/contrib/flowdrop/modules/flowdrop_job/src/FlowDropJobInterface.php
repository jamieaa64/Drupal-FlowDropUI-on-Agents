<?php

declare(strict_types=1);

namespace Drupal\flowdrop_job;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a flowdrop job entity type.
 */
interface FlowDropJobInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Get the job metadata.
   *
   * @return array
   *   The metadata as an associative array.
   */
  public function getMetadata(): array;

  /**
   * Set the job metadata.
   *
   * @param array $metadata
   *   The metadata as an associative array.
   *
   * @return static
   *   The job entity.
   */
  public function setMetadata(array $metadata): static;

  /**
   * Get a specific metadata value.
   *
   * @param string $key
   *   The metadata key.
   * @param mixed $default
   *   The default value if key doesn't exist.
   *
   * @return mixed
   *   The metadata value or default.
   */
  public function getMetadataValue(string $key, mixed $default = NULL): mixed;

  /**
   * Set a specific metadata value.
   *
   * @param string $key
   *   The metadata key.
   * @param mixed $value
   *   The metadata value.
   *
   * @return static
   *   The job entity.
   */
  public function setMetadataValue(string $key, mixed $value): static;

  /**
   * Get the node ID (for backward compatibility).
   *
   * @return string
   *   The node ID.
   */
  public function getNodeId(): string;

  /**
   * Set the node ID (for backward compatibility).
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return static
   *   The job entity.
   */
  public function setNodeId(string $node_id): static;

  /**
   * Get the job status.
   *
   * @return string
   *   The job status.
   */
  public function getStatus(): string;

  /**
   * Set the job status.
   *
   * @param string $status
   *   The job status.
   *
   * @return static
   *   The job entity.
   */
  public function setStatus(string $status): static;

  /**
   * Get the job priority.
   *
   * @return int
   *   The job priority.
   */
  public function getPriority(): int;

  /**
   * Set the job priority.
   *
   * @param int $priority
   *   The job priority.
   *
   * @return static
   *   The job entity.
   */
  public function setPriority(int $priority): static;

  /**
   * Get the input data.
   *
   * @return array
   *   The input data as an array.
   */
  public function getInputData(): array;

  /**
   * Set the input data.
   *
   * @param array $input_data
   *   The input data as an array.
   *
   * @return static
   *   The job entity.
   */
  public function setInputData(array $input_data): static;

  /**
   * Get the output data.
   *
   * @return array
   *   The output data as an array.
   */
  public function getOutputData(): array;

  /**
   * Set the output data.
   *
   * @param array $output_data
   *   The output data as an array.
   *
   * @return static
   *   The job entity.
   */
  public function setOutputData(array $output_data): static;

  /**
   * Get the error message.
   *
   * @return string
   *   The error message.
   */
  public function getErrorMessage(): string;

  /**
   * Set the error message.
   *
   * @param string $error_message
   *   The error message.
   *
   * @return static
   *   The job entity.
   */
  public function setErrorMessage(string $error_message): static;

  /**
   * Get the started timestamp.
   *
   * @return int
   *   The started timestamp.
   */
  public function getStarted(): int;

  /**
   * Set the started timestamp.
   *
   * @param int $started
   *   The started timestamp.
   *
   * @return static
   *   The job entity.
   */
  public function setStarted(int $started): static;

  /**
   * Get the completed timestamp.
   *
   * @return int
   *   The completed timestamp.
   */
  public function getCompleted(): int;

  /**
   * Set the completed timestamp.
   *
   * @param int $completed
   *   The completed timestamp.
   *
   * @return static
   *   The job entity.
   */
  public function setCompleted(int $completed): static;

  /**
   * Get the retry count.
   *
   * @return int
   *   The retry count.
   */
  public function getRetryCount(): int;

  /**
   * Set the retry count.
   *
   * @param int $retry_count
   *   The retry count.
   *
   * @return static
   *   The job entity.
   */
  public function setRetryCount(int $retry_count): static;

  /**
   * Get the max retries.
   *
   * @return int
   *   The max retries.
   */
  public function getMaxRetries(): int;

  /**
   * Set the max retries.
   *
   * @param int $max_retries
   *   The max retries.
   *
   * @return static
   *   The job entity.
   */
  public function setMaxRetries(int $max_retries): static;

  /**
   * Get the jobs this job depends on.
   *
   * @return \Drupal\flowdrop_job\FlowDropJobInterface[]
   *   Array of job entities this job depends on.
   */
  public function getDependentJobs(): array;

  /**
   * Add a job dependency.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job this job depends on.
   *
   * @return static
   *   The job entity.
   */
  public function addDependentJob(FlowDropJobInterface $job): static;

  /**
   * Remove a job dependency.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job dependency to remove.
   *
   * @return static
   *   The job entity.
   */
  public function removeDependentJob(FlowDropJobInterface $job): static;

  /**
   * Clear all job dependencies.
   *
   * @return static
   *   The job entity.
   */
  public function clearDependentJobs(): static;

  /**
   * Get the executor plugin ID.
   *
   * @return string
   *   The executor plugin ID.
   */
  public function getExecutorPluginId(): string;

  /**
   * Set the executor plugin ID.
   *
   * @param string $executor_plugin_id
   *   The executor plugin ID.
   *
   * @return static
   *   The job entity.
   */
  public function setExecutorPluginId(string $executor_plugin_id): static;

  /**
   * Check if the job is ready to run (dependencies met).
   *
   * @param array $completed_jobs
   *   Array of completed job IDs or job entities.
   *
   * @return bool
   *   TRUE if the job is ready to run.
   */
  public function isReadyToRun(array $completed_jobs): bool;

  /**
   * Check if the job can be retried.
   *
   * @return bool
   *   TRUE if the job can be retried.
   */
  public function canRetry(): bool;

  /**
   * Increment the retry count.
   *
   * @return static
   *   The job entity.
   */
  public function incrementRetryCount(): static;

  /**
   * Mark the job as started.
   *
   * @return static
   *   The job entity.
   */
  public function markAsStarted(): static;

  /**
   * Mark the job as completed.
   *
   * @param array $output_data
   *   The output data.
   *
   * @return static
   *   The job entity.
   */
  public function markAsCompleted(array $output_data = []): static;

  /**
   * Mark the job as failed.
   *
   * @param string $error_message
   *   The error message.
   *
   * @return static
   *   The job entity.
   */
  public function markAsFailed(string $error_message = ''): static;

  /**
   * Mark the job as cancelled.
   *
   * @return static
   *   The job entity.
   */
  public function markAsCancelled(): static;

}
