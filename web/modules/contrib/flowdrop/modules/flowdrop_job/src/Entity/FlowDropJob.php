<?php

declare(strict_types=1);

namespace Drupal\flowdrop_job\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop_job\FlowDropJobAccessControlHandler;
use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\flowdrop_job\FlowDropJobListBuilder;
use Drupal\flowdrop_job\Form\FlowDropJobForm;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Defines the flowdrop job entity class.
 */
#[ContentEntityType(
  id: 'flowdrop_job',
  label: new TranslatableMarkup('FlowDrop Job'),
  label_collection: new TranslatableMarkup('FlowDrop Jobs'),
  label_singular: new TranslatableMarkup('flowdrop job'),
  label_plural: new TranslatableMarkup('flowdrop jobs'),
  entity_keys: [
    'id' => 'id',
    'bundle' => 'bundle',
    'label' => 'label',
    'owner' => 'uid',
    'published' => 'status',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropJobListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => FlowDropJobAccessControlHandler::class,
    'form' => [
      'add' => FlowDropJobForm::class,
      'edit' => FlowDropJobForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/flowdrop-job',
    'add-form' => '/flowdrop-job/add/{flowdrop_job_type}',
    'add-page' => '/flowdrop-job/add',
    'canonical' => '/flowdrop-job/{flowdrop_job}',
    'edit-form' => '/flowdrop-job/{flowdrop_job}/edit',
    'delete-form' => '/flowdrop-job/{flowdrop_job}/delete',
    'delete-multiple-form' => '/admin/content/flowdrop-job/delete-multiple',
  ],
  admin_permission: 'administer flowdrop_job types',
  bundle_entity_type: 'flowdrop_job_type',
  bundle_label: new TranslatableMarkup('FlowDrop Job type'),
  base_table: 'flowdrop_job',
  label_count: [
    'singular' => '@count flowdrop jobs',
    'plural' => '@count flowdrop jobs',
  ],
  field_ui_base_route: 'entity.flowdrop_job_type.edit_form',
)]
class FlowDropJob extends ContentEntityBase implements FlowDropJobInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the flowdrop job was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the flowdrop pipeline was last edited.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Node Type ID - References the pipeline this job belongs to.
    $fields['node_type_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Node Type'))
      ->setDescription(t('The ID of the node type this job needs to be processed by.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'flowdrop_node_type')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Metadata - JSON field for storing custom metadata.
    $fields['metadata'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Metadata'))
      ->setDescription(t('Custom metadata for this job (JSON format).'))
      ->setDefaultValue('{}')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Status - Current status of the job.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The current status of the job.'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSettings([
        'allowed_values' => [
          'pending' => 'Pending',
          'running' => 'Running',
          'completed' => 'Completed',
          'failed' => 'Failed',
          'cancelled' => 'Cancelled',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => -2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Priority - Job execution priority.
    $fields['priority'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Priority'))
      ->setDescription(t('The execution priority of this job (lower numbers = higher priority).'))
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Input Data - JSON field for job input data.
    $fields['input_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Input Data'))
      ->setDescription(t('The input data for this job (JSON format).'))
      ->setDefaultValue('{}')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Output Data - JSON field for job output data.
    $fields['output_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Output Data'))
      ->setDescription(t('The output data from this job (JSON format).'))
      ->setDefaultValue('{}')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Error Message - Error details if job failed.
    $fields['error_message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Error Message'))
      ->setDescription(t('Error message if the job failed.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Started Time - When the job started execution.
    $fields['started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Started'))
      ->setDescription(t('When the job started execution.'))
      ->setDefaultValue(NULL)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Completed Time - When the job completed execution.
    $fields['completed'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Completed'))
      ->setDescription(t('When the job completed execution.'))
      ->setDefaultValue(NULL)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Retry Count - Number of retry attempts.
    $fields['retry_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Retry Count'))
      ->setDescription(t('Number of retry attempts for this job.'))
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Max Retries - Maximum number of retry attempts.
    $fields['max_retries'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Max Retries'))
      ->setDescription(t('Maximum number of retry attempts for this job.'))
      ->setDefaultValue(3)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['depends_on'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Job ID'))
      ->setDescription(t('The ID of the job.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'flowdrop_job')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * Get the job metadata.
   *
   * @return array
   *   The metadata as an associative array.
   */
  public function getMetadata(): array {
    $data = $this->get('metadata')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * Set the job metadata.
   *
   * @param array $metadata
   *   The metadata as an associative array.
   *
   * @return static
   *   The job entity.
   */
  public function setMetadata(array $metadata): static {
    $this->set('metadata', json_encode($metadata));
    return $this;
  }

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
  public function getMetadataValue(string $key, mixed $default = NULL): mixed {
    $metadata = $this->getMetadata();
    return $metadata[$key] ?? $default;
  }

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
  public function setMetadataValue(string $key, mixed $value): static {
    $metadata = $this->getMetadata();
    $metadata[$key] = $value;
    return $this->setMetadata($metadata);
  }

  /**
   * Get the node ID from metadata (for backward compatibility).
   *
   * @return string
   *   The node ID.
   */
  public function getNodeId(): string {
    return $this->getMetadataValue('node_id', '');
  }

  /**
   * Set the node ID in metadata (for backward compatibility).
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return static
   *   The job entity.
   */
  public function setNodeId(string $node_id): static {
    return $this->setMetadataValue('node_id', $node_id);
  }

  /**
   * Get the job status.
   *
   * @return string
   *   The job status.
   */
  public function getStatus(): string {
    return $this->get('status')->value;
  }

  /**
   * Set the job status.
   *
   * @param string $status
   *   The job status.
   *
   * @return static
   *   The job entity.
   */
  public function setStatus(string $status): static {
    $this->set('status', $status);
    return $this;
  }

  /**
   * Get the job priority.
   *
   * @return int
   *   The job priority.
   */
  public function getPriority(): int {
    return (int) $this->get('priority')->value;
  }

  /**
   * Set the job priority.
   *
   * @param int $priority
   *   The job priority.
   *
   * @return static
   *   The job entity.
   */
  public function setPriority(int $priority): static {
    $this->set('priority', $priority);
    return $this;
  }

  /**
   * Get the input data.
   *
   * @return array
   *   The input data as an array.
   */
  public function getInputData(): array {
    $data = $this->get('input_data')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * Set the input data.
   *
   * @param array $input_data
   *   The input data as an array.
   *
   * @return static
   *   The job entity.
   */
  public function setInputData(array $input_data): static {
    $this->set('input_data', json_encode($input_data));
    return $this;
  }

  /**
   * Get the output data.
   *
   * @return array
   *   The output data as an array.
   */
  public function getOutputData(): array {
    $data = $this->get('output_data')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * Set the output data.
   *
   * @param array $output_data
   *   The output data as an array.
   *
   * @return static
   *   The job entity.
   */
  public function setOutputData(array $output_data): static {
    $this->set('output_data', json_encode($output_data));
    return $this;
  }

  /**
   * Get the error message.
   *
   * @return string
   *   The error message.
   */
  public function getErrorMessage(): string {
    return $this->get('error_message')->value ?? '';
  }

  /**
   * Set the error message.
   *
   * @param string $error_message
   *   The error message.
   *
   * @return static
   *   The job entity.
   */
  public function setErrorMessage(string $error_message): static {
    $this->set('error_message', $error_message);
    return $this;
  }

  /**
   * Get the started timestamp.
   *
   * @return int
   *   The started timestamp.
   */
  public function getStarted(): int {
    return (int) $this->get('started')->value;
  }

  /**
   * Set the started timestamp.
   *
   * @param int $started
   *   The started timestamp.
   *
   * @return static
   *   The job entity.
   */
  public function setStarted(int $started): static {
    $this->set('started', $started);
    return $this;
  }

  /**
   * Get the completed timestamp.
   *
   * @return int
   *   The completed timestamp.
   */
  public function getCompleted(): int {
    return (int) $this->get('completed')->value;
  }

  /**
   * Set the completed timestamp.
   *
   * @param int $completed
   *   The completed timestamp.
   *
   * @return static
   *   The job entity.
   */
  public function setCompleted(int $completed): static {
    $this->set('completed', $completed);
    return $this;
  }

  /**
   * Get the retry count.
   *
   * @return int
   *   The retry count.
   */
  public function getRetryCount(): int {
    return (int) $this->get('retry_count')->value;
  }

  /**
   * Set the retry count.
   *
   * @param int $retry_count
   *   The retry count.
   *
   * @return static
   *   The job entity.
   */
  public function setRetryCount(int $retry_count): static {
    $this->set('retry_count', $retry_count);
    return $this;
  }

  /**
   * Get the max retries.
   *
   * @return int
   *   The max retries.
   */
  public function getMaxRetries(): int {
    return (int) $this->get('max_retries')->value;
  }

  /**
   * Set the max retries.
   *
   * @param int $max_retries
   *   The max retries.
   *
   * @return static
   *   The job entity.
   */
  public function setMaxRetries(int $max_retries): static {
    $this->set('max_retries', $max_retries);
    return $this;
  }

  /**
   * Get the jobs this job depends on.
   *
   * @return \Drupal\flowdrop_job\FlowDropJobInterface[]
   *   Array of job entities this job depends on.
   */
  public function getDependentJobs(): array {
    $jobs = [];
    $items = $this->get('depends_on');

    foreach ($items as $item) {
      $entity = $item->get('entity')->getValue();
      if ($entity instanceof FlowDropJobInterface) {
        $jobs[] = $entity;
      }
    }

    return $jobs;
  }

  /**
   * Add a job dependency.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job this job depends on.
   *
   * @return static
   *   The job entity.
   */
  public function addDependentJob(FlowDropJobInterface $job): static {
    // Check if dependency already exists.
    $existing_jobs = $this->getDependentJobs();
    foreach ($existing_jobs as $existing_job) {
      if ($existing_job->id() === $job->id()) {
        // Already exists, no need to add.
        return $this;
      }
    }

    $this->get('depends_on')->appendItem(['target_id' => $job->id()]);
    return $this;
  }

  /**
   * Remove a job dependency.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job dependency to remove.
   *
   * @return static
   *   The job entity.
   */
  public function removeDependentJob(FlowDropJobInterface $job): static {
    $items = $this->get('depends_on');
    $job_id = $job->id();

    foreach ($items as $index => $item) {
      $target_id = $item->get('target_id')->getValue();
      if ($target_id === $job_id) {
        $items->removeItem($index);
        break;
      }
    }

    return $this;
  }

  /**
   * Clear all job dependencies.
   *
   * @return static
   *   The job entity.
   */
  public function clearDependentJobs(): static {
    $this->set('depends_on', []);
    return $this;
  }

  /**
   * Get the executor plugin ID.
   *
   * @return string
   *   The executor plugin ID.
   */
  public function getExecutorPluginId(): string {
    return $this->get('executor_plugin_id')->value;
  }

  /**
   * Set the executor plugin ID.
   *
   * @param string $executor_plugin_id
   *   The executor plugin ID.
   *
   * @return static
   *   The job entity.
   */
  public function setExecutorPluginId(string $executor_plugin_id): static {
    $this->set('executor_plugin_id', $executor_plugin_id);
    return $this;
  }

  /**
   * Check if the job is ready to run (dependencies met).
   *
   * @param array $completed_jobs
   *   Array of completed job IDs or job entities.
   *
   * @return bool
   *   TRUE if the job is ready to run.
   */
  public function isReadyToRun(array $completed_jobs): bool {
    $dependent_jobs = $this->getDependentJobs();

    // If no dependencies, job is ready to run.
    if (empty($dependent_jobs)) {
      return TRUE;
    }

    // Extract job IDs from completed jobs (support both IDs and entities).
    $completed_job_ids = [];
    foreach ($completed_jobs as $job) {
      if ($job instanceof FlowDropJobInterface) {
        $completed_job_ids[] = $job->id();
      }
      elseif (is_string($job) || is_numeric($job)) {
        $completed_job_ids[] = (string) $job;
      }
    }

    // Check if all dependent jobs are completed.
    foreach ($dependent_jobs as $dependent_job) {
      if (!in_array($dependent_job->id(), $completed_job_ids, TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Check if the job can be retried.
   *
   * @return bool
   *   TRUE if the job can be retried.
   */
  public function canRetry(): bool {
    return $this->getRetryCount() < $this->getMaxRetries();
  }

  /**
   * Increment the retry count.
   *
   * @return static
   *   The job entity.
   */
  public function incrementRetryCount(): static {
    $this->setRetryCount($this->getRetryCount() + 1);
    return $this;
  }

  /**
   * Mark the job as started.
   *
   * @return static
   *   The job entity.
   */
  public function markAsStarted(): static {
    $this->setStatus('running')
      ->setStarted(time());
    return $this;
  }

  /**
   * Mark the job as completed.
   *
   * @param array $output_data
   *   The output data.
   *
   * @return static
   *   The job entity.
   */
  public function markAsCompleted(array $output_data = []): static {
    $this->setStatus('completed')
      ->setCompleted(time())
      ->setOutputData($output_data);
    return $this;
  }

  /**
   * Mark the job as failed.
   *
   * @param string $error_message
   *   The error message.
   *
   * @return static
   *   The job entity.
   */
  public function markAsFailed(string $error_message = ''): static {
    $this->setStatus('failed')
      ->setCompleted(time())
      ->setErrorMessage($error_message);
    return $this;
  }

  /**
   * Mark the job as cancelled.
   *
   * @return static
   *   The job entity.
   */
  public function markAsCancelled(): static {
    $this->setStatus('cancelled')
      ->setCompleted(time());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set default values if not set.
    if ($this->isNew()) {
      if (!$this->get('status')->value) {
        $this->setStatus('pending');
      }
      if (!$this->get('priority')->value) {
        $this->setPriority(0);
      }
      if (!$this->get('retry_count')->value) {
        $this->setRetryCount(0);
      }
      if (!$this->get('max_retries')->value) {
        $this->setMaxRetries(3);
      }
    }
  }

}
