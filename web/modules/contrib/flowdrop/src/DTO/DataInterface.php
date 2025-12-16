<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Interface for data transfer objects.
 *
 * This interface provides a standardized way to handle data
 * in the FlowDrop system, ensuring proper serialization and
 * type safety.
 */
interface DataInterface {

  /**
   * Get a value from the data object.
   *
   * @param string $key
   *   The key to retrieve.
   * @param mixed $default
   *   The default value if key doesn't exist.
   *
   * @return mixed
   *   The value for the key or default.
   */
  public function get(string $key, mixed $default = NULL): mixed;

  /**
   * Set a value in the data object.
   *
   * @param string $key
   *   The key to set.
   * @param mixed $value
   *   The value to set.
   *
   * @return static
   *   The data object for method chaining.
   */
  public function set(string $key, mixed $value): static;

  /**
   * Check if a key exists in the data object.
   *
   * @param string $key
   *   The key to check.
   *
   * @return bool
   *   TRUE if the key exists.
   */
  public function has(string $key): bool;

  /**
   * Remove a key from the data object.
   *
   * @param string $key
   *   The key to remove.
   *
   * @return static
   *   The data object for method chaining.
   */
  public function remove(string $key): static;

  /**
   * Get all data as an array.
   *
   * @return array
   *   The data as an array.
   */
  public function toArray(): array;

  /**
   * Set data from an array.
   *
   * @param array $data
   *   The data array.
   *
   * @return static
   *   The data object for method chaining.
   */
  public function fromArray(array $data): static;

  /**
   * Get all keys in the data object.
   *
   * @return array
   *   Array of keys.
   */
  public function keys(): array;

  /**
   * Check if the data object is empty.
   *
   * @return bool
   *   TRUE if the data object is empty.
   */
  public function isEmpty(): bool;

  /**
   * Clear all data from the object.
   *
   * @return static
   *   The data object for method chaining.
   */
  public function clear(): static;

  /**
   * Merge data from another DataInterface object.
   *
   * @param \Drupal\flowdrop\DTO\DataInterface $other
   *   The other data object to merge from.
   *
   * @return static
   *   The data object for method chaining.
   */
  public function merge(DataInterface $other): static;

}
