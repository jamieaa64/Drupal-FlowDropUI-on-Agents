<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Input DTO for the flowdrop module.
 *
 * This class represents input data for node processors,
 * providing specialized methods for input handling.
 */
class Input extends Data implements InputInterface {

  /**
   * {@inheritdoc}
   */
  public function getInput(string $key, mixed $default = NULL, ?callable $validator = NULL): mixed {
    $value = $this->get($key, $default);

    if ($validator !== NULL && !$validator($value)) {
      throw new \InvalidArgumentException("Invalid input value for key: {$key}");
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRequiredInputs(array $required_keys): bool {
    foreach ($required_keys as $key) {
      if (!$this->has($key)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMissingInputs(array $required_keys): array {
    $missing = [];
    foreach ($required_keys as $key) {
      if (!$this->has($key)) {
        $missing[] = $key;
      }
    }
    return $missing;
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
  public function getTypedInput(string $key, string $type, mixed $default = NULL): mixed {
    $value = $this->get($key, $default);

    if ($value !== NULL) {
      $actual_type = gettype($value);
      if ($actual_type !== $type) {
        throw new \InvalidArgumentException("Input key '{$key}' expects type '{$type}', got '{$actual_type}'");
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllInputs(): array {
    return $this->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function hasData(): bool {
    return !$this->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputCount(): int {
    return count($this->keys());
  }

}
