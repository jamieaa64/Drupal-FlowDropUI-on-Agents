<?php

declare(strict_types=1);

namespace Drupal\flowdrop\DTO;

/**
 * Output DTO for the flowdrop module.
 *
 * This class represents output data from node processors,
 * providing specialized methods for output handling.
 */
class Output extends Data implements OutputInterface {

  /**
   * {@inheritdoc}
   */
  public function setOutput(string $key, mixed $value, ?callable $validator = NULL): static {
    if ($validator !== NULL && !$validator($value)) {
      throw new \InvalidArgumentException("Invalid output value for key: {$key}");
    }

    return $this->set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function addMetadata(string $key, mixed $value): static {
    $metadata = $this->get('metadata', []);
    $metadata[$key] = $value;
    return $this->set('metadata', $metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(string $key, mixed $default = NULL): mixed {
    $metadata = $this->get('metadata', []);
    return $metadata[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status, ?string $message = NULL): static {
    $this->set('status', $status);
    if ($message !== NULL) {
      $this->set('status_message', $message);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return $this->get('status', 'unknown');
  }

  /**
   * {@inheritdoc}
   */
  public function isSuccess(): bool {
    return $this->getStatus() === 'success';
  }

  /**
   * {@inheritdoc}
   */
  public function isError(): bool {
    return $this->getStatus() === 'error';
  }

  /**
   * {@inheritdoc}
   */
  public function setError(string $message, ?string $code = NULL): static {
    $this->setStatus('error', $message);
    if ($code !== NULL) {
      $this->set('error_code', $code);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorMessage(): ?string {
    return $this->get('status_message');
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorCode(): ?string {
    return $this->get('error_code');
  }

  /**
   * {@inheritdoc}
   */
  public function setSuccess(?string $message = NULL): static {
    $this->setStatus('success', $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuccessMessage(): ?string {
    return $this->getStatus() === 'success' ? $this->get('status_message') : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addExecutionTime(float $execution_time): static {
    return $this->set('execution_time', $execution_time);
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionTime(): ?float {
    return $this->get('execution_time');
  }

  /**
   * {@inheritdoc}
   */
  public function getAllOutput(): array {
    return $this->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function hasOutputData(): bool {
    return !$this->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputCount(): int {
    return count($this->keys());
  }

  /**
   * {@inheritdoc}
   */
  public function mergeOutput(OutputInterface $other): static {
    $this->data = array_merge($this->data, $other->toArray());
    return $this;
  }

}
