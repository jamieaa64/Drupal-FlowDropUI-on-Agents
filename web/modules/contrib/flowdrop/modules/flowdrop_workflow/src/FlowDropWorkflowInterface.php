<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a FlowDrop workflow entity type.
 */
interface FlowDropWorkflowInterface extends ConfigEntityInterface {

  /**
   * Gets the workflow label.
   *
   * @return string
   *   The workflow label.
   */
  public function getLabel(): string;

  /**
   * Sets the workflow label.
   *
   * @param string $label
   *   The workflow label.
   *
   * @return static
   *   The workflow entity.
   */
  public function setLabel(string $label): static;

  /**
   * Gets the workflow description.
   *
   * @return string
   *   The workflow description.
   */
  public function getDescription(): string;

  /**
   * Sets the workflow description.
   *
   * @param string $description
   *   The workflow description.
   *
   * @return static
   *   The workflow entity.
   */
  public function setDescription(string $description): static;

  /**
   * Gets the workflow nodes.
   *
   * @return array
   *   The workflow nodes.
   */
  public function getNodes(): array;

  /**
   * Sets the workflow nodes.
   *
   * @param array $nodes
   *   The workflow nodes.
   *
   * @return static
   *   The workflow entity.
   */
  public function setNodes(array $nodes): static;

  /**
   * Gets the workflow edges.
   *
   * @return array
   *   The workflow edges.
   */
  public function getEdges(): array;

  /**
   * Sets the workflow edges.
   *
   * @param array $edges
   *   The workflow edges.
   *
   * @return static
   *   The workflow entity.
   */
  public function setEdges(array $edges): static;

  /**
   * Gets the workflow metadata.
   *
   * @return array
   *   The workflow metadata.
   */
  public function getMetadata(): array;

  /**
   * Sets the workflow metadata.
   *
   * @param array $metadata
   *   The workflow metadata.
   *
   * @return static
   *   The workflow entity.
   */
  public function setMetadata(array $metadata): static;

  /**
   * Gets the creation timestamp.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreated(): int;

  /**
   * Sets the creation timestamp.
   *
   * @param int $created
   *   The creation timestamp.
   *
   * @return static
   *   The workflow entity.
   */
  public function setCreated(int $created): static;

  /**
   * Gets the last modified timestamp.
   *
   * @return int
   *   The last modified timestamp.
   */
  public function getChanged(): int;

  /**
   * Sets the last modified timestamp.
   *
   * @param int $changed
   *   The last modified timestamp.
   *
   * @return static
   *   The workflow entity.
   */
  public function setChanged(int $changed): static;

  /**
   * Gets the user ID who created the workflow.
   *
   * @return int
   *   The user ID.
   */
  public function getUid(): int;

  /**
   * Sets the user ID who created the workflow.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return static
   *   The workflow entity.
   */
  public function setUid(int $uid): static;

}
