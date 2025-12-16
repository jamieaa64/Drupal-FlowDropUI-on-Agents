<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Unit\Service\ErrorHandling;

use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\Tests\UnitTestCase;
use Drupal\flowdrop_runtime\Service\ErrorHandling\ErrorHandler;
use Drupal\flowdrop_runtime\Exception\RuntimeException;
use Drupal\flowdrop_runtime\Exception\DataFlowException;
use Drupal\flowdrop_runtime\Exception\CompilationException;
use Drupal\flowdrop_runtime\Exception\OrchestrationException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ErrorHandler.
 *
 * @coversDefaultClass \Drupal\flowdrop_runtime\Service\ErrorHandling\ErrorHandler
 * @group flowdrop_runtime
 */
class ErrorHandlerTest extends UnitTestCase {

  /**
   * The error handler service.
   *
   * @var \Drupal\flowdrop_runtime\Service\ErrorHandling\ErrorHandler
   */
  private ErrorHandler $errorHandler;

  /**
   * Mock logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Mock logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private LoggerInterface $logger;

  /**
   * Mock event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')->willReturn($this->logger);

    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->errorHandler = new ErrorHandler($this->loggerFactory, $this->eventDispatcher);
  }

  /**
   * Test error handling with runtime exception.
   *
   * @covers ::handleError
   */
  public function testHandleErrorRuntimeException(): void {
    $error = new RuntimeException('Test runtime error');
    $context = ['node_id' => 'test_node', 'execution_id' => 'test_execution'];

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Workflow execution error: @error');

    $this->eventDispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf(GenericEvent::class), 'flowdrop_runtime.execution.error');

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('runtime', $result['category']);
    $this->assertEquals('medium', $result['severity']);
    $this->assertEquals('Test runtime error', $result['error_message']);
    $this->assertArrayHasKey('recovery_action', $result);
  }

  /**
   * Test error handling with data flow exception.
   *
   * @covers ::handleError
   */
  public function testHandleErrorDataFlowException(): void {
    $error = new DataFlowException('Missing required fields: age, email');
    $context = ['node_id' => 'test_node'];

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('data_flow', $result['category']);
    $this->assertEquals('medium', $result['severity']);
    $this->assertEquals('provide_defaults', $result['recovery_action']['action']);
    $this->assertTrue($result['recovery_action']['success']);
  }

  /**
   * Test error handling with compilation exception.
   *
   * @covers ::handleError
   */
  public function testHandleErrorCompilationException(): void {
    $error = new CompilationException('Missing node: test_node');
    $context = ['workflow_id' => 'test_workflow'];

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('compilation', $result['category']);
    $this->assertEquals('high', $result['severity']);
    $this->assertEquals('skip_missing_nodes', $result['recovery_action']['action']);
    $this->assertTrue($result['recovery_action']['success']);
  }

  /**
   * Test error handling with orchestration exception.
   *
   * @covers ::handleError
   */
  public function testHandleErrorOrchestrationException(): void {
    $error = new OrchestrationException('Node execution failed: test_node');
    $context = ['execution_id' => 'test_execution'];

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('orchestration', $result['category']);
    $this->assertEquals('high', $result['severity']);
    $this->assertEquals('retry_failed_nodes', $result['recovery_action']['action']);
    $this->assertTrue($result['recovery_action']['success']);
  }

  /**
   * Test error handling with critical error.
   *
   * @covers ::handleError
   */
  public function testHandleErrorCriticalError(): void {
    $error = new \Exception('Fatal error: Cannot allocate memory');
    $context = ['execution_id' => 'test_execution'];

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('resource', $result['category']);
    $this->assertEquals('critical', $result['severity']);
    $this->assertEquals('custom_strategy', $result['recovery_action']['action']);
    $this->assertTrue($result['recovery_action']['success']);
  }

  /**
   * Test error handling with low severity warning.
   *
   * @covers ::handleError
   */
  public function testHandleErrorLowSeverityWarning(): void {
    $error = new \Exception('Warning: Deprecated function used');
    $context = ['node_id' => 'test_node'];

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('unknown', $result['category']);
    $this->assertEquals('low', $result['severity']);
  }

  /**
   * Test batch error handling.
   *
   * @covers ::handleBatchErrors
   */
  public function testHandleBatchErrors(): void {
    $errors = [
      new RuntimeException('Error 1'),
      new DataFlowException('Error 2'),
      new \Exception('Fatal error: Critical issue'),
    ];
    $context = ['batch_id' => 'test_batch'];

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Handling batch of @count errors');

    $this->eventDispatcher->expects($this->exactly(4))
      ->method('dispatch');

    $result = $this->errorHandler->handleBatchErrors($errors, $context);

    $this->assertEquals(3, $result['total_errors']);
    $this->assertEquals(1, $result['critical_count']);
    $this->assertCount(3, $result['results']);
    $this->assertCount(1, $result['critical_errors']);
  }

  /**
   * Test custom error category registration.
   *
   * @covers ::registerErrorCategory
   */
  public function testRegisterErrorCategory(): void {
    $category = 'custom_error';
    $rules = [
      [
        'message_pattern' => '/custom error/i',
        'description' => 'Custom error type',
      ],
    ];

    $this->logger->expects($this->once())
      ->method('debug')
      ->with('Registered error category: @category');

    $this->errorHandler->registerErrorCategory($category, $rules);

    // Test that the category is used for matching errors.
    $error = new \Exception('This is a custom error message');
    $result = $this->errorHandler->handleError($error);

    $this->assertEquals('custom_error', $result['category']);
  }

  /**
   * Test custom recovery strategy registration.
   *
   * @covers ::registerRecoveryStrategy
   */
  public function testRegisterRecoveryStrategy(): void {
    $category = 'custom_error';
    $strategy = function (\Exception $error, array $context) {
      return [
        'success' => TRUE,
        'message' => 'Custom recovery successful',
        'data' => ['custom_data' => 'value'],
      ];
    };

    $this->logger->expects($this->once())
      ->method('debug')
      ->with('Registered recovery strategy for category: @category');

    $this->errorHandler->registerRecoveryStrategy($category, $strategy);

    // Test that the custom strategy is used.
    $error = new \Exception('Custom error');
    $result = $this->errorHandler->handleError($error);

    // Since we haven't registered a category for this error, it will use
    // default.
    $this->assertEquals('unknown', $result['category']);
  }

  /**
   * Test error statistics retrieval.
   *
   * @covers ::getErrorStatistics
   */
  public function testGetErrorStatistics(): void {
    $statistics = $this->errorHandler->getErrorStatistics();

    $this->assertArrayHasKey('total_errors', $statistics);
    $this->assertArrayHasKey('errors_by_category', $statistics);
    $this->assertArrayHasKey('errors_by_severity', $statistics);
    $this->assertArrayHasKey('recovery_success_rate', $statistics);
    $this->assertArrayHasKey('average_resolution_time', $statistics);
  }

  /**
   * Test error handling with custom recovery strategy.
   *
   * @covers ::handleError
   */
  public function testHandleErrorWithCustomRecoveryStrategy(): void {
    // Register a custom category and strategy.
    $this->errorHandler->registerErrorCategory('timeout', [
      ['message_pattern' => '/timeout/i'],
    ]);

    $this->errorHandler->registerRecoveryStrategy('timeout', function (\Exception $error, array $context) {
      return [
        'success' => TRUE,
        'message' => 'Timeout recovery successful',
        'data' => ['strategy' => 'increase_timeout'],
      ];
    });

    $error = new \Exception('Request timeout after 30 seconds');
    $result = $this->errorHandler->handleError($error);

    $this->assertEquals('timeout', $result['category']);
    $this->assertEquals('custom_strategy', $result['recovery_action']['action']);
    $this->assertTrue($result['recovery_action']['success']);
    $this->assertEquals('Timeout recovery successful', $result['recovery_action']['message']);
  }

  /**
   * Test error handling with recovery strategy failure.
   *
   * @covers ::handleError
   */
  public function testHandleErrorWithRecoveryStrategyFailure(): void {
    // Register a strategy that throws an exception.
    $this->errorHandler->registerRecoveryStrategy('test_category', function (\Exception $error, array $context) {
      throw new \Exception('Recovery strategy failed');
    });

    $error = new \Exception('Test error');
    $result = $this->errorHandler->handleError($error);

    // Should fall back to default recovery.
    $this->assertEquals('unknown', $result['category']);
    $this->assertEquals('none', $result['recovery_action']['action']);
    $this->assertFalse($result['recovery_action']['success']);
  }

  /**
   * Test error categorization with multiple rules.
   *
   * @covers ::handleError
   */
  public function testHandleErrorWithMultipleRules(): void {
    $this->errorHandler->registerErrorCategory('validation', [
      ['message_pattern' => '/validation/i'],
      ['message_pattern' => '/invalid/i'],
    ]);

    $error = new \Exception('Data validation failed');
    $result = $this->errorHandler->handleError($error);

    $this->assertEquals('validation', $result['category']);
    $this->assertEquals(1.0, $result['confidence']);
  }

  /**
   * Test error handling with partial rule matches.
   *
   * @covers ::handleError
   */
  public function testHandleErrorWithPartialRuleMatches(): void {
    $this->errorHandler->registerErrorCategory('complex', [
      [
        'class' => RuntimeException::class,
        'message_pattern' => '/test/i',
      ],
      [
        'class' => DataFlowException::class,
        'message_pattern' => '/data/i',
      ],
    ]);

    $error = new RuntimeException('This is a test error');
    $result = $this->errorHandler->handleError($error);

    $this->assertEquals('complex', $result['category']);
    $this->assertEquals(1.0, $result['confidence']);
  }

  /**
   * Test error handling with no rule matches.
   *
   * @covers ::handleError
   */
  public function testHandleErrorWithNoRuleMatches(): void {
    $this->errorHandler->registerErrorCategory('specific', [
      [
        'class' => RuntimeException::class,
        'message_pattern' => '/specific pattern/i',
      ],
    ]);

    $error = new RuntimeException('This error does not match the pattern');
    $result = $this->errorHandler->handleError($error);

    // Should fall back to default categorization.
    $this->assertEquals('runtime', $result['category']);
  }

  /**
   * Test error handling with context information.
   *
   * @covers ::handleError
   */
  public function testHandleErrorWithContext(): void {
    $error = new RuntimeException('Test error');
    $context = [
      'execution_id' => 'test_execution',
      'node_id' => 'test_node',
      'workflow_id' => 'test_workflow',
      'timestamp' => time(),
    ];

    $this->eventDispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->callback(function ($event) use ($context) {
        $eventData = $event->getArguments();
        return $eventData['context'] === $context;
      }), 'flowdrop_runtime.execution.error');

    $result = $this->errorHandler->handleError($error, $context);

    $this->assertEquals('runtime', $result['category']);
    $this->assertArrayHasKey('error_trace', $result);
  }

}
