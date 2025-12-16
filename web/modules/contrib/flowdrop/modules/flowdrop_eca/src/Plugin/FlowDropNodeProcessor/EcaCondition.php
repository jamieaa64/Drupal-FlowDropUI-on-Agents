<?php

declare(strict_types=1);

namespace Drupal\flowdrop_eca\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for ECA Condition nodes.
 *
 * ECA Condition nodes evaluate ECA conditions within FlowDrop workflows,
 * allowing conditional logic based on ECA condition evaluation.
 */
#[FlowDropNodeProcessor(
  id: "eca_condition",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("ECA Condition"),
  type: "conditional",
  supportedTypes: ["conditional"],
  category: "eca",
  description: "Evaluate ECA conditions within FlowDrop workflows",
  version: "1.0.0",
  tags: ["eca", "condition", "workflow"]
)]
class EcaCondition extends AbstractFlowDropNodeProcessor {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getLogger(): LoggerChannelInterface {
    return $this->loggerFactory->get('flowdrop_node_processor');
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $condition_type = $config->getConfig('conditionType', '');
    $condition_config = $config->getConfig('conditionConfig', []);
    $eca_context = $config->getConfig('ecaContext', []);
    $default_branch = $config->getConfig('defaultBranch', 'false');

    // Merge inputs with ECA context.
    $context = array_merge($eca_context, $inputs->toArray());

    // Evaluate the ECA condition.
    $result = $this->evaluateEcaCondition($condition_type, $condition_config, $context);
    $condition_result = $result['result'] ?? FALSE;

    // Determine which branch to follow based on condition result.
    $branch_to_follow = $condition_result ? 'true' : 'false';

    // If condition fails and we have a default branch, use it.
    if (!$condition_result && $default_branch !== 'false') {
      $branch_to_follow = $default_branch;
    }

    $this->getLogger()->info('ECA Condition evaluated: @condition -> @result -> @branch', [
      'condition_type' => $condition_type,
      'result' => $condition_result ? 'true' : 'false',
      'branch' => $branch_to_follow,
    ]);

    // Return control flow information rather than data.
    return [
      'condition_evaluated' => TRUE,
      'condition_result' => $condition_result,
      'branch_to_follow' => $branch_to_follow,
      'condition_type' => $condition_type,
      'condition_config' => $condition_config,
      'eca_context' => $eca_context,
      'default_branch' => $default_branch,
      'execution_metadata' => [
        'timestamp' => time(),
        'condition_type' => 'eca_condition',
        'flow_control' => TRUE,
        'eca_integration' => TRUE,
      ],
    ];
  }

  /**
   * Evaluate an ECA condition.
   *
   * @param string $condition_type
   *   The type of ECA condition to evaluate.
   * @param array $condition_config
   *   The condition configuration.
   * @param array $context
   *   The context data for the condition.
   *
   * @return array
   *   The condition evaluation result.
   */
  protected function evaluateEcaCondition(string $condition_type, array $condition_config, array $context): array {
    // This would integrate with ECA's condition evaluation system
    // For now, provide a framework for different condition types.
    switch ($condition_type) {
      case 'entity_has_field':
        return $this->evaluateEntityHasFieldCondition($condition_config, $context);

      case 'entity_field_value':
        return $this->evaluateEntityFieldValueCondition($condition_config, $context);

      case 'user_has_role':
        return $this->evaluateUserHasRoleCondition($condition_config, $context);

      case 'user_is_authenticated':
        return $this->evaluateUserIsAuthenticatedCondition($condition_config, $context);

      case 'entity_is_published':
        return $this->evaluateEntityIsPublishedCondition($condition_config, $context);

      case 'entity_is_new':
        return $this->evaluateEntityIsNewCondition($condition_config, $context);

      case 'entity_has_bundle':
        return $this->evaluateEntityHasBundleCondition($condition_config, $context);

      case 'entity_has_entity_type':
        return $this->evaluateEntityHasEntityTypeCondition($condition_config, $context);

      case 'string_equals':
        return $this->evaluateStringEqualsCondition($condition_config, $context);

      case 'string_contains':
        return $this->evaluateStringContainsCondition($condition_config, $context);

      case 'number_equals':
        return $this->evaluateNumberEqualsCondition($condition_config, $context);

      case 'number_greater_than':
        return $this->evaluateNumberGreaterThanCondition($condition_config, $context);

      case 'number_less_than':
        return $this->evaluateNumberLessThanCondition($condition_config, $context);

      case 'list_contains':
        return $this->evaluateListContainsCondition($condition_config, $context);

      case 'list_is_empty':
        return $this->evaluateListIsEmptyCondition($condition_config, $context);

      case 'data_is_empty':
        return $this->evaluateDataIsEmptyCondition($condition_config, $context);

      case 'data_is_not_empty':
        return $this->evaluateDataIsNotEmptyCondition($condition_config, $context);

      default:
        return $this->evaluateCustomCondition($condition_type, $condition_config, $context);
    }
  }

  /**
   * Evaluate entity has field condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateEntityHasFieldCondition(array $config, array $context): array {
    $field_name = $config['field_name'] ?? '';
    $entity = $context['entity'] ?? [];

    $has_field = !empty($entity) && isset($entity[$field_name]);

    return [
      'result' => $has_field,
      'output' => [
        'field_name' => $field_name,
        'has_field' => $has_field,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate entity field value condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateEntityFieldValueCondition(array $config, array $context): array {
    $field_name = $config['field_name'] ?? '';
    $expected_value = $config['expected_value'] ?? '';
    $operator = $config['operator'] ?? 'equals';
    $entity = $context['entity'] ?? [];

    $field_value = $entity[$field_name] ?? '';
    $result = FALSE;

    switch ($operator) {
      case 'equals':
        $result = $field_value === $expected_value;
        break;

      case 'not_equals':
        $result = $field_value !== $expected_value;
        break;

      case 'contains':
        $result = strpos($field_value, $expected_value) !== FALSE;
        break;

      case 'starts_with':
        $result = strpos($field_value, $expected_value) === 0;
        break;

      case 'ends_with':
        $result = str_ends_with($field_value, $expected_value);
        break;
    }

    return [
      'result' => $result,
      'output' => [
        'field_name' => $field_name,
        'field_value' => $field_value,
        'expected_value' => $expected_value,
        'operator' => $operator,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate user has role condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateUserHasRoleCondition(array $config, array $context): array {
    $role = $config['role'] ?? '';
    $user = $context['user'] ?? [];
    $user_roles = $user['roles'] ?? [];

    $has_role = in_array($role, $user_roles);

    return [
      'result' => $has_role,
      'output' => [
        'role' => $role,
        'user_roles' => $user_roles,
        'has_role' => $has_role,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate user is authenticated condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateUserIsAuthenticatedCondition(array $config, array $context): array {
    $user = $context['user'] ?? [];
    $is_authenticated = !empty($user) && ($user['uid'] ?? 0) > 0;

    return [
      'result' => $is_authenticated,
      'output' => [
        'is_authenticated' => $is_authenticated,
        'user_id' => $user['uid'] ?? 0,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate entity is published condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateEntityIsPublishedCondition(array $config, array $context): array {
    $entity = $context['entity'] ?? [];
    $is_published = ($entity['status'] ?? 0) === 1;

    return [
      'result' => $is_published,
      'output' => [
        'is_published' => $is_published,
        'status' => $entity['status'] ?? 0,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate entity is new condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateEntityIsNewCondition(array $config, array $context): array {
    $entity = $context['entity'] ?? [];
    $is_new = empty($entity['id']) || ($entity['id'] ?? 0) === 0;

    return [
      'result' => $is_new,
      'output' => [
        'is_new' => $is_new,
        'entity_id' => $entity['id'] ?? 0,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate entity has bundle condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateEntityHasBundleCondition(array $config, array $context): array {
    $expected_bundle = $config['bundle'] ?? '';
    $entity = $context['entity'] ?? [];
    $entity_bundle = $entity['bundle'] ?? '';

    $has_bundle = $entity_bundle === $expected_bundle;

    return [
      'result' => $has_bundle,
      'output' => [
        'expected_bundle' => $expected_bundle,
        'entity_bundle' => $entity_bundle,
        'has_bundle' => $has_bundle,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate entity has entity type condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateEntityHasEntityTypeCondition(array $config, array $context): array {
    $expected_type = $config['entity_type'] ?? '';
    $entity = $context['entity'] ?? [];
    $entity_type = $entity['type'] ?? '';

    $has_type = $entity_type === $expected_type;

    return [
      'result' => $has_type,
      'output' => [
        'expected_type' => $expected_type,
        'entity_type' => $entity_type,
        'has_type' => $has_type,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate string equals condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateStringEqualsCondition(array $config, array $context): array {
    $value1 = $config['value1'] ?? '';
    $value2 = $config['value2'] ?? '';
    $case_sensitive = $config['case_sensitive'] ?? TRUE;

    if (!$case_sensitive) {
      $value1 = strtolower($value1);
      $value2 = strtolower($value2);
    }

    $result = $value1 === $value2;

    return [
      'result' => $result,
      'output' => [
        'value1' => $value1,
        'value2' => $value2,
        'case_sensitive' => $case_sensitive,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate string contains condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateStringContainsCondition(array $config, array $context): array {
    $haystack = $config['haystack'] ?? '';
    $needle = $config['needle'] ?? '';
    $case_sensitive = $config['case_sensitive'] ?? TRUE;

    if (!$case_sensitive) {
      $haystack = strtolower($haystack);
      $needle = strtolower($needle);
    }

    $result = strpos($haystack, $needle) !== FALSE;

    return [
      'result' => $result,
      'output' => [
        'haystack' => $haystack,
        'needle' => $needle,
        'case_sensitive' => $case_sensitive,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate number equals condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateNumberEqualsCondition(array $config, array $context): array {
    $value1 = (float) ($config['value1'] ?? 0);
    $value2 = (float) ($config['value2'] ?? 0);

    $result = $value1 === $value2;

    return [
      'result' => $result,
      'output' => [
        'value1' => $value1,
        'value2' => $value2,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate number greater than condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateNumberGreaterThanCondition(array $config, array $context): array {
    $value1 = (float) ($config['value1'] ?? 0);
    $value2 = (float) ($config['value2'] ?? 0);

    $result = $value1 > $value2;

    return [
      'result' => $result,
      'output' => [
        'value1' => $value1,
        'value2' => $value2,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate number less than condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateNumberLessThanCondition(array $config, array $context): array {
    $value1 = (float) ($config['value1'] ?? 0);
    $value2 = (float) ($config['value2'] ?? 0);

    $result = $value1 < $value2;

    return [
      'result' => $result,
      'output' => [
        'value1' => $value1,
        'value2' => $value2,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate list contains condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateListContainsCondition(array $config, array $context): array {
    $list = $config['list'] ?? [];
    $item = $config['item'] ?? '';

    $result = in_array($item, $list);

    return [
      'result' => $result,
      'output' => [
        'list' => $list,
        'item' => $item,
        'contains' => $result,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate list is empty condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateListIsEmptyCondition(array $config, array $context): array {
    $list = $config['list'] ?? [];

    $result = empty($list);

    return [
      'result' => $result,
      'output' => [
        'list' => $list,
        'is_empty' => $result,
        'count' => count($list),
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate data is empty condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateDataIsEmptyCondition(array $config, array $context): array {
    $data = $config['data'] ?? '';

    $result = empty($data);

    return [
      'result' => $result,
      'output' => [
        'data' => $data,
        'is_empty' => $result,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate data is not empty condition.
   *
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateDataIsNotEmptyCondition(array $config, array $context): array {
    $data = $config['data'] ?? '';

    $result = !empty($data);

    return [
      'result' => $result,
      'output' => [
        'data' => $data,
        'is_not_empty' => $result,
      ],
      'errors' => [],
    ];
  }

  /**
   * Evaluate a custom condition.
   *
   * @param string $condition_type
   *   The custom condition type.
   * @param array $config
   *   The condition configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The condition result.
   */
  protected function evaluateCustomCondition(string $condition_type, array $config, array $context): array {
    $this->getLogger()->info('Evaluating custom ECA condition', [
      'condition_type' => $condition_type,
      'config' => $config,
    ]);

    return [
      'result' => TRUE,
      'output' => [
        'condition_type' => $condition_type,
        'custom_condition' => TRUE,
      ],
      'errors' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // ECA conditions can accept any inputs.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'condition_evaluated' => [
        'type' => 'boolean',
        'description' => 'Whether the ECA condition was evaluated',
      ],
      'condition_result' => [
        'type' => 'boolean',
        'description' => 'The result from the ECA condition evaluation',
      ],
      'branch_to_follow' => [
        'type' => 'string',
        'description' => 'The branch to follow based on the condition result',
        'enum' => ['true', 'false'],
      ],
      'condition_type' => [
        'type' => 'string',
        'description' => 'The type of ECA condition that was evaluated',
      ],
      'condition_config' => [
        'type' => 'object',
        'description' => 'The configuration used for the ECA condition evaluation',
      ],
      'eca_context' => [
        'type' => 'object',
        'description' => 'The context data used for the ECA condition evaluation',
      ],
      'default_branch' => [
        'type' => 'string',
        'description' => 'The default branch to follow if the condition fails',
        'enum' => ['true', 'false'],
      ],
      'execution_metadata' => [
        'type' => 'object',
        'description' => 'Metadata about the execution of the ECA condition',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'conditionType' => [
          'type' => 'string',
          'title' => 'Condition Type',
          'description' => 'The type of ECA condition to evaluate',
          'enum' => [
            'entity_has_field',
            'entity_field_value',
            'user_has_role',
            'user_is_authenticated',
            'entity_is_published',
            'entity_is_new',
            'entity_has_bundle',
            'entity_has_entity_type',
            'string_equals',
            'string_contains',
            'number_equals',
            'number_greater_than',
            'number_less_than',
            'list_contains',
            'list_is_empty',
            'data_is_empty',
            'data_is_not_empty',
          ],
          'default' => 'data_is_not_empty',
        ],
        'conditionConfig' => [
          'type' => 'object',
          'title' => 'Condition Configuration',
          'description' => 'Configuration for the ECA condition',
          'default' => [],
        ],
        'ecaContext' => [
          'type' => 'object',
          'title' => 'ECA Context',
          'description' => 'Context data for the ECA condition',
          'default' => [],
        ],
        'defaultBranch' => [
          'type' => 'string',
          'title' => 'Default Branch',
          'description' => 'The branch to follow if the condition fails',
          'enum' => ['true', 'false'],
          'default' => 'false',
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Optional description for this ECA condition',
          'default' => '',
        ],
      ],
      'required' => ['conditionType'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'data' => [
          'type' => 'mixed',
          'title' => 'Input Data',
          'description' => 'Input data for the ECA condition',
          'required' => FALSE,
        ],
        'entity' => [
          'type' => 'object',
          'title' => 'Entity',
          'description' => 'Entity data for entity-related conditions',
          'required' => FALSE,
        ],
        'user' => [
          'type' => 'object',
          'title' => 'User',
          'description' => 'User data for user-related conditions',
          'required' => FALSE,
        ],
        'parameters' => [
          'type' => 'object',
          'title' => 'Parameters',
          'description' => 'Additional parameters for the condition',
          'required' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'conditional';
  }

}
