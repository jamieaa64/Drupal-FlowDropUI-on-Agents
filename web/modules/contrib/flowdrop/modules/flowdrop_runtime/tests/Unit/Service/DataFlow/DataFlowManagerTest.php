<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_runtime\Unit\Service\DataFlow;

use Drupal\Tests\UnitTestCase;
use Drupal\flowdrop_runtime\Service\DataFlow\DataFlowManager;
use Drupal\flowdrop_runtime\Exception\DataFlowException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for DataFlowManager.
 *
 * @coversDefaultClass \Drupal\flowdrop_runtime\Service\DataFlow\DataFlowManager
 * @group flowdrop_runtime
 */
class DataFlowManagerTest extends UnitTestCase {

  /**
   * The data flow manager service.
   *
   * @var \Drupal\flowdrop_runtime\Service\DataFlow\DataFlowManager
   */
  private DataFlowManager $dataFlowManager;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->logger = $this->createMock(LoggerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->loggerFactory->method('get')->willReturn($this->logger);

    $this->dataFlowManager = new DataFlowManager($this->loggerFactory);
  }

  /**
   * Test successful data flow validation.
   *
   * @covers ::validateDataFlow
   */
  public function testValidateDataFlowSuccess(): void {
    $sourceData = [
      'name' => 'John Doe',
      'age' => 30,
      'email' => 'john@example.com',
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE],
      'age' => ['type' => 'integer', 'required' => TRUE],
      'email' => ['type' => 'string', 'required' => TRUE],
    ];

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Data flow validation successful');

    $result = $this->dataFlowManager->validateDataFlow($sourceData, $targetSchema);
    $this->assertTrue($result);
  }

  /**
   * Test data flow validation with missing required fields.
   *
   * @covers ::validateDataFlow
   */
  public function testValidateDataFlowMissingRequiredFields(): void {
    $sourceData = [
      'name' => 'John Doe',
      // Missing 'age' and 'email' fields.
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE],
      'age' => ['type' => 'integer', 'required' => TRUE],
      'email' => ['type' => 'string', 'required' => TRUE],
    ];

    $this->expectException(DataFlowException::class);
    $this->expectExceptionMessage('Missing required fields: age, email');

    $this->dataFlowManager->validateDataFlow($sourceData, $targetSchema);
  }

  /**
   * Test data flow validation with type mismatches.
   *
   * @covers ::validateDataFlow
   */
  public function testValidateDataFlowTypeMismatch(): void {
    $sourceData = [
      'name' => 'John Doe',
    // String instead of integer.
      'age' => 'thirty',
      'email' => 'john@example.com',
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE],
      'age' => ['type' => 'integer', 'required' => TRUE],
      'email' => ['type' => 'string', 'required' => TRUE],
    ];

    $this->expectException(DataFlowException::class);
    $this->expectExceptionMessage('Data type validation failed');

    $this->dataFlowManager->validateDataFlow($sourceData, $targetSchema);
  }

  /**
   * Test data flow validation with constraint violations.
   *
   * @covers ::validateDataFlow
   */
  public function testValidateDataFlowConstraintViolation(): void {
    $sourceData = [
      'name' => 'John Doe',
      'age' => 30,
      'email' => 'john@example.com',
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE, 'min_length' => 10],
      'age' => ['type' => 'integer', 'required' => TRUE],
      'email' => ['type' => 'string', 'required' => TRUE],
    ];

    $this->expectException(DataFlowException::class);
    $this->expectExceptionMessage('Constraint validation failed');

    $this->dataFlowManager->validateDataFlow($sourceData, $targetSchema);
  }

  /**
   * Test data transformation with basic rules.
   *
   * @covers ::transformData
   */
  public function testTransformDataBasic(): void {
    $sourceData = [
      'name' => 'john doe',
      'age' => '30',
      'email' => 'JOHN@EXAMPLE.COM',
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE],
      'age' => ['type' => 'integer', 'required' => TRUE],
      'email' => ['type' => 'string', 'required' => TRUE],
    ];

    $transformationRules = [
      'name' => [['type' => 'uppercase']],
      'email' => [['type' => 'lowercase']],
    ];

    $result = $this->dataFlowManager->transformData($sourceData, $targetSchema, $transformationRules);

    $this->assertEquals('JOHN DOE', $result['name']);
    $this->assertEquals(30, $result['age']);
    $this->assertEquals('john@example.com', $result['email']);
  }

  /**
   * Test data transformation with date formatting.
   *
   * @covers ::transformData
   */
  public function testTransformDataDateFormatting(): void {
    $sourceData = [
    // 2022-01-01 00:00:00
      'timestamp' => 1640995200,
      'date_string' => '2022-01-01',
    ];

    $targetSchema = [
      'timestamp' => ['type' => 'string', 'required' => TRUE],
      'date_string' => ['type' => 'string', 'required' => TRUE],
    ];

    $transformationRules = [
      'timestamp' => [['type' => 'format_date', 'format' => 'Y-m-d H:i:s']],
      'date_string' => [['type' => 'format_date', 'format' => 'Y-m-d']],
    ];

    $result = $this->dataFlowManager->transformData($sourceData, $targetSchema, $transformationRules);

    $this->assertEquals('2022-01-01 00:00:00', $result['timestamp']);
    $this->assertEquals('2022-01-01', $result['date_string']);
  }

  /**
   * Test data transformation with JSON encoding/decoding.
   *
   * @covers ::transformData
   */
  public function testTransformDataJsonOperations(): void {
    $sourceData = [
      'data_array' => ['key1' => 'value1', 'key2' => 'value2'],
      'data_json' => '{"key1":"value1","key2":"value2"}',
    ];

    $targetSchema = [
      'data_array' => ['type' => 'string', 'required' => TRUE],
      'data_json' => ['type' => 'array', 'required' => TRUE],
    ];

    $transformationRules = [
      'data_array' => [['type' => 'json_encode']],
      'data_json' => [['type' => 'json_decode']],
    ];

    $result = $this->dataFlowManager->transformData($sourceData, $targetSchema, $transformationRules);

    $this->assertEquals('{"key1":"value1","key2":"value2"}', $result['data_array']);
    $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $result['data_json']);
  }

  /**
   * Test merging data sources with different strategies.
   *
   * @covers ::mergeDataSources
   */
  public function testMergeDataSources(): void {
    $dataSources = [
      'source1' => ['name' => 'John', 'age' => 30],
      'source2' => ['email' => 'john@example.com', 'city' => 'New York'],
    // Will override source1.
      'source3' => ['name' => 'Jane', 'age' => 25],
    ];

    $mergeStrategy = [
      'source1' => 'append',
      'source2' => 'append',
      'source3' => 'replace',
    ];

    $result = $this->dataFlowManager->mergeDataSources($dataSources, $mergeStrategy);

    $expected = [
    // From source3 (replace)
      'name' => 'Jane',
    // From source3 (replace)
      'age' => 25,
    // From source2.
      'email' => 'john@example.com',
    // From source2.
      'city' => 'New York',
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Test merging data sources with nested merge strategy.
   *
   * @covers ::mergeDataSources
   */
  public function testMergeDataSourcesNested(): void {
    $dataSources = [
      'source1' => [
        'user' => ['name' => 'John', 'age' => 30],
        'settings' => ['theme' => 'dark'],
      ],
      'source2' => [
        'user' => ['email' => 'john@example.com'],
        'settings' => ['language' => 'en'],
      ],
    ];

    $mergeStrategy = [
      'source1' => 'append',
      'source2' => 'merge_nested',
    ];

    $result = $this->dataFlowManager->mergeDataSources($dataSources, $mergeStrategy);

    $expected = [
      'user' => [
        'name' => 'John',
        'age' => 30,
        'email' => 'john@example.com',
      ],
      'settings' => [
        'theme' => 'dark',
        'language' => 'en',
      ],
    ];

    $this->assertEquals($expected, $result);
  }

  /**
   * Test data transformation with default values.
   *
   * @covers ::transformData
   */
  public function testTransformDataWithDefaults(): void {
    $sourceData = [
      'name' => 'John Doe',
      // Missing 'age' and 'email' fields.
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE],
      'age' => ['type' => 'integer', 'required' => TRUE, 'default' => 0],
      'email' => ['type' => 'string', 'required' => TRUE, 'default' => 'unknown@example.com'],
    ];

    $result = $this->dataFlowManager->transformData($sourceData, $targetSchema);

    $this->assertEquals('John Doe', $result['name']);
    $this->assertEquals(0, $result['age']);
    $this->assertEquals('unknown@example.com', $result['email']);
  }

  /**
   * Test data transformation with type casting.
   *
   * @covers ::transformData
   */
  public function testTransformDataTypeCasting(): void {
    $sourceData = [
      'string_value' => 'hello',
      'integer_value' => '42',
      'float_value' => '3.14',
      'boolean_value' => 'true',
      'array_value' => 'single_value',
    ];

    $targetSchema = [
      'string_value' => ['type' => 'string', 'required' => TRUE],
      'integer_value' => ['type' => 'integer', 'required' => TRUE],
      'float_value' => ['type' => 'float', 'required' => TRUE],
      'boolean_value' => ['type' => 'boolean', 'required' => TRUE],
      'array_value' => ['type' => 'array', 'required' => TRUE],
    ];

    $result = $this->dataFlowManager->transformData($sourceData, $targetSchema);

    $this->assertEquals('hello', $result['string_value']);
    $this->assertEquals(42, $result['integer_value']);
    $this->assertEquals(3.14, $result['float_value']);
    $this->assertEquals(TRUE, $result['boolean_value']);
    $this->assertEquals(['single_value'], $result['array_value']);
  }

  /**
   * Test data flow validation with complex constraints.
   *
   * @covers ::validateDataFlow
   */
  public function testValidateDataFlowComplexConstraints(): void {
    $sourceData = [
      'name' => 'John Doe',
    // Over maximum value.
      'age' => 150,
      'email' => 'very_long_email_address_that_exceeds_the_maximum_length_limit@example.com',
    ];

    $targetSchema = [
      'name' => ['type' => 'string', 'required' => TRUE, 'min_length' => 5],
      'age' => ['type' => 'integer', 'required' => TRUE, 'min_value' => 0, 'max_value' => 120],
      'email' => ['type' => 'string', 'required' => TRUE, 'max_length' => 50],
    ];

    $this->expectException(DataFlowException::class);
    $this->expectExceptionMessage('Constraint validation failed');

    $this->dataFlowManager->validateDataFlow($sourceData, $targetSchema);
  }

  /**
   * Test data transformation error handling.
   *
   * @covers ::transformData
   */
  public function testTransformDataErrorHandling(): void {
    $sourceData = [
      'invalid_json' => 'invalid json string',
    ];

    $targetSchema = [
      'invalid_json' => ['type' => 'array', 'required' => TRUE],
    ];

    $transformationRules = [
      'invalid_json' => [['type' => 'json_decode']],
    ];

    $this->expectException(DataFlowException::class);
    $this->expectExceptionMessage('Data transformation failed');

    $this->dataFlowManager->transformData($sourceData, $targetSchema, $transformationRules);
  }

}
