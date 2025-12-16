<?php

declare(strict_types=1);

namespace Drupal\flowdrop_job\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop_job\FlowDropJobTypeListBuilder;
use Drupal\flowdrop_job\Form\FlowDropJobTypeForm;

/**
 * Defines the FlowDrop Job type configuration entity.
 */
#[ConfigEntityType(
  id: 'flowdrop_job_type',
  label: new TranslatableMarkup('FlowDrop Job type'),
  label_collection: new TranslatableMarkup('FlowDrop Job types'),
  label_singular: new TranslatableMarkup('flowdrop job type'),
  label_plural: new TranslatableMarkup('flowdrop jobs types'),
  config_prefix: 'flowdrop_job_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropJobTypeListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'form' => [
      'add' => FlowDropJobTypeForm::class,
      'edit' => FlowDropJobTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/flowdrop_job_types/add',
    'edit-form' => '/admin/structure/flowdrop_job_types/manage/{flowdrop_job_type}',
    'delete-form' => '/admin/structure/flowdrop_job_types/manage/{flowdrop_job_type}/delete',
    'collection' => '/admin/structure/flowdrop_job_types',
  ],
  admin_permission: 'administer flowdrop_job types',
  bundle_of: 'flowdrop_job',
  label_count: [
    'singular' => '@count flowdrop job type',
    'plural' => '@count flowdrop jobs types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'description',
    'default_priority',
    'default_max_retries',
    'node_executor_plugin',
    'execution_timeout',
    'memory_limit',
  ],
)]
final class FlowDropJobType extends ConfigEntityBundleBase {
  /**
   * The machine name of this flowdrop job type.
   */
  protected string $id;

  /**
   * The human-readable name of the flowdrop job type.
   */
  protected string $label;

  /**
   * The job type description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The default priority for jobs of this type.
   *
   * @var int
   */
  protected $default_priority = 0;

  /**
   * The default max retries for jobs of this type.
   *
   * @var int
   */
  protected $default_max_retries = 3;

  /**
   * The node executor plugin ID for jobs of this type.
   *
   * @var string
   */
  protected $node_executor_plugin = '';

  /**
   * The execution timeout in seconds.
   *
   * @var int
   */
  protected $execution_timeout = 300;

  /**
   * The memory limit in MB.
   *
   * @var int
   */
  protected $memory_limit = 128;

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
   *   The job type entity.
   */
  public function setDescription(string $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * Get the default priority.
   *
   * @return int
   *   The default priority.
   */
  public function getDefaultPriority(): int {
    return $this->default_priority;
  }

  /**
   * Set the default priority.
   *
   * @param int $priority
   *   The default priority.
   *
   * @return static
   *   The job type entity.
   */
  public function setDefaultPriority(int $priority): static {
    $this->default_priority = $priority;
    return $this;
  }

  /**
   * Get the default max retries.
   *
   * @return int
   *   The default max retries.
   */
  public function getDefaultMaxRetries(): int {
    return $this->default_max_retries;
  }

  /**
   * Set the default max retries.
   *
   * @param int $max_retries
   *   The default max retries.
   *
   * @return static
   *   The job type entity.
   */
  public function setDefaultMaxRetries(int $max_retries): static {
    $this->default_max_retries = $max_retries;
    return $this;
  }

  /**
   * Get the node executor plugin ID.
   *
   * @return string
   *   The node executor plugin ID.
   */
  public function getNodeExecutorPlugin(): string {
    return $this->node_executor_plugin;
  }

  /**
   * Set the node executor plugin ID.
   *
   * @param string $plugin_id
   *   The node executor plugin ID.
   *
   * @return static
   *   The job type entity.
   */
  public function setNodeExecutorPlugin(string $plugin_id): static {
    $this->node_executor_plugin = $plugin_id;
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
   *   The job type entity.
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
   *   The job type entity.
   */
  public function setMemoryLimit(int $limit): static {
    $this->memory_limit = $limit;
    return $this;
  }

}
