<?php

declare(strict_types=1);

namespace Drupal\flowdrop_pipeline\Entity;

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
use Drupal\flowdrop_job\FlowDropJobInterface;
use Drupal\flowdrop_pipeline\FlowDropPipelineAccessControlHandler;
use Drupal\flowdrop_pipeline\FlowDropPipelineListBuilder;
use Drupal\flowdrop_pipeline\Form\FlowDropPipelineForm;
use Drupal\flowdrop_workflow\Entity\FlowDropWorkflow;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * FlowDrop Pipeline Entity.
 */
#[ContentEntityType(
  id: 'flowdrop_pipeline',
  label: new TranslatableMarkup('FlowDrop Pipeline'),
  label_collection: new TranslatableMarkup('FlowDrop Pipelines'),
  label_singular: new TranslatableMarkup('flowdrop pipeline'),
  label_plural: new TranslatableMarkup('flowdrop pipelines'),
  entity_keys: [
    'id' => 'id',
    'bundle' => 'bundle',
    'label' => 'label',
    'owner' => 'uid',
    'published' => 'status',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropPipelineListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => FlowDropPipelineAccessControlHandler::class,
    'form' => [
      'add' => FlowDropPipelineForm::class,
      'edit' => FlowDropPipelineForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/flowdrop-pipeline',
    'add-form' => '/flowdrop-pipeline/add/{flowdrop_pipeline_type}',
    'add-page' => '/flowdrop-pipeline/add',
    'canonical' => '/flowdrop-pipeline/{flowdrop_pipeline}',
    'edit-form' => '/flowdrop-pipeline/{flowdrop_pipeline}/edit',
    'delete-form' => '/flowdrop-pipeline/{flowdrop_pipeline}/delete',
    'delete-multiple-form' => '/admin/content/flowdrop-pipeline/delete-multiple',
  ],
  admin_permission: 'administer flowdrop_pipeline types',
  bundle_entity_type: 'flowdrop_pipeline_type',
  bundle_label: new TranslatableMarkup('FlowDrop Pipeline type'),
  base_table: 'flowdrop_pipeline',
  label_count: [
    'singular' => '@count flowdrop pipelines',
    'plural' => '@count flowdrop pipelines',
  ],
  field_ui_base_route: 'entity.flowdrop_pipeline_type.edit_form',
)]
class FlowDropPipeline extends ContentEntityBase implements FlowDropPipelineInterface {

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
      ->setDescription(t('The time that the flowdrop pipeline was created.'))
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

    // Workflow ID - References the workflow this pipeline is based on.
    $fields['workflow_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Workflow ID'))
      ->setDescription(t('The ID of the workflow this pipeline is based on.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'flowdrop_workflow')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Pipeline ID - References the pipeline this job belongs to.
    $fields['job_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Job ID'))
      ->setDescription(t('The ID of the job.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'flowdrop_job')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Status - Current status of the pipeline.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The current status of the pipeline.'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSettings([
        'allowed_values' => [
          'pending' => 'Pending',
          'running' => 'Running',
          'completed' => 'Completed',
          'failed' => 'Failed',
          'cancelled' => 'Cancelled',
          'paused' => 'Paused',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Input Data - JSON field for pipeline input data.
    $fields['input_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Input Data'))
      ->setDescription(t('The input data for this pipeline (JSON format).'))
      ->setDefaultValue('{}')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Output Data - JSON field for pipeline output data.
    $fields['output_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Output Data'))
      ->setDescription(t('The output data from this pipeline (JSON format).'))
      ->setDefaultValue('{}')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Error Message - Error details if pipeline failed.
    $fields['error_message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Error Message'))
      ->setDescription(t('Error message if the pipeline failed.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Started Time - When the pipeline started execution.
    $fields['started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Started'))
      ->setDescription(t('When the pipeline started execution.'))
      ->setDefaultValue(NULL)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Completed Time - When the pipeline completed execution.
    $fields['completed'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Completed'))
      ->setDescription(t('When the pipeline completed execution.'))
      ->setDefaultValue(NULL)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Max Concurrent Jobs - Maximum number of jobs to run simultaneously.
    $fields['max_concurrent_jobs'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Max Concurrent Jobs'))
      ->setDescription(t('Maximum number of jobs to run simultaneously.'))
      ->setDefaultValue(5)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Job Priority Strategy - How to prioritize jobs.
    $fields['job_priority_strategy'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Job Priority Strategy'))
      ->setDescription(t('How to prioritize jobs in this pipeline.'))
      ->setDefaultValue('dependency_order')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Retry Strategy - How to handle job failures.
    $fields['retry_strategy'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Retry Strategy'))
      ->setDescription(t('How to handle job failures in this pipeline.'))
      ->setDefaultValue('individual')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Execution Context - JSON field for pipeline execution context.
    $fields['execution_context'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Execution Context'))
      ->setDescription(t('Execution context data for the pipeline (JSON format).'))
      ->setDefaultValue('{}')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function getWorkflowId(): string {
    return $this->get('workflow_id')->target_id;
  }

  /**
   * {@inheritDoc}
   */
  public function setWorkflowId(string $workflow_id): static {
    $this->set('workflow_id', ['target_id' => $workflow_id]);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getStatus(): string {
    return $this->get('status')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setStatus(string $status): static {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getInputData(): array {
    $data = $this->get('input_data')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * {@inheritDoc}
   */
  public function setInputData(array $input_data): static {
    $this->set('input_data', json_encode($input_data));
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getOutputData(): array {
    $data = $this->get('output_data')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * {@inheritDoc}
   */
  public function setOutputData(array $output_data): static {
    $this->set('output_data', json_encode($output_data));
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getErrorMessage(): string {
    return $this->get('error_message')->value ?? '';
  }

  /**
   * {@inheritDoc}
   */
  public function setErrorMessage(string $error_message): static {
    $this->set('error_message', $error_message);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getStarted(): int {
    return (int) $this->get('started')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setStarted(int $started): static {
    $this->set('started', $started);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getCompleted(): int {
    return (int) $this->get('completed')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setCompleted(int $completed): static {
    $this->set('completed', $completed);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getMaxConcurrentJobs(): int {
    return (int) $this->get('max_concurrent_jobs')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setMaxConcurrentJobs(int $max_concurrent_jobs): static {
    $this->set('max_concurrent_jobs', $max_concurrent_jobs);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getJobPriorityStrategy(): string {
    return $this->get('job_priority_strategy')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setJobPriorityStrategy(string $strategy): static {
    $this->set('job_priority_strategy', $strategy);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getRetryStrategy(): string {
    return $this->get('retry_strategy')->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setRetryStrategy(string $strategy): static {
    $this->set('retry_strategy', $strategy);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutionContext(): array {
    $data = $this->get('execution_context')->value;
    return $data ? json_decode($data, TRUE) : [];
  }

  /**
   * {@inheritDoc}
   */
  public function setExecutionContext(array $context): static {
    $this->set('execution_context', json_encode($context));
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function isReadyToStart(): bool {
    return $this->getStatus() === 'pending';
  }

  /**
   * {@inheritDoc}
   */
  public function isRunning(): bool {
    return $this->getStatus() === 'running';
  }

  /**
   * {@inheritDoc}
   */
  public function isCompleted(): bool {
    return $this->getStatus() === 'completed';
  }

  /**
   * {@inheritDoc}
   */
  public function isFailed(): bool {
    return $this->getStatus() === 'failed';
  }

  /**
   * {@inheritDoc}
   */
  public function isCancelled(): bool {
    return $this->getStatus() === 'cancelled';
  }

  /**
   * {@inheritDoc}
   */
  public function isPaused(): bool {
    return $this->getStatus() === 'paused';
  }

  /**
   * {@inheritDoc}
   */
  public function markAsStarted(): static {
    $this->setStatus('running')
      ->setStarted(time());
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function markAsCompleted(array $output_data = []): static {
    $this->setStatus('completed')
      ->setCompleted(time())
      ->setOutputData($output_data);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function markAsFailed(string $error_message = ''): static {
    $this->setStatus('failed')
      ->setCompleted(time())
      ->setErrorMessage($error_message);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function markAsCancelled(): static {
    $this->setStatus('cancelled')
      ->setCompleted(time());
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function pause(): static {
    $this->setStatus('paused');
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function resume(): static {
    if ($this->getStatus() === 'paused') {
      $this->setStatus('running');
    }
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getWorkflow(): ?FlowDropWorkflow {
    try {
      $workflow = $this->get('workflow_id')->entity;
      return $workflow instanceof FlowDropWorkflow ? $workflow : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getJobs(): array {
    $output = [];
    try {
      $job_field = $this->get('job_id');
      if ($job_field->isEmpty()) {
        return [];
      }

      $job_storage = \Drupal::entityTypeManager()->getStorage('flowdrop_job');
      $job_ids = [];

      foreach ($job_field as $item) {
        if (!empty($item->target_id)) {
          $job_ids[] = $item->target_id;
        }
      }

      if (empty($job_ids)) {
        return [];
      }

      $jobs = $job_storage->loadMultiple($job_ids);

      // Sort by priority and created date.
      usort($jobs, function ($a, $b) {
        assert($a instanceof FlowDropJobInterface);
        assert($b instanceof FlowDropJobInterface);
        $priority_comparison = $a->getPriority() <=> $b->getPriority();
        if ($priority_comparison !== 0) {
          return $priority_comparison;
        }
        return (int) $a->get('created')->value <=> (int) $b->get('created')->value;
      });

      foreach ($jobs as $job) {
        if ($job instanceof FlowDropJobInterface) {
          $output[] = $job;
        }
      }
      return $output;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getJobsByStatus(string $status): array {
    $all_jobs = $this->getJobs();
    $filtered_jobs = [];

    foreach ($all_jobs as $job) {
      if ($job->getStatus() === $status) {
        $filtered_jobs[] = $job;
      }
    }

    return $filtered_jobs;
  }

  /**
   * {@inheritDoc}
   */
  public function getReadyJobs(): array {
    // Use the JobGenerationService for proper trigger/gateway handling.
    $job_generation_service = \Drupal::service('flowdrop_pipeline.job_generation');
    return $job_generation_service->getReadyJobs($this);
  }

  /**
   * {@inheritDoc}
   */
  public function hasRunningJobs(): bool {
    $running_jobs = $this->getJobsByStatus('running');
    return !empty($running_jobs);
  }

  /**
   * {@inheritDoc}
   */
  public function hasPendingJobs(): bool {
    $pending_jobs = $this->getJobsByStatus('pending');
    return !empty($pending_jobs);
  }

  /**
   * Add a job to this pipeline.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to add.
   *
   * @return static
   *   The pipeline entity.
   */
  public function addJob(FlowDropJobInterface $job): static {
    $current_jobs = $this->get('job_id')->getValue();
    $job_ids = array_column($current_jobs, 'target_id');

    // Only add if not already present.
    if (!in_array($job->id(), $job_ids)) {
      $this->get('job_id')->appendItem(['target_id' => $job->id()]);
    }

    return $this;
  }

  /**
   * Remove a job from this pipeline.
   *
   * @param \Drupal\flowdrop_job\FlowDropJobInterface $job
   *   The job to remove.
   *
   * @return static
   *   The pipeline entity.
   */
  public function removeJob(FlowDropJobInterface $job): static {
    $job_field = $this->get('job_id');
    $items_to_remove = [];

    foreach ($job_field as $delta => $item) {
      $item_value = $item->getValue();
      if (isset($item_value['target_id']) && $item_value['target_id'] == $job->id()) {
        $items_to_remove[] = $delta;
      }
    }

    // Remove in reverse order to maintain indexes.
    foreach (array_reverse($items_to_remove) as $delta) {
      $job_field->removeItem($delta);
    }

    return $this;
  }

  /**
   * Clear all jobs from this pipeline.
   *
   * @return static
   *   The pipeline entity.
   */
  public function clearJobs(): static {
    $this->get('job_id')->setValue([]);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function allJobsCompleted(): bool {
    $jobs = $this->getJobs();
    if (empty($jobs)) {
      return FALSE;
    }

    foreach ($jobs as $job) {
      $status = $job->getStatus();
      if ($status === 'pending' || $status === 'running') {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function hasFailedJobs(): bool {
    $failed_jobs = $this->getJobsByStatus('failed');
    return !empty($failed_jobs);
  }

  /**
   * Calculate job counts dynamically.
   *
   * @return array
   *   Array with job counts by status.
   */
  public function calculateJobCounts(): array {
    $jobs = $this->getJobs();
    $counts = [
      'total' => 0,
      'pending' => 0,
      'running' => 0,
      'completed' => 0,
      'failed' => 0,
      'cancelled' => 0,
    ];

    foreach ($jobs as $job) {
      $status = $job->getStatus();
      $counts['total']++;
      if (isset($counts[$status])) {
        $counts[$status]++;
      }
    }

    return $counts;
  }

  /**
   * {@inheritDoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set default values if not set.
    if ($this->isNew()) {
      if (!$this->get('status')->value) {
        $this->setStatus('pending');
      }
      if (!$this->get('max_concurrent_jobs')->value) {
        $this->setMaxConcurrentJobs(5);
      }
      if (!$this->get('job_priority_strategy')->value) {
        $this->setJobPriorityStrategy('dependency_order');
      }
      if (!$this->get('retry_strategy')->value) {
        $this->setRetryStrategy('individual');
      }
    }
  }

}
