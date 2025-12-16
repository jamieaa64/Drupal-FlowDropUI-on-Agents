<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\DataFlow;

use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop_runtime\Exception\DataFlowException;

/**
 * Manages data flow and transformation between nodes.
 */
class DataFlowManager {

  /**
   * Logger channel for this service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private readonly LoggerInterface $logger;

  public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
    $this->logger = $loggerFactory->get('flowdrop_runtime');
  }

  /**
   * Validate data flow between nodes.
   *
   * @param array $sourceData
   *   The source data from the previous node.
   * @param array $targetSchema
   *   The expected schema for the target node.
   * @param array $context
   *   Additional context for validation.
   *
   * @return bool
   *   TRUE if data flow is valid, FALSE otherwise.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\DataFlowException
   *   When validation fails with specific error details.
   */
  public function validateDataFlow(array $sourceData, array $targetSchema, array $context = []): bool {
    $this->logger->debug('Validating data flow between nodes', [
      'source_data_size' => count($sourceData),
      'target_schema_keys' => array_keys($targetSchema),
      'context' => $context,
    ]);

    try {
      // Check required fields in target schema.
      $requiredFields = $this->extractRequiredFields($targetSchema);
      $missingFields = $this->findMissingFields($sourceData, $requiredFields);

      if (!empty($missingFields)) {
        throw new DataFlowException(sprintf(
          'Missing required fields: %s',
          implode(', ', $missingFields)
        ));
      }

      // Validate data types.
      $typeErrors = $this->validateDataTypes($sourceData, $targetSchema);
      if (!empty($typeErrors)) {
        throw new DataFlowException(sprintf(
          'Data type validation failed: %s',
          implode('; ', $typeErrors)
        ));
      }

      // Validate data constraints.
      $constraintErrors = $this->validateConstraints($sourceData, $targetSchema);
      if (!empty($constraintErrors)) {
        throw new DataFlowException(sprintf(
          'Constraint validation failed: %s',
          implode('; ', $constraintErrors)
        ));
      }

      $this->logger->info('Data flow validation successful', [
        'source_data_keys' => array_keys($sourceData),
        'target_schema_keys' => array_keys($targetSchema),
      ]);

      return TRUE;
    }
    catch (DataFlowException $e) {
      $this->logger->error('Data flow validation failed: @error', [
        '@error' => $e->getMessage(),
        'context' => $context,
      ]);
      throw $e;
    }
  }

  /**
   * Transform data according to target schema.
   *
   * @param array $sourceData
   *   The source data to transform.
   * @param array $targetSchema
   *   The target schema for transformation.
   * @param array $transformationRules
   *   Optional transformation rules.
   *
   * @return array
   *   The transformed data.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\DataFlowException
   *   When transformation fails.
   */
  public function transformData(array $sourceData, array $targetSchema, array $transformationRules = []): array {
    $this->logger->debug('Transforming data according to target schema', [
      'source_data_size' => count($sourceData),
      'target_schema_keys' => array_keys($targetSchema),
      'transformation_rules_count' => count($transformationRules),
    ]);

    try {
      $transformedData = [];

      foreach ($targetSchema as $fieldName => $fieldConfig) {
        $transformedData[$fieldName] = $this->transformField(
          $sourceData,
          $fieldName,
          $fieldConfig,
          $transformationRules[$fieldName] ?? []
        );
      }

      $this->logger->info('Data transformation completed successfully', [
        'transformed_fields_count' => count($transformedData),
      ]);

      return $transformedData;
    }
    catch (\Exception $e) {
      $this->logger->error('Data transformation failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw new DataFlowException('Data transformation failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Merge data from multiple sources.
   *
   * @param array $dataSources
   *   Array of data sources to merge.
   * @param array $mergeStrategy
   *   Strategy for merging data.
   *
   * @return array
   *   The merged data.
   */
  public function mergeDataSources(array $dataSources, array $mergeStrategy = []): array {
    $this->logger->debug('Merging data from multiple sources', [
      'sources_count' => count($dataSources),
      'merge_strategy' => $mergeStrategy,
    ]);

    $mergedData = [];

    foreach ($dataSources as $sourceId => $sourceData) {
      $strategy = $mergeStrategy[$sourceId] ?? 'append';

      switch ($strategy) {
        case 'append':
          $mergedData = array_merge($mergedData, $sourceData);
          break;

        case 'prepend':
          $mergedData = array_merge($sourceData, $mergedData);
          break;

        case 'replace':
          $mergedData = array_merge($mergedData, $sourceData);
          break;

        case 'merge_nested':
          $mergedData = $this->mergeNestedArrays($mergedData, $sourceData);
          break;

        default:
          $this->logger->warning('Unknown merge strategy: @strategy', [
            '@strategy' => $strategy,
          ]);
          $mergedData = array_merge($mergedData, $sourceData);
      }
    }

    $this->logger->info('Data sources merged successfully', [
      'merged_fields_count' => count($mergedData),
    ]);

    return $mergedData;
  }

  /**
   * Extract required fields from schema.
   *
   * @param array $schema
   *   The schema to extract required fields from.
   *
   * @return array<string>
   *   Array of required field names.
   */
  private function extractRequiredFields(array $schema): array {
    $requiredFields = [];

    foreach ($schema as $fieldName => $fieldConfig) {
      if (isset($fieldConfig['required']) && $fieldConfig['required'] === TRUE) {
        $requiredFields[] = $fieldName;
      }
    }

    return $requiredFields;
  }

  /**
   * Find missing required fields in source data.
   *
   * @param array $sourceData
   *   The source data to check.
   * @param array $requiredFields
   *   The required fields to check for.
   *
   * @return array<string>
   *   Array of missing field names.
   */
  private function findMissingFields(array $sourceData, array $requiredFields): array {
    $missingFields = [];

    foreach ($requiredFields as $fieldName) {
      if (!array_key_exists($fieldName, $sourceData)) {
        $missingFields[] = $fieldName;
      }
    }

    return $missingFields;
  }

  /**
   * Validate data types against schema.
   *
   * @param array $sourceData
   *   The source data to validate.
   * @param array $targetSchema
   *   The target schema for validation.
   *
   * @return array<string>
   *   Array of type validation errors.
   */
  private function validateDataTypes(array $sourceData, array $targetSchema): array {
    $errors = [];

    foreach ($targetSchema as $fieldName => $fieldConfig) {
      if (!array_key_exists($fieldName, $sourceData)) {
        // Skip missing fields (handled by required field validation).
        continue;
      }

      $expectedType = $fieldConfig['type'] ?? 'string';
      $actualValue = $sourceData[$fieldName];

      if (!$this->validateFieldType($actualValue, $expectedType)) {
        $errors[] = sprintf(
          'Field "%s" expected type "%s", got "%s"',
          $fieldName,
          $expectedType,
          gettype($actualValue)
        );
      }
    }

    return $errors;
  }

  /**
   * Validate field type.
   *
   * @param mixed $value
   *   The value to validate.
   * @param string $expectedType
   *   The expected type.
   *
   * @return bool
   *   TRUE if type is valid, FALSE otherwise.
   */
  private function validateFieldType(mixed $value, string $expectedType): bool {
    return match ($expectedType) {
      'string' => is_string($value),
      'integer' => is_int($value),
      'float' => is_float($value) || is_int($value),
      'boolean' => is_bool($value),
      'array' => is_array($value),
      'object' => is_object($value),
      'null' => is_null($value),
      // Unknown types are considered valid.
      default => TRUE,
    };
  }

  /**
   * Validate data constraints.
   *
   * @param array $sourceData
   *   The source data to validate.
   * @param array $targetSchema
   *   The target schema for validation.
   *
   * @return array<string>
   *   Array of constraint validation errors.
   */
  private function validateConstraints(array $sourceData, array $targetSchema): array {
    $errors = [];

    foreach ($targetSchema as $fieldName => $fieldConfig) {
      if (!array_key_exists($fieldName, $sourceData)) {
        continue;
      }

      $value = $sourceData[$fieldName];

      // Check minimum length for strings.
      if (isset($fieldConfig['min_length']) && is_string($value)) {
        if (strlen($value) < $fieldConfig['min_length']) {
          $errors[] = sprintf(
            'Field "%s" minimum length is %d, got %d',
            $fieldName,
            $fieldConfig['min_length'],
            strlen($value)
          );
        }
      }

      // Check maximum length for strings.
      if (isset($fieldConfig['max_length']) && is_string($value)) {
        if (strlen($value) > $fieldConfig['max_length']) {
          $errors[] = sprintf(
            'Field "%s" maximum length is %d, got %d',
            $fieldName,
            $fieldConfig['max_length'],
            strlen($value)
          );
        }
      }

      // Check minimum value for numbers.
      if (isset($fieldConfig['min_value']) && is_numeric($value)) {
        if ($value < $fieldConfig['min_value']) {
          $errors[] = sprintf(
            'Field "%s" minimum value is %s, got %s',
            $fieldName,
            $fieldConfig['min_value'],
            $value
          );
        }
      }

      // Check maximum value for numbers.
      if (isset($fieldConfig['max_value']) && is_numeric($value)) {
        if ($value > $fieldConfig['max_value']) {
          $errors[] = sprintf(
            'Field "%s" maximum value is %s, got %s',
            $fieldName,
            $fieldConfig['max_value'],
            $value
          );
        }
      }
    }

    return $errors;
  }

  /**
   * Transform a single field according to schema.
   *
   * @param array $sourceData
   *   The source data.
   * @param string $fieldName
   *   The field name to transform.
   * @param array $fieldConfig
   *   The field configuration.
   * @param array $transformationRules
   *   Transformation rules for this field.
   *
   * @return mixed
   *   The transformed field value.
   */
  private function transformField(array $sourceData, string $fieldName, array $fieldConfig, array $transformationRules): mixed {
    $sourceValue = $sourceData[$fieldName] ?? NULL;

    // Apply transformation rules.
    foreach ($transformationRules as $rule) {
      $sourceValue = $this->applyTransformationRule($sourceValue, $rule);
    }

    // Apply default value if source is null.
    if ($sourceValue === NULL && isset($fieldConfig['default'])) {
      $sourceValue = $fieldConfig['default'];
    }

    // Apply type casting.
    if (isset($fieldConfig['type'])) {
      $sourceValue = $this->castToType($sourceValue, $fieldConfig['type']);
    }

    return $sourceValue;
  }

  /**
   * Apply a transformation rule to a value.
   *
   * @param mixed $value
   *   The value to transform.
   * @param array $rule
   *   The transformation rule.
   *
   * @return mixed
   *   The transformed value.
   */
  private function applyTransformationRule(mixed $value, array $rule): mixed {
    $ruleType = $rule['type'] ?? 'none';

    return match ($ruleType) {
      'uppercase' => is_string($value) ? strtoupper($value) : $value,
      'lowercase' => is_string($value) ? strtolower($value) : $value,
      'trim' => is_string($value) ? trim($value) : $value,
      'format_date' => $this->formatDate($value, $rule['format'] ?? 'Y-m-d H:i:s'),
      'json_encode' => is_array($value) ? json_encode($value) : $value,
      'json_decode' => $this->decodeJson($value),
      default => $value,
    };
  }

  /**
   * Cast value to specified type.
   *
   * @param mixed $value
   *   The value to cast.
   * @param string $type
   *   The target type.
   *
   * @return mixed
   *   The casted value.
   */
  private function castToType(mixed $value, string $type): mixed {
    return match ($type) {
      'string' => (string) $value,
      'integer' => (int) $value,
      'float' => (float) $value,
      'boolean' => (bool) $value,
      'array' => is_array($value) ? $value : [$value],
      default => $value,
    };
  }

  /**
   * Format date value.
   *
   * @param mixed $value
   *   The date value to format.
   * @param string $format
   *   The date format.
   *
   * @return string
   *   The formatted date string.
   */
  private function formatDate(mixed $value, string $format): string {
    if (is_numeric($value)) {
      return gmdate($format, (int) $value);
    }

    if (is_string($value)) {
      // Parse date string in UTC to avoid timezone issues.
      $dateTime = \DateTime::createFromFormat('Y-m-d', $value, new \DateTimeZone('UTC'));
      if ($dateTime !== FALSE) {
        return $dateTime->format($format);
      }

      // Fallback for other formats.
      $timestamp = strtotime($value . ' UTC');
      return $timestamp !== FALSE ? gmdate($format, $timestamp) : $value;
    }

    return (string) $value;
  }

  /**
   * Decode JSON value with error handling.
   *
   * @param mixed $value
   *   The value to decode.
   *
   * @return mixed
   *   The decoded value.
   *
   * @throws \Drupal\flowdrop_runtime\Exception\DataFlowException
   *   When JSON decoding fails.
   */
  private function decodeJson(mixed $value): mixed {
    if (!is_string($value)) {
      return $value;
    }

    $decoded = json_decode($value, TRUE);

    if ($decoded === NULL && json_last_error() !== JSON_ERROR_NONE) {
      throw new DataFlowException('JSON decode failed: ' . json_last_error_msg());
    }

    return $decoded;
  }

  /**
   * Merge nested arrays recursively.
   *
   * @param array $array1
   *   The first array.
   * @param array $array2
   *   The second array.
   *
   * @return array
   *   The merged array.
   */
  private function mergeNestedArrays(array $array1, array $array2): array {
    $result = $array1;

    foreach ($array2 as $key => $value) {
      if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
        $result[$key] = $this->mergeNestedArrays($result[$key], $value);
      }
      else {
        $result[$key] = $value;
      }
    }

    return $result;
  }

}
