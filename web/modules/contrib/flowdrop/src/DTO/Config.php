<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Config DTO for the flowdrop module.
 *
 * This class represents configuration data for node processors,
 * providing specialized methods for configuration handling.
 */
class Config extends Data implements ConfigInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfig(string $key, mixed $default = NULL, ?string $expected_type = NULL): mixed {
    $value = $this->get($key, $default);

    if ($expected_type !== NULL && $value !== NULL) {
      $actual_type = gettype($value);
      if ($actual_type !== $expected_type) {
        throw new \InvalidArgumentException("Configuration key '{$key}' expects type '{$expected_type}', got '{$actual_type}'");
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredConfig(string $key, ?string $expected_type = NULL): mixed {
    if (!$this->has($key)) {
      throw new \InvalidArgumentException("Required configuration key '{$key}' is missing");
    }

    return $this->getConfig($key, NULL, $expected_type);
  }

  /**
   * {@inheritdoc}
   */
  public function validateSchema(array $schema): bool {
    // Basic schema validation - can be extended for more complex validation.
    foreach ($schema as $key => $rules) {
      if (isset($rules['required']) && $rules['required'] && !$this->has($key)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWithDefaults(array $defaults): array {
    return array_merge($defaults, $this->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function hasConfig(string $key): bool {
    return $this->has($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getTypedConfig(string $key, string $type, mixed $default = NULL): mixed {
    return $this->getConfig($key, $default, $type);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllConfig(): array {
    return $this->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function hasConfigData(): bool {
    return !$this->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigCount(): int {
    return count($this->keys());
  }

  /**
   * {@inheritdoc}
   */
  public function mergeConfig(ConfigInterface $other): static {
    $this->data = array_merge($this->data, $other->toArray());
    return $this;
  }

}
