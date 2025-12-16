<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Interface for configuration data transfer objects.
 *
 * This interface provides specialized methods for handling configuration data
 * in the FlowDrop system, including type validation and schema validation.
 */
interface ConfigInterface extends DataInterface {

  /**
   * Get configuration value with type validation.
   *
   * @param string $key
   *   The configuration key.
   * @param mixed $default
   *   The default value.
   * @param string|null $expected_type
   *   The expected type (string, int, bool, array, etc.).
   *
   * @return mixed
   *   The configuration value.
   *
   * @throws \InvalidArgumentException
   *   When type validation fails.
   */
  public function getConfig(string $key, mixed $default = NULL, ?string $expected_type = NULL): mixed;

  /**
   * Get required configuration value.
   *
   * @param string $key
   *   The configuration key.
   * @param string|null $expected_type
   *   The expected type.
   *
   * @return mixed
   *   The configuration value.
   *
   * @throws \InvalidArgumentException
   *   When the configuration is missing or invalid.
   */
  public function getRequiredConfig(string $key, ?string $expected_type = NULL): mixed;

  /**
   * Validate configuration against a schema.
   *
   * @param array $schema
   *   The configuration schema.
   *
   * @return bool
   *   TRUE if configuration is valid.
   */
  public function validateSchema(array $schema): bool;

  /**
   * Get configuration as a merged array with defaults.
   *
   * @param array $defaults
   *   Default configuration values.
   *
   * @return array
   *   Merged configuration with defaults.
   */
  public function getWithDefaults(array $defaults): array;

  /**
   * Check if configuration has a specific key.
   *
   * @param string $key
   *   The configuration key.
   *
   * @return bool
   *   TRUE if the key exists.
   */
  public function hasConfig(string $key): bool;

  /**
   * Get configuration value as a specific type.
   *
   * @param string $key
   *   The configuration key.
   * @param string $type
   *   The expected type.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The typed configuration value.
   *
   * @throws \InvalidArgumentException
   *   When type validation fails.
   */
  public function getTypedConfig(string $key, string $type, mixed $default = NULL): mixed;

  /**
   * Get all configuration as an associative array.
   *
   * @return array
   *   All configuration as an array.
   */
  public function getAllConfig(): array;

  /**
   * Check if configuration has any data.
   *
   * @return bool
   *   TRUE if configuration has data.
   */
  public function hasConfigData(): bool;

  /**
   * Get configuration count.
   *
   * @return int
   *   Number of configuration fields.
   */
  public function getConfigCount(): int;

  /**
   * Merge configuration with another config object.
   *
   * @param \Drupal\flowdrop\DTO\ConfigInterface $other
   *   The other configuration object.
   *
   * @return static
   *   The merged configuration object.
   */
  public function mergeConfig(ConfigInterface $other): static;

}
