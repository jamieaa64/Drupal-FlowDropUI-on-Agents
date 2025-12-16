<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\ErrorHandling;

use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\flowdrop_runtime\Exception\RuntimeException;
use Drupal\flowdrop_runtime\Exception\DataFlowException;
use Drupal\flowdrop_runtime\Exception\CompilationException;
use Drupal\flowdrop_runtime\Exception\OrchestrationException;

/**
 * Handles errors during workflow execution.
 */
class ErrorHandler {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  /**
   * Error recovery strategies.
   *
   * @var array<string, callable>
   */
  private array $recoveryStrategies = [];

  /**
   * Error categorization rules.
   *
   * @var array<string, array>
   */
  private array $errorCategories = [];

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
    $this->initializeErrorCategories();
    $this->initializeRecoveryStrategies();
  }

  /**
   * Handle execution error with categorization and recovery.
   *
   * @param \Exception $error
   *   The error to handle.
   * @param array $context
   *   Additional context for error handling.
   *
   * @return array
   *   Error handling result with category, severity, and recovery info.
   */
  public function handleError(\Exception $error, array $context = []): array {
    $errorInfo = $this->categorizeError($error);
    $severity = $this->determineSeverity($error, $errorInfo);
    $recoveryAction = $this->attemptRecovery($error, $errorInfo, $context);

    $this->logger->error('Workflow execution error: @error', [
      '@error' => $error->getMessage(),
      'category' => $errorInfo['category'],
      'severity' => $severity,
      'context' => $context,
      'recovery_action' => $recoveryAction['action'],
    ]);

    // Dispatch error event for other modules to listen to.
    $event = new GenericEvent($error, [
      'error' => $error->getMessage(),
      'category' => $errorInfo['category'],
      'severity' => $severity,
      'context' => $context,
      'recovery_action' => $recoveryAction,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.execution.error');

    return [
      'category' => $errorInfo['category'],
      'severity' => $severity,
      'recovery_action' => $recoveryAction,
      'error_message' => $error->getMessage(),
      'error_trace' => $error->getTraceAsString(),
      'confidence' => $errorInfo['confidence'] ?? 1.0,
    ];
  }

  /**
   * Handle multiple errors in batch.
   *
   * @param array $errors
   *   Array of errors to handle.
   * @param array $context
   *   Additional context for error handling.
   *
   * @return array
   *   Batch error handling results.
   */
  public function handleBatchErrors(array $errors, array $context = []): array {
    $this->logger->info('Handling batch of @count errors', [
      '@count' => count($errors),
    ]);

    $results = [];
    $criticalErrors = [];

    foreach ($errors as $index => $error) {
      $result = $this->handleError($error, array_merge($context, ['error_index' => $index]));
      $results[] = $result;

      if ($result['severity'] === 'critical') {
        $criticalErrors[] = $result;
      }
    }

    // Dispatch batch error event.
    $event = new GenericEvent($errors, [
      'results' => $results,
      'critical_errors_count' => count($criticalErrors),
      'context' => $context,
      'timestamp' => time(),
    ]);
    $this->eventDispatcher->dispatch($event, 'flowdrop_runtime.execution.batch_error');

    return [
      'results' => $results,
      'critical_errors' => $criticalErrors,
      'total_errors' => count($errors),
      'critical_count' => count($criticalErrors),
    ];
  }

  /**
   * Register a custom error recovery strategy.
   *
   * @param string $category
   *   The error category this strategy applies to.
   * @param callable $strategy
   *   The recovery strategy function.
   */
  public function registerRecoveryStrategy(string $category, callable $strategy): void {
    $this->recoveryStrategies[$category] = $strategy;
    $this->logger->debug('Registered recovery strategy for category: @category', [
      '@category' => $category,
    ]);
  }

  /**
   * Register a custom error category.
   *
   * @param string $category
   *   The category name.
   * @param array $rules
   *   The categorization rules.
   */
  public function registerErrorCategory(string $category, array $rules): void {
    $this->errorCategories[$category] = $rules;
    $this->logger->debug('Registered error category: @category', [
      '@category' => $category,
    ]);
  }

  /**
   * Get error statistics for monitoring.
   *
   * @param array $filters
   *   Optional filters for statistics.
   *
   * @return array
   *   Error statistics.
   */
  public function getErrorStatistics(array $filters = []): array {
    // This would typically query a database or cache for error statistics.
    // For now, return a placeholder structure.
    return [
      'total_errors' => 0,
      'errors_by_category' => [],
      'errors_by_severity' => [],
      'recovery_success_rate' => 0.0,
      'average_resolution_time' => 0,
    ];
  }

  /**
   * Categorize an error based on its type and message.
   *
   * @param \Exception $error
   *   The error to categorize.
   *
   * @return array
   *   Error categorization information.
   */
  private function categorizeError(\Exception $error): array {
    $errorClass = get_class($error);
    $errorMessage = $error->getMessage();

    // Check custom registered categories first (highest priority).
    foreach ($this->errorCategories as $category => $rules) {
      $matchResult = $this->findMatchingRule($error, $rules);
      if ($matchResult !== NULL) {
        return [
          'category' => $category,
          'rules_matched' => $rules,
          'confidence' => $matchResult['confidence'],
        ];
      }
    }

    // Check specific FlowDrop exception types (but not generic exceptions).
    $exceptionTypeResult = match ($errorClass) {
      DataFlowException::class => [
        'category' => 'data_flow',
        'description' => 'Data flow validation or transformation error',
        'confidence' => 1.0,
      ],
      CompilationException::class => [
        'category' => 'compilation',
        'description' => 'Workflow compilation error',
        'confidence' => 1.0,
      ],
      OrchestrationException::class => [
        'category' => 'orchestration',
        'description' => 'Workflow orchestration error',
        'confidence' => 1.0,
      ],
      RuntimeException::class => [
        'category' => 'runtime',
        'description' => 'General runtime error',
        'confidence' => 1.0,
      ],
      default => NULL,
    };

    // If we have a specific FlowDrop exception type, return that.
    if ($exceptionTypeResult !== NULL) {
      return $exceptionTypeResult;
    }

    // Final fallback for generic exceptions and truly unknown errors.
    return [
      'category' => 'unknown',
      'description' => 'Unknown error type',
      'confidence' => 0.5,
    ];
  }

  /**
   * Determine error severity.
   *
   * @param \Exception $error
   *   The error to evaluate.
   * @param array $errorInfo
   *   The error categorization information.
   *
   * @return string
   *   The severity level (low, medium, high, critical).
   */
  private function determineSeverity(\Exception $error, array $errorInfo): string {
    $category = $errorInfo['category'];

    // Category-based severity mapping.
    $categorySeverity = [
      'data_flow' => 'medium',
      'compilation' => 'high',
      'orchestration' => 'high',
      'runtime' => 'medium',
      'unknown' => 'medium',
    ];

    $baseSeverity = $categorySeverity[$category] ?? 'medium';

    // Adjust severity based on error message patterns.
    $message = strtolower($error->getMessage());

    if (str_contains($message, 'fatal') || str_contains($message, 'critical')) {
      return 'critical';
    }

    if (str_contains($message, 'warning') || str_contains($message, 'deprecated')) {
      return 'low';
    }

    return $baseSeverity;
  }

  /**
   * Attempt to recover from an error.
   *
   * @param \Exception $error
   *   The error to recover from.
   * @param array $errorInfo
   *   The error categorization information.
   * @param array $context
   *   Additional context for recovery.
   *
   * @return array
   *   Recovery attempt result.
   */
  private function attemptRecovery(\Exception $error, array $errorInfo, array $context): array {
    $category = $errorInfo['category'];

    // Check if we have a custom recovery strategy for this category.
    if (isset($this->recoveryStrategies[$category])) {
      try {
        $result = call_user_func($this->recoveryStrategies[$category], $error, $context);
        return [
          'action' => 'custom_strategy',
          'success' => $result['success'] ?? FALSE,
          'message' => $result['message'] ?? 'Custom recovery attempted',
          'data' => $result['data'] ?? [],
        ];
      }
      catch (\Exception $recoveryError) {
        $this->logger->error('Recovery strategy failed: @error', [
          '@error' => $recoveryError->getMessage(),
        ]);
      }
    }

    // Default recovery strategies.
    return match ($category) {
      'data_flow' => $this->attemptDataFlowRecovery($error, $context),
      'compilation' => $this->attemptCompilationRecovery($error, $context),
      'orchestration' => $this->attemptOrchestrationRecovery($error, $context),
      'runtime' => $this->attemptRuntimeRecovery($error, $context),
      default => [
        'action' => 'none',
        'success' => FALSE,
        'message' => 'No recovery strategy available',
        'data' => [],
      ],
    };
  }

  /**
   * Attempt data flow error recovery.
   *
   * @param \Exception $error
   *   The error to recover from.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Recovery result.
   */
  private function attemptDataFlowRecovery(\Exception $error, array $context): array {
    // Try to provide default values for missing fields.
    if (str_contains($error->getMessage(), 'Missing required fields')) {
      return [
        'action' => 'provide_defaults',
        'success' => TRUE,
        'message' => 'Attempting to provide default values for missing fields',
        'data' => ['strategy' => 'default_values'],
      ];
    }

    // Try to transform data types.
    if (str_contains($error->getMessage(), 'Data type validation failed')) {
      return [
        'action' => 'type_conversion',
        'success' => TRUE,
        'message' => 'Attempting to convert data types',
        'data' => ['strategy' => 'type_conversion'],
      ];
    }

    return [
      'action' => 'none',
      'success' => FALSE,
      'message' => 'No specific data flow recovery available',
      'data' => [],
    ];
  }

  /**
   * Attempt compilation error recovery.
   *
   * @param \Exception $error
   *   The error to recover from.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Recovery result.
   */
  private function attemptCompilationRecovery(\Exception $error, array $context): array {
    // Try to fix common compilation issues.
    if (stripos($error->getMessage(), 'missing node') !== FALSE) {
      return [
        'action' => 'skip_missing_nodes',
        'success' => TRUE,
        'message' => 'Attempting to skip missing nodes',
        'data' => ['strategy' => 'skip_missing'],
      ];
    }

    return [
      'action' => 'none',
      'success' => FALSE,
      'message' => 'Compilation errors require manual intervention',
      'data' => [],
    ];
  }

  /**
   * Attempt orchestration error recovery.
   *
   * @param \Exception $error
   *   The error to recover from.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Recovery result.
   */
  private function attemptOrchestrationRecovery(\Exception $error, array $context): array {
    // Try to retry failed nodes.
    if (stripos($error->getMessage(), 'node execution failed') !== FALSE) {
      return [
        'action' => 'retry_failed_nodes',
        'success' => TRUE,
        'message' => 'Attempting to retry failed nodes',
        'data' => ['strategy' => 'retry', 'max_retries' => 3],
      ];
    }

    return [
      'action' => 'none',
      'success' => FALSE,
      'message' => 'Orchestration errors require manual intervention',
      'data' => [],
    ];
  }

  /**
   * Attempt runtime error recovery.
   *
   * @param \Exception $error
   *   The error to recover from.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Recovery result.
   */
  private function attemptRuntimeRecovery(\Exception $error, array $context): array {
    // Try to restart the execution context.
    return [
      'action' => 'restart_context',
      'success' => TRUE,
      'message' => 'Attempting to restart execution context',
      'data' => ['strategy' => 'context_restart'],
    ];
  }

  /**
   * Find the first matching rule and calculate its confidence.
   *
   * @param \Exception $error
   *   The error to check.
   * @param array $rules
   *   The categorization rules.
   *
   * @return array|null
   *   The match result with confidence, or NULL if no match.
   */
  private function findMatchingRule(\Exception $error, array $rules): ?array {
    foreach ($rules as $rule) {
      $criteriaChecked = 0;
      $criteriaMet = 0;

      if (isset($rule['class'])) {
        $criteriaChecked++;
        if (get_class($error) === $rule['class']) {
          $criteriaMet++;
        }
      }

      if (isset($rule['message_pattern'])) {
        $criteriaChecked++;
        if (preg_match($rule['message_pattern'], $error->getMessage())) {
          $criteriaMet++;
        }
      }

      if (isset($rule['code'])) {
        $criteriaChecked++;
        if ($error->getCode() === $rule['code']) {
          $criteriaMet++;
        }
      }

      // If all criteria for this rule are met, return success.
      if ($criteriaChecked > 0 && $criteriaMet === $criteriaChecked) {
        return [
          'rule' => $rule,
          'confidence' => 1.0,
        ];
      }
    }

    return NULL;
  }

  /**
   * Initialize default error categories.
   */
  private function initializeErrorCategories(): void {
    $this->errorCategories = [
      'validation' => [
        [
          'message_pattern' => '/validation/i',
          'description' => 'Data validation errors',
        ],
      ],
      'timeout' => [
        [
          'message_pattern' => '/timeout/i',
          'description' => 'Execution timeout errors',
        ],
      ],
      'resource' => [
        [
          'message_pattern' => '/memory|disk|resource/i',
          'description' => 'Resource-related errors',
        ],
      ],
    ];
  }

  /**
   * Initialize default recovery strategies.
   */
  private function initializeRecoveryStrategies(): void {
    // Register default recovery strategies.
    $this->recoveryStrategies['timeout'] = function (\Exception $error, array $context) {
      return [
        'success' => TRUE,
        'message' => 'Attempting to increase timeout limits',
        'data' => ['strategy' => 'increase_timeout'],
      ];
    };

    $this->recoveryStrategies['resource'] = function (\Exception $error, array $context) {
      return [
        'success' => TRUE,
        'message' => 'Attempting to free up resources',
        'data' => ['strategy' => 'resource_cleanup'],
      ];
    };
  }

}
