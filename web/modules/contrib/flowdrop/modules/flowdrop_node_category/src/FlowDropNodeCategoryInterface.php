<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_category;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a flowdrop node category entity type.
 */
interface FlowDropNodeCategoryInterface extends ConfigEntityInterface {

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

}
