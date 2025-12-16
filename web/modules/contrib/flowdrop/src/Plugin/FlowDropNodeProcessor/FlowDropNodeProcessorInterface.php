<?php

declare(strict_types=1);

namespace Drupal\flowdrop\Plugin\FlowDropNodeProcessor;

/**
 * Interface for FlowDropNodeProcessor plugins.
 */
interface FlowDropNodeProcessorInterface extends NodeExecutorInterface {

  /**
   * Get the node type.
   *
   * This differs from "category" in the sense that
   * category is used for visual organization. Think of category
   * like "bundles".
   *
   * The node type has implication on how it behaves.
   *
   * @return string
   *   The node type ("note", "default", etc.).
   */
  public function getType(): string;

  /**
   * Get the component ID.
   *
   * @return string
   *   The unique component identifier.
   */
  public function getId(): string;

  /**
   * Get the component name.
   *
   * @return string
   *   The human-readable component name.
   */
  public function getName(): string;

  /**
   * Get the component description.
   *
   * @return string
   *   The component description.
   */
  public function getDescription(): string;

  /**
   * Get the component category.
   *
   * @return string
   *   The component category (e.g., "inputs", "models", "tools").
   */
  public function getCategory(): string;

  /**
   * Get the component version.
   *
   * @return string
   *   The component version.
   */
  public function getVersion(): string;

  /**
   * Get the component tags.
   *
   * @return array
   *   The component tags.
   */
  public function getTags(): array;

  /**
   * Validate inputs for the node.
   *
   * @param array $inputs
   *   The inputs to validate.
   *
   * @return bool
   *   TRUE if inputs are valid.
   */
  public function validateInputs(array $inputs): bool;

  /**
   * Get the output schema for this node.
   *
   * @return array
   *   The output schema.
   */
  public function getOutputSchema(): array;

  /**
   * Get the input schema for this node.
   *
   * @return array
   *   The input schema.
   */
  public function getInputSchema(): array;

  /**
   * Get the config schema for this node.
   *
   * @return array
   *   The config schema.
   */
  public function getConfigSchema(): array;

}
