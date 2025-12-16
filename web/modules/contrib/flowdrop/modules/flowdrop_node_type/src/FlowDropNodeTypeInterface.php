<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_type;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a flowdrop node type entity type.
 */
interface FlowDropNodeTypeInterface extends ConfigEntityInterface {

  /**
   * Get the label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Set the label.
   *
   * @param string $label
   *   The label.
   *
   * @return static
   *   The node entity.
   */
  public function setLabel(string $label): static;

  /**
   * Get the description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string;

  /**
   * Set the description.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   *   The node entity.
   */
  public function setDescription(string $description): static;

  /**
   * Get the category.
   *
   * @return string
   *   The category.
   */
  public function getCategory(): string;

  /**
   * Set the category.
   *
   * @param string $category
   *   The category.
   *
   * @return static
   *   The node entity.
   */
  public function setCategory(string $category): static;

  /**
   * Get the icon.
   *
   * @return string
   *   The icon.
   */
  public function getIcon(): string;

  /**
   * Set the icon.
   *
   * @param string $icon
   *   The icon.
   *
   * @return static
   *   The node entity.
   */
  public function setIcon(string $icon): static;

  /**
   * Get the color.
   *
   * @return string
   *   The color.
   */
  public function getColor(): string;

  /**
   * Set the color.
   *
   * @param string $color
   *   The color.
   *
   * @return static
   *   The node entity.
   */
  public function setColor(string $color): static;

  /**
   * Get the version.
   *
   * @return string
   *   The version.
   */
  public function getVersion(): string;

  /**
   * Set the version.
   *
   * @param string $version
   *   The version.
   *
   * @return static
   *   The node entity.
   */
  public function setVersion(string $version): static;

  /**
   * Check if the node type is enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function isEnabled(): bool;

  /**
   * Set the enabled status.
   *
   * @param bool $enabled
   *   The enabled status.
   *
   * @return static
   *   The node entity.
   */
  public function setEnabled(bool $enabled): static;

  /**
   * Get the configuration schema.
   *
   * @return array
   *   The configuration schema.
   */
  public function getConfig(): array;

  /**
   * Set the configuration schema.
   *
   * @param array $config
   *   The configuration schema.
   *
   * @return static
   *   The node entity.
   */
  public function setConfig(array $config): static;

  /**
   * Get the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags(): array;

  /**
   * Set the tags.
   *
   * @param array $tags
   *   The tags.
   *
   * @return static
   *   The node entity.
   */
  public function setTags(array $tags): static;

  /**
   * Get the executor plugin ID.
   *
   * @return string
   *   The executor plugin ID.
   */
  public function getExecutorPlugin(): string;

  /**
   * Set the executor plugin ID.
   *
   * @param string $executor_plugin
   *   The executor plugin ID.
   *
   * @return static
   *   The node entity.
   */
  public function setExecutorPlugin(string $executor_plugin): static;

  /**
   * Convert to node definition format.
   *
   * @return array
   *   The node definition array.
   */
  public function toNodeDefinition(): array;

}
