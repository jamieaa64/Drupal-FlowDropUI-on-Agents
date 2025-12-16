<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_type\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;
use Drupal\flowdrop_node_type\FlowDropNodeTypeListBuilder;
use Drupal\flowdrop_node_type\Form\FlowDropNodeTypeForm;

/**
 * Defines the flowdrop node type entity type.
 */
#[ConfigEntityType(
  id: 'flowdrop_node_type',
  label: new TranslatableMarkup('FlowDrop Node Type'),
  label_collection: new TranslatableMarkup('FlowDrop Node Types'),
  label_singular: new TranslatableMarkup('flowdrop node type'),
  label_plural: new TranslatableMarkup('flowdrop node types'),
  config_prefix: 'flowdrop_node_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropNodeTypeListBuilder::class,
    'form' => [
      'add' => FlowDropNodeTypeForm::class,
      'edit' => FlowDropNodeTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/flowdrop-node-type',
    'add-form' => '/admin/structure/flowdrop-node-type/add',
    'edit-form' => '/admin/structure/flowdrop-node-type/{flowdrop_node_type}/edit',
    'delete-form' => '/admin/structure/flowdrop-node-type/{flowdrop_node_type}/delete',
  ],
  admin_permission: 'administer flowdrop_node_type',
  label_count: [
    'singular' => '@count flowdrop node type',
    'plural' => '@count flowdrop node types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'description',
    'category',
    'icon',
    'color',
    'version',
    'enabled',
    'config',
    'tags',
    'executor_plugin',
  ],
)]
final class FlowDropNodeType extends ConfigEntityBase implements FlowDropNodeTypeInterface {

  /**
   * The node type ID.
   */
  protected string $id;

  /**
   * The node type label.
   */
  protected string $label;

  /**
   * The node type description.
   */
  protected string $description = '';

  /**
   * The node category.
   */
  protected string $category = 'processing';

  /**
   * The node icon.
   */
  protected string $icon = 'mdi:cog';

  /**
   * The node color.
   */
  protected string $color = '#007cba';

  /**
   * The node version.
   */
  protected string $version = '1.0.0';

  /**
   * Whether the node type is enabled.
   */
  protected bool $enabled = TRUE;

  /**
   * The node configuration schema.
   */
  protected array $config = [];

  /**
   * The node tags.
   */
  protected array $tags = [];

  /**
   * The executor plugin ID.
   */
  protected string $executor_plugin = '';

  /**
   * Get the label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Set the label.
   *
   * @param string $label
   *   The label.
   *
   * @return static
   *   The node entity.
   */
  public function setLabel(string $label): static {
    $this->label = $label;
    return $this;
  }

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
   *   The node entity.
   */
  public function setDescription(string $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * Get the category.
   *
   * @return string
   *   The category.
   */
  public function getCategory(): string {
    return $this->category;
  }

  /**
   * Set the category.
   *
   * @param string $category
   *   The category.
   *
   * @return static
   *   The node entity.
   */
  public function setCategory(string $category): static {
    $this->category = $category;
    return $this;
  }

  /**
   * Get the icon.
   *
   * @return string
   *   The icon.
   */
  public function getIcon(): string {
    return $this->icon;
  }

  /**
   * Set the icon.
   *
   * @param string $icon
   *   The icon.
   *
   * @return static
   *   The node entity.
   */
  public function setIcon(string $icon): static {
    $this->icon = $icon;
    return $this;
  }

  /**
   * Get the color.
   *
   * @return string
   *   The color.
   */
  public function getColor(): string {
    return $this->color;
  }

  /**
   * Set the color.
   *
   * @param string $color
   *   The color.
   *
   * @return static
   *   The node entity.
   */
  public function setColor(string $color): static {
    $this->color = $color;
    return $this;
  }

  /**
   * Get the version.
   *
   * @return string
   *   The version.
   */
  public function getVersion(): string {
    return $this->version;
  }

  /**
   * Set the version.
   *
   * @param string $version
   *   The version.
   *
   * @return static
   *   The node entity.
   */
  public function setVersion(string $version): static {
    $this->version = $version;
    return $this;
  }

  /**
   * Check if the node type is enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function isEnabled(): bool {
    return $this->enabled;
  }

  /**
   * Set the enabled status.
   *
   * @param bool $enabled
   *   The enabled status.
   *
   * @return static
   *   The node entity.
   */
  public function setEnabled(bool $enabled): static {
    $this->enabled = $enabled;
    return $this;
  }

  /**
   * Get the configuration schema.
   *
   * @return array
   *   The configuration schema.
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Set the configuration schema.
   *
   * @param array $config
   *   The configuration schema.
   *
   * @return static
   *   The node entity.
   */
  public function setConfig(array $config): static {
    $this->config = $config;
    return $this;
  }

  /**
   * Get the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags(): array {
    return $this->tags;
  }

  /**
   * Set the tags.
   *
   * @param array $tags
   *   The tags.
   *
   * @return static
   *   The node entity.
   */
  public function setTags(array $tags): static {
    $this->tags = $tags;
    return $this;
  }

  /**
   * Get the executor plugin ID.
   *
   * @return string
   *   The executor plugin ID.
   */
  public function getExecutorPlugin(): string {
    return $this->executor_plugin;
  }

  /**
   * Set the executor plugin ID.
   *
   * @param string $executor_plugin
   *   The executor plugin ID.
   *
   * @return static
   *   The node entity.
   */
  public function setExecutorPlugin(string $executor_plugin): static {
    $this->executor_plugin = $executor_plugin;
    return $this;
  }

  /**
   * Transform singular category to plural form for API compatibility.
   *
   * @param string $category
   *   The singular category.
   *
   * @return string
   *   The plural category.
   */
  protected function transformCategoryToPlural(string $category): string {
    $category_mapping = [
      'input' => 'inputs',
      'output' => 'outputs',
      'model' => 'models',
      'prompt' => 'prompts',
      'processing' => 'processing',
      'logic' => 'logic',
      'data' => 'data',
      'helper' => 'helpers',
      'tool' => 'tools',
      'vectorstore' => 'vectorstores',
      'embedding' => 'embeddings',
      'memory' => 'memories',
      'agent' => 'agents',
      'bundle' => 'bundles',
    ];

    return $category_mapping[$category] ?? $category;
  }

  /**
   * Convert to node definition format.
   *
   * @return array
   *   The node definition array.
   */
  public function toNodeDefinition(): array {
    return [
      'id' => $this->id,
      'name' => $this->label,
      'version' => $this->version,
      'description' => $this->description,
      'category' => $this->transformCategoryToPlural($this->category),
      'icon' => $this->icon,
      'color' => $this->color,
      'configSchema' => $this->config,
      'tags' => $this->tags,
      'enabled' => $this->enabled,
    ];
  }

}
