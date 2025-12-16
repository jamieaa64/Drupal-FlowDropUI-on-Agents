<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Data DTO for the flowdrop module.
 *
 * This class provides a flexible data container that implements
 * the DataInterface for consistent data handling across the FlowDrop system.
 */
class Data implements DataInterface {

  /**
   * The internal data storage.
   *
   * @var array
   */
  protected array $data = [];

  /**
   * Constructs a new Data object.
   *
   * @param array $data
   *   Initial data array.
   */
  public function __construct(array $data = []) {
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $key, mixed $default = NULL): mixed {
    return $this->data[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $key, mixed $value): static {
    $this->data[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $key): bool {
    return array_key_exists($key, $this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(string $key): static {
    unset($this->data[$key]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(array $data): static {
    $this->data = $data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function keys(): array {
    return array_keys($this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(): static {
    $this->data = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function merge(DataInterface $other): static {
    $this->data = array_merge($this->data, $other->toArray());
    return $this;
  }

  /**
   * Magic getter for accessing data properties.
   *
   * @param string $name
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  public function __get(string $name): mixed {
    return $this->get($name);
  }

  /**
   * Magic setter for setting data properties.
   *
   * @param string $name
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  public function __set(string $name, mixed $value): void {
    $this->set($name, $value);
  }

  /**
   * Magic isset for checking data properties.
   *
   * @param string $name
   *   The property name.
   *
   * @return bool
   *   TRUE if the property exists.
   */
  public function __isset(string $name): bool {
    return $this->has($name);
  }

  /**
   * Magic unset for removing data properties.
   *
   * @param string $name
   *   The property name.
   */
  public function __unset(string $name): void {
    $this->remove($name);
  }

}
