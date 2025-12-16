<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Interface for input data transfer objects.
 *
 * This interface provides specialized methods for handling input data
 * in the FlowDrop system, including validation and required field checking.
 */
interface InputInterface extends DataInterface {

  /**
   * Get input data with validation.
   *
   * @param string $key
   *   The input key.
   * @param mixed $default
   *   The default value.
   * @param callable|null $validator
   *   Optional validation function.
   *
   * @return mixed
   *   The validated input value.
   *
   * @throws \InvalidArgumentException
   *   When validation fails.
   */
  public function getInput(string $key, mixed $default = NULL, ?callable $validator = NULL): mixed;

  /**
   * Check if all required inputs are present.
   *
   * @param array $required_keys
   *   Array of required input keys.
   *
   * @return bool
   *   TRUE if all required inputs are present.
   */
  public function hasRequiredInputs(array $required_keys): bool;

  /**
   * Get missing required inputs.
   *
   * @param array $required_keys
   *   Array of required input keys.
   *
   * @return array
   *   Array of missing input keys.
   */
  public function getMissingInputs(array $required_keys): array;

  /**
   * Validate input against a schema.
   *
   * @param array $schema
   *   The input schema.
   *
   * @return bool
   *   TRUE if input is valid.
   */
  public function validateSchema(array $schema): bool;

  /**
   * Get input as a specific type with validation.
   *
   * @param string $key
   *   The input key.
   * @param string $type
   *   The expected type (string, int, bool, array, etc.).
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The typed input value.
   *
   * @throws \InvalidArgumentException
   *   When type validation fails.
   */
  public function getTypedInput(string $key, string $type, mixed $default = NULL): mixed;

  /**
   * Get all inputs as an associative array.
   *
   * @return array
   *   All inputs as an array.
   */
  public function getAllInputs(): array;

  /**
   * Check if input has any data.
   *
   * @return bool
   *   TRUE if input has data.
   */
  public function hasData(): bool;

  /**
   * Get input count.
   *
   * @return int
   *   Number of input fields.
   */
  public function getInputCount(): int;

}
