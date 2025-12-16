<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception thrown when agent data validation fails.
 *
 * @see \Drupal\ai_integration_eca_agents\Exception\EntityViolationException
 */
class ValidationException extends \RuntimeException {

  /**
   * The validation violations.
   *
   * @var \Symfony\Component\Validator\ConstraintViolationListInterface|null
   */
  protected ?ConstraintViolationListInterface $violations;

  /**
   * Constructs a ValidationException.
   *
   * @param string $message
   *   The exception message.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface|null $violations
   *   The constraint violations.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous exception.
   */
  public function __construct(
    string $message,
    ?ConstraintViolationListInterface $violations = NULL,
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
    $this->violations = $violations;
  }

  /**
   * Gets the constraint violations.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface|null
   *   The violations, or NULL if not available.
   */
  public function getViolations(): ?ConstraintViolationListInterface {
    return $this->violations;
  }

  /**
   * Formats violation messages as a human-readable string.
   *
   * @return string
   *   Formatted violation messages.
   */
  public function formatViolations(): string {
    if ($this->violations === NULL || $this->violations->count() === 0) {
      return $this->getMessage();
    }

    $messages = [];
    foreach ($this->violations as $violation) {
      $path = $violation->getPropertyPath();
      $message = $violation->getMessage();
      $messages[] = $path ? "$path: $message" : $message;
    }

    return implode("\n", $messages);
  }

  /**
   * Creates a ValidationException from constraint violations.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The constraint violations.
   *
   * @return static
   *   The exception instance.
   */
  public static function fromViolations(ConstraintViolationListInterface $violations): static {
    $messages = [];
    foreach ($violations as $violation) {
      $path = $violation->getPropertyPath();
      $message = $violation->getMessage();
      $messages[] = $path ? "$path: $message" : $message;
    }

    $formattedMessage = 'Validation failed: ' . implode('; ', $messages);
    return new static($formattedMessage, $violations);
  }

  /**
   * Creates a ValidationException from error message strings.
   *
   * @param array $messages
   *   Array of error message strings.
   *
   * @return static
   *   The exception instance.
   */
  public static function fromMessages(array $messages): static {
    $formattedMessage = 'Validation failed: ' . implode('; ', $messages);
    return new static($formattedMessage);
  }

}
