<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Interface for output data transfer objects.
 *
 * This interface provides specialized methods for handling output data
 * in the FlowDrop system, including status management and metadata handling.
 */
interface OutputInterface extends DataInterface {

  /**
   * Set output data with validation.
   *
   * @param string $key
   *   The output key.
   * @param mixed $value
   *   The output value.
   * @param callable|null $validator
   *   Optional validation function.
   *
   * @return static
   *   The output object for method chaining.
   *
   * @throws \InvalidArgumentException
   *   When validation fails.
   */
  public function setOutput(string $key, mixed $value, ?callable $validator = NULL): static;

  /**
   * Add metadata to the output.
   *
   * @param string $key
   *   The metadata key.
   * @param mixed $value
   *   The metadata value.
   *
   * @return static
   *   The output object for method chaining.
   */
  public function addMetadata(string $key, mixed $value): static;

  /**
   * Get metadata from the output.
   *
   * @param string $key
   *   The metadata key.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The metadata value.
   */
  public function getMetadata(string $key, mixed $default = NULL): mixed;

  /**
   * Set execution status.
   *
   * @param string $status
   *   The execution status (success, error, warning, etc.).
   * @param string|null $message
   *   Optional status message.
   *
   * @return static
   *   The output object for method chaining.
   */
  public function setStatus(string $status, ?string $message = NULL): static;

  /**
   * Get execution status.
   *
   * @return string
   *   The execution status.
   */
  public function getStatus(): string;

  /**
   * Check if the output indicates success.
   *
   * @return bool
   *   TRUE if the output indicates success.
   */
  public function isSuccess(): bool;

  /**
   * Check if the output indicates an error.
   *
   * @return bool
   *   TRUE if the output indicates an error.
   */
  public function isError(): bool;

  /**
   * Set error information.
   *
   * @param string $message
   *   The error message.
   * @param string|null $code
   *   Optional error code.
   *
   * @return static
   *   The output object for method chaining.
   */
  public function setError(string $message, ?string $code = NULL): static;

  /**
   * Get error message.
   *
   * @return string|null
   *   The error message or NULL if no error.
   */
  public function getErrorMessage(): ?string;

  /**
   * Get error code.
   *
   * @return string|null
   *   The error code or NULL if no error.
   */
  public function getErrorCode(): ?string;

  /**
   * Set success information.
   *
   * @param string|null $message
   *   Optional success message.
   *
   * @return static
   *   The output object for method chaining.
   */
  public function setSuccess(?string $message = NULL): static;

  /**
   * Get success message.
   *
   * @return string|null
   *   The success message or NULL if no message.
   */
  public function getSuccessMessage(): ?string;

  /**
   * Add execution timing information.
   *
   * @param float $execution_time
   *   The execution time in seconds.
   *
   * @return static
   *   The output object for method chaining.
   */
  public function addExecutionTime(float $execution_time): static;

  /**
   * Get execution time.
   *
   * @return float|null
   *   The execution time in seconds or NULL if not set.
   */
  public function getExecutionTime(): ?float;

  /**
   * Get all output data as an associative array.
   *
   * @return array
   *   All output data as an array.
   */
  public function getAllOutput(): array;

  /**
   * Check if output has any data.
   *
   * @return bool
   *   TRUE if output has data.
   */
  public function hasOutputData(): bool;

  /**
   * Get output count.
   *
   * @return int
   *   Number of output fields.
   */
  public function getOutputCount(): int;

  /**
   * Merge output with another output object.
   *
   * @param \Drupal\flowdrop\DTO\OutputInterface $other
   *   The other output object.
   *
   * @return static
   *   The merged output object.
   */
  public function mergeOutput(OutputInterface $other): static;

}
