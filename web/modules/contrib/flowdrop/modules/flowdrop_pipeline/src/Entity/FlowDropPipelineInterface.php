<?php

namespace Drupal\flowdrop_pipeline\Entity;

use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\flowdrop_workflow\Entity\FlowDropWorkflow;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines the flowdrop pipeline entity class.
 */
interface FlowDropPipelineInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Get the workflow ID.
   *
   * @return string
   *   The workflow ID.
   */
  public function getWorkflowId(): string;

  /**
   * Set the workflow ID.
   *
   * @param string $workflow_id
   *   The workflow ID.
   *
   * @return static
   *   The pipeline entity.
   */
  public function setWorkflowId(string $workflow_id): static;

  /**
   * Get the pipeline status.
   *
   * @return string
   *   The pipeline status.
   */
  public function getStatus(): string;

  /**
   * Set the pipeline status.
   *
   * @param string $status
   *   The pipeline status.
   *
   * @return static
   *   The pipeline entity.
   */
  public function setStatus(string $status): static;

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
   *   The pipeline entity.
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
   *   The pipeline entity.
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
   *   The pipeline entity.
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
   *   The pipeline entity.
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
   *   The pipeline entity.
   */
  public function setCompleted(int $completed): static;

  /**
   * Get the max concurrent jobs.
   *
   * @return int
   *   The max concurrent jobs.
   */
  public function getMaxConcurrentJobs(): int;

  /**
   * Set the max concurrent jobs.
   *
   * @param int $max_concurrent_jobs
   *   The max concurrent jobs.
   *
   * @return static
   *   The pipeline entity.
   */
  public function setMaxConcurrentJobs(int $max_concurrent_jobs): static;

  /**
   * Get the job priority strategy.
   *
   * @return string
   *   The job priority strategy.
   */
  public function getJobPriorityStrategy(): string;

  /**
   * Set the job priority strategy.
   *
   * @param string $strategy
   *   The job priority strategy.
   *
   * @return static
   *   The pipeline entity.
   */
  public function setJobPriorityStrategy(string $strategy): static;

  /**
   * Get the retry strategy.
   *
   * @return string
   *   The retry strategy.
   */
  public function getRetryStrategy(): string;

  /**
   * Set the retry strategy.
   *
   * @param string $strategy
   *   The retry strategy.
   *
   * @return static
   *   The pipeline entity.
   */
  public function setRetryStrategy(string $strategy): static;

  /**
   * Get the execution context.
   *
   * @return array
   *   The execution context as an array.
   */
  public function getExecutionContext(): array;

  /**
   * Set the execution context.
   *
   * @param array $context
   *   The execution context as an array.
   *
   * @return static
   *   The pipeline entity.
   */
  public function setExecutionContext(array $context): static;

  /**
   * Calculate job counts dynamically.
   *
   * @return array
   *   Array with job counts by status.
   */
  public function calculateJobCounts(): array;

  /**
   * Check if the pipeline is ready to start.
   *
   * @return bool
   *   TRUE if the pipeline is ready to start.
   */
  public function isReadyToStart(): bool;

  /**
   * Check if the pipeline is running.
   *
   * @return bool
   *   TRUE if the pipeline is running.
   */
  public function isRunning(): bool;

  /**
   * Check if the pipeline is completed.
   *
   * @return bool
   *   TRUE if the pipeline is completed.
   */
  public function isCompleted(): bool;

  /**
   * Check if the pipeline is failed.
   *
   * @return bool
   *   TRUE if the pipeline is failed.
   */
  public function isFailed(): bool;

  /**
   * Check if the pipeline is cancelled.
   *
   * @return bool
   *   TRUE if the pipeline is cancelled.
   */
  public function isCancelled(): bool;

  /**
   * Check if the pipeline is paused.
   *
   * @return bool
   *   TRUE if the pipeline is paused.
   */
  public function isPaused(): bool;

  /**
   * Mark the pipeline as started.
   *
   * @return static
   *   The pipeline entity.
   */
  public function markAsStarted(): static;

  /**
   * Mark the pipeline as completed.
   *
   * @param array $output_data
   *   The output data.
   *
   * @return static
   *   The pipeline entity.
   */
  public function markAsCompleted(array $output_data = []): static;

  /**
   * Mark the pipeline as failed.
   *
   * @param string $error_message
   *   The error message.
   *
   * @return static
   *   The pipeline entity.
   */
  public function markAsFailed(string $error_message = ''): static;

  /**
   * Mark the pipeline as cancelled.
   *
   * @return static
   *   The pipeline entity.
   */
  public function markAsCancelled(): static;

  /**
   * Pause the pipeline.
   *
   * @return static
   *   The pipeline entity.
   */
  public function pause(): static;

  /**
   * Resume the pipeline.
   *
   * @return static
   *   The pipeline entity.
   */
  public function resume(): static;

  /**
   * Get the workflow entity.
   *
   * @return \Drupal\flowdrop_workflow\Entity\FlowDropWorkflow|null
   *   The workflow entity or NULL if not found.
   */
  public function getWorkflow(): ?FlowDropWorkflow;

  /**
   * Get all jobs for this pipeline.
   *
   * @return \Drupal\flowdrop_job\FlowDropJobInterface[]
   *   Array of job entities.
   */
  public function getJobs(): array;

  /**
   * Get jobs by status.
   *
   * @param string $status
   *   The job status to filter by.
   *
   * @return \Drupal\flowdrop_job\FlowDropJobInterface[]
   *   Array of job entities with the specified status.
   */
  public function getJobsByStatus(string $status): array;

  /**
   * Get ready jobs (jobs that can be executed).
   *
   * @return \Drupal\flowdrop_job\FlowDropJobInterface[]
   *   Array of ready job entities.
   */
  public function getReadyJobs(): array;

  /**
   * Check if the pipeline has any running jobs.
   *
   * @return bool
   *   TRUE if the pipeline has running jobs.
   */
  public function hasRunningJobs(): bool;

  /**
   * Check if the pipeline has any pending jobs.
   *
   * @return bool
   *   TRUE if the pipeline has pending jobs.
   */
  public function hasPendingJobs(): bool;

  /**
   * Check if all jobs are completed.
   *
   * @return bool
   *   TRUE if all jobs are completed.
   */
  public function allJobsCompleted(): bool;

  /**
   * Check if any jobs failed.
   *
   * @return bool
   *   TRUE if any jobs failed.
   */
  public function hasFailedJobs(): bool;

  /**
   * Add a job to this pipeline.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to add.
   *
   * @return static
   *   The pipeline entity.
   */
  public function addJob(FlowDropJobInterface $job): static;

  /**
   * Remove a job from this pipeline.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to remove.
   *
   * @return static
   *   The pipeline entity.
   */
  public function removeJob(FlowDropJobInterface $job): static;

  /**
   * Clear all jobs from this pipeline.
   *
   * @return static
   *   The pipeline entity.
   */
  public function clearJobs(): static;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage);

}
