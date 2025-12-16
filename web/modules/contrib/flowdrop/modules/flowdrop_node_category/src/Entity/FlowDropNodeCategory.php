<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_category\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop_node_category\FlowDropNodeCategoryInterface;
use Drupal\flowdrop_node_category\FlowDropNodeCategoryListBuilder;
use Drupal\flowdrop_node_category\Form\FlowDropNodeCategoryForm;

/**
 * Defines the flowdrop node category entity type.
 */
#[ConfigEntityType(
  id: 'flowdrop_node_category',
  label: new TranslatableMarkup('FlowDrop Node Category'),
  label_collection: new TranslatableMarkup('FlowDrop Node Categories'),
  label_singular: new TranslatableMarkup('flowdrop node category'),
  label_plural: new TranslatableMarkup('flowdrop node categories'),
  config_prefix: 'flowdrop_node_category',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => FlowDropNodeCategoryListBuilder::class,
    'form' => [
      'add' => FlowDropNodeCategoryForm::class,
      'edit' => FlowDropNodeCategoryForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/flowdrop-node-category',
    'add-form' => '/admin/structure/flowdrop-node-category/add',
    'edit-form' => '/admin/structure/flowdrop-node-category/{flowdrop_node_category}',
    'delete-form' => '/admin/structure/flowdrop-node-category/{flowdrop_node_category}/delete',
  ],
  admin_permission: 'administer flowdrop_node_category',
  label_count: [
    'singular' => '@count flowdrop node category',
    'plural' => '@count flowdrop node categories',
  ],
  config_export: [
    'id',
    'label',
    'description',
    'icon',
    'color',
    'enabled',
  ],
)]
final class FlowDropNodeCategory extends ConfigEntityBase implements FlowDropNodeCategoryInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description = '';

  /**
   * The category icon.
   */
  protected string $icon = 'mdi:cog';

  /**
   * The category color.
   */
  protected string $color = '#007cba';

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

}
