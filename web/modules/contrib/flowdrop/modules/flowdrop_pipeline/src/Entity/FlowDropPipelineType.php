<?php

declare(strict_types=1);

namespace Drupal\flowdrop_pipeline\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop_pipeline\FlowDropPipelineTypeListBuilder;
use Drupal\flowdrop_pipeline\Form\FlowDropPipelineTypeForm;

/**
 * Defines the FlowDrop Pipeline type configuration entity.
 */
#[ConfigEntityType(
  id: 'flowdrop_pipeline_type',
  label: new TranslatableMarkup('FlowDrop Pipeline type'),
  label_collection: new TranslatableMarkup('FlowDrop Pipeline types'),
  label_singular: new TranslatableMarkup('flowdrop pipeline type'),
  label_plural: new TranslatableMarkup('flowdrop pipelines types'),
  config_prefix: 'flowdrop_pipeline_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropPipelineTypeListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'form' => [
      'add' => FlowDropPipelineTypeForm::class,
      'edit' => FlowDropPipelineTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/flowdrop_pipeline_types/add',
    'edit-form' => '/admin/structure/flowdrop_pipeline_types/manage/{flowdrop_pipeline_type}',
    'delete-form' => '/admin/structure/flowdrop_pipeline_types/manage/{flowdrop_pipeline_type}/delete',
    'collection' => '/admin/structure/flowdrop_pipeline_types',
  ],
  admin_permission: 'administer flowdrop_pipeline types',
  bundle_of: 'flowdrop_pipeline',
  label_count: [
    'singular' => '@count flowdrop pipeline type',
    'plural' => '@count flowdrop pipelines types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'description',
    'default_max_concurrent_jobs',
    'default_job_priority_strategy',
    'default_retry_strategy',
    'execution_timeout',
    'memory_limit',
    'allowed_workflow_types',
  ],
)]
final class FlowDropPipelineType extends ConfigEntityBundleBase {
  /**
   * The machine name of this flowdrop pipeline type.
   */
  protected string $id;

  /**
   * The human-readable name of the flowdrop pipeline type.
   */
  protected string $label;

  /**
   * The pipeline type description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The default max concurrent jobs for pipelines of this type.
   *
   * @var int
   */
  protected $default_max_concurrent_jobs = 5;

  /**
   * The default job priority strategy for pipelines of this type.
   *
   * @var string
   */
  protected $default_job_priority_strategy = 'dependency_order';

  /**
   * The default retry strategy for pipelines of this type.
   *
   * @var string
   */
  protected $default_retry_strategy = 'individual';

  /**
   * The execution timeout in seconds.
   *
   * @var int
   */
  protected $execution_timeout = 3600;

  /**
   * The memory limit in MB.
   *
   * @var int
   */
  protected $memory_limit = 512;

  /**
   * The allowed workflow types for pipelines of this type.
   *
   * @var array
   */
  protected $allowed_workflow_types = [];

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Set the description.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setDescription(string $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * Get the default max concurrent jobs.
   *
   * @return int
   *   The default max concurrent jobs.
   */
  public function getDefaultMaxConcurrentJobs(): int {
    return $this->default_max_concurrent_jobs;
  }

  /**
   * Set the default max concurrent jobs.
   *
   * @param int $max_jobs
   *   The default max concurrent jobs.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setDefaultMaxConcurrentJobs(int $max_jobs): static {
    $this->default_max_concurrent_jobs = $max_jobs;
    return $this;
  }

  /**
   * Get the default job priority strategy.
   *
   * @return string
   *   The default job priority strategy.
   */
  public function getDefaultJobPriorityStrategy(): string {
    return $this->default_job_priority_strategy;
  }

  /**
   * Set the default job priority strategy.
   *
   * @param string $strategy
   *   The default job priority strategy.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setDefaultJobPriorityStrategy(string $strategy): static {
    $this->default_job_priority_strategy = $strategy;
    return $this;
  }

  /**
   * Get the default retry strategy.
   *
   * @return string
   *   The default retry strategy.
   */
  public function getDefaultRetryStrategy(): string {
    return $this->default_retry_strategy;
  }

  /**
   * Set the default retry strategy.
   *
   * @param string $strategy
   *   The default retry strategy.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setDefaultRetryStrategy(string $strategy): static {
    $this->default_retry_strategy = $strategy;
    return $this;
  }

  /**
   * Get the execution timeout.
   *
   * @return int
   *   The execution timeout in seconds.
   */
  public function getExecutionTimeout(): int {
    return $this->execution_timeout;
  }

  /**
   * Set the execution timeout.
   *
   * @param int $timeout
   *   The execution timeout in seconds.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setExecutionTimeout(int $timeout): static {
    $this->execution_timeout = $timeout;
    return $this;
  }

  /**
   * Get the memory limit.
   *
   * @return int
   *   The memory limit in MB.
   */
  public function getMemoryLimit(): int {
    return $this->memory_limit;
  }

  /**
   * Set the memory limit.
   *
   * @param int $limit
   *   The memory limit in MB.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setMemoryLimit(int $limit): static {
    $this->memory_limit = $limit;
    return $this;
  }

  /**
   * Get the allowed workflow types.
   *
   * @return array
   *   The allowed workflow types.
   */
  public function getAllowedWorkflowTypes(): array {
    return $this->allowed_workflow_types;
  }

  /**
   * Set the allowed workflow types.
   *
   * @param array $types
   *   The allowed workflow types.
   *
   * @return static
   *   The pipeline type entity.
   */
  public function setAllowedWorkflowTypes(array $types): static {
    $this->allowed_workflow_types = $types;
    return $this;
  }

  /**
   * Check if a workflow type is allowed.
   *
   * @param string $workflow_type
   *   The workflow type to check.
   *
   * @return bool
   *   TRUE if the workflow type is allowed.
   */
  public function isWorkflowTypeAllowed(string $workflow_type): bool {
    return empty($this->allowed_workflow_types) ||
           in_array($workflow_type, $this->allowed_workflow_types);
  }

}
