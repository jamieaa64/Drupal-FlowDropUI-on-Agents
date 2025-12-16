<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop_workflow\FlowDropWorkflowInterface;
use Drupal\flowdrop_workflow\FlowDropWorkflowListBuilder;
use Drupal\flowdrop_workflow\Form\FlowDropWorkflowForm;

/**
 * Defines the flowdrop workflow entity type.
 */
#[ConfigEntityType(
  id: 'flowdrop_workflow',
  label: new TranslatableMarkup('FlowDrop Workflow'),
  label_collection: new TranslatableMarkup('FlowDrop Workflows'),
  label_singular: new TranslatableMarkup('workflow'),
  label_plural: new TranslatableMarkup('workflows'),
  label_count: [
    'singular' => '@count workflow',
    'plural' => '@count workflows',
  ],
  config_prefix: 'flowdrop_workflow',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropWorkflowListBuilder::class,
    'form' => [
      'add' => FlowDropWorkflowForm::class,
      'edit' => FlowDropWorkflowForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\AdminHtmlRouteProvider',
    ],
  ],
  links: [
    'canonical' => '/admin/structure/flowdrop-workflow/{flowdrop_workflow}',
    'add-form' => '/admin/structure/flowdrop-workflow/add',
    'edit-form' => '/admin/structure/flowdrop-workflow/{flowdrop_workflow}',
    'delete-form' => '/admin/structure/flowdrop-workflow/{flowdrop_workflow}/delete',
    'collection' => '/admin/structure/flowdrop-workflow',
  ],
  admin_permission: 'administer flowdrop_workflow',
  config_export: [
    'id',
    'label',
    'description',
    'nodes',
    'edges',
    'metadata',
    'created',
    'changed',
    'uid',
  ],
)]
final class FlowDropWorkflow extends ConfigEntityBase implements FlowDropWorkflowInterface {

  /**
   * The workflow label.
   *
   * @var string
   */
  protected string $label = '';

  /**
   * The workflow description.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * The workflow nodes.
   *
   * @var array
   */
  protected array $nodes = [];

  /**
   * The workflow edges.
   *
   * @var array
   */
  protected array $edges = [];

  /**
   * The workflow metadata.
   *
   * @var array
   */
  protected array $metadata = [];

  /**
   * The creation timestamp.
   *
   * @var int
   */
  protected int $created = 0;

  /**
   * The last modified timestamp.
   *
   * @var int
   */
  protected int $changed = 0;

  /**
   * The user ID who created the workflow.
   *
   * @var int
   */
  protected int $uid = 0;

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label): static {
    $this->label = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodes(): array {
    return $this->nodes;
  }

  /**
   * {@inheritdoc}
   */
  public function setNodes(array $nodes): static {
    $this->nodes = $nodes;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEdges(): array {
    return $this->edges;
  }

  /**
   * {@inheritdoc}
   */
  public function setEdges(array $edges): static {
    $this->edges = $edges;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function setMetadata(array $metadata): static {
    $this->metadata = $metadata;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreated(): int {
    return $this->created;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreated(int $created): static {
    $this->created = $created;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChanged(): int {
    return $this->changed;
  }

  /**
   * {@inheritdoc}
   */
  public function setChanged(int $changed): static {
    $this->changed = $changed;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUid(): int {
    return $this->uid;
  }

  /**
   * {@inheritdoc}
   */
  public function setUid(int $uid): static {
    $this->uid = $uid;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    $now = time();

    if ($this->isNew()) {
      $this->setCreated($now);
      $this->setChanged($now);
    }
    else {
      $this->setChanged($now);
    }
  }

}
