<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Exception;

/**
 * Exception thrown when workflow-to-agent mapping fails.
 */
class MappingException extends \RuntimeException {

  /**
   * The mapping context where the error occurred.
   *
   * @var string
   */
  protected string $context;

  /**
   * Constructs a MappingException.
   *
   * @param string $message
   *   The exception message.
   * @param string $context
   *   The mapping context (e.g., 'node_to_agent', 'tool_extraction').
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   The previous exception.
   */
  public function __construct(
    string $message,
    string $context = '',
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
    $this->context = $context;
  }

  /**
   * Gets the mapping context.
   *
   * @return string
   *   The context string.
   */
  public function getContext(): string {
    return $this->context;
  }

}
