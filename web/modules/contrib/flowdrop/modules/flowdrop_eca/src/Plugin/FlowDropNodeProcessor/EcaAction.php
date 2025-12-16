<?php

declare(strict_types=1);

namespace Drupal\flowdrop_eca\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\DTO\Config;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for ECA Action nodes.
 *
 * ECA Action nodes execute ECA actions within FlowDrop workflows,
 * allowing integration between FlowDrop and ECA systems.
 */
#[FlowDropNodeProcessor(
  id: "eca_action",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("ECA Action"),
  type: "default",
  supportedTypes: ["default"],
  category: "eca",
  description: "Execute ECA actions within FlowDrop workflows",
  version: "1.0.0",
  tags: ["eca", "action", "workflow"]
)]
class EcaAction extends AbstractFlowDropNodeProcessor {

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
      $container,
      $configuration,
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
   * Create a Config object from an array.
   *
   * @param array $config_array
   *   The configuration array.
   *
   * @return \Drupal\flowdrop\DTO\ConfigInterface
   *   The Config object.
   */
  protected function createConfigFromArray(array $config_array): ConfigInterface {
    $config = new Config();
    $config->fromArray($config_array);
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $action_type = $config->getConfig('actionType', '');
    $action_config = $config->getConfig('actionConfig', []);
    $eca_context = $config->getConfig('ecaContext', []);

    // Merge inputs with ECA context.
    $context = array_merge($eca_context, $inputs->toArray());

    // Execute the ECA action.
    $result = $this->executeEcaAction($action_type, $action_config, $context);

    $this->getLogger()->info('ECA Action executed successfully', [
      'action_type' => $action_type,
      'success' => $result['success'] ?? FALSE,
    ]);

    return [
      'action_type' => $action_type,
      'action_result' => $result,
      'success' => $result['success'] ?? FALSE,
      'output' => $result['output'] ?? [],
      'errors' => $result['errors'] ?? [],
    ];
  }

  /**
   * Execute an ECA action.
   *
   * @param string $action_type
   *   The type of ECA action to execute.
   * @param array $action_config
   *   The action configuration.
   * @param array $context
   *   The context data for the action.
   *
   * @return array
   *   The action execution result.
   */
  protected function executeEcaAction(string $action_type, array $action_config, array $context): array {
    // This would integrate with ECA's action execution system
    // For now, provide a framework for different action types.
    switch ($action_type) {
      case 'create_entity':
        return $this->executeCreateEntityAction($this->createConfigFromArray($action_config), $context);

      case 'update_entity':
        return $this->executeUpdateEntityAction($this->createConfigFromArray($action_config), $context);

      case 'delete_entity':
        return $this->executeDeleteEntityAction($action_config, $context);

      case 'send_email':
        return $this->executeSendEmailAction($this->createConfigFromArray($action_config), $context);

      case 'redirect_user':
        return $this->executeRedirectUserAction($action_config, $context);

      case 'set_message':
        return $this->executeSetMessageAction($action_config, $context);

      case 'log_action':
        return $this->executeLogActionAction($action_config, $context);

      default:
        return $this->executeCustomAction($action_type, $action_config, $context);
    }
  }

  /**
   * Execute a create entity action.
   *
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeCreateEntityAction(ConfigInterface $config, array $context): array {
    $entity_type = $config->getConfig('entity_type', '');
    $bundle = $config->getConfig('bundle', '');
    $values = $config->getConfig('values', []);

    // This would integrate with Drupal's entity creation system.
    $this->getLogger()->info('Creating ECA entity', [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'values' => $values,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'entity_id' => uniqid('entity_', TRUE),
        'entity_type' => $entity_type,
        'bundle' => $bundle,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute an update entity action.
   *
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeUpdateEntityAction(ConfigInterface $config, array $context): array {
    $entity_id = $config->getConfig('entity_id', '');
    $entity_type = $config->getConfig('entity_type', '');
    $values = $config->getConfig('values', []);

    $this->getLogger()->info('Updating ECA entity', [
      'entity_id' => $entity_id,
      'entity_type' => $entity_type,
      'values' => $values,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'entity_id' => $entity_id,
        'entity_type' => $entity_type,
        'updated' => TRUE,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute a delete entity action.
   *
   * @param array $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeDeleteEntityAction(array $config, array $context): array {
    $entity_id = $config['entity_id'] ?? '';
    $entity_type = $config['entity_type'] ?? '';

    $this->getLogger()->info('Deleting ECA entity', [
      'entity_id' => $entity_id,
      'entity_type' => $entity_type,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'entity_id' => $entity_id,
        'entity_type' => $entity_type,
        'deleted' => TRUE,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute a send email action.
   *
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeSendEmailAction(ConfigInterface $config, array $context): array {
    $to = $config->getConfig('to', '');
    $subject = $config->getConfig('subject', '');
    $from = $config->getConfig('from', '');

    $this->getLogger()->info('Sending ECA email', [
      'to' => $to,
      'subject' => $subject,
      'from' => $from,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'email_sent' => TRUE,
        'to' => $to,
        'subject' => $subject,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute a redirect user action.
   *
   * @param array $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeRedirectUserAction(array $config, array $context): array {
    $url = $config['url'] ?? '';
    $status_code = $config['status_code'] ?? 302;

    $this->getLogger()->info('Redirecting user via ECA', [
      'url' => $url,
      'status_code' => $status_code,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'redirect_url' => $url,
        'status_code' => $status_code,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute a set message action.
   *
   * @param array $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeSetMessageAction(array $config, array $context): array {
    $message = $config['message'] ?? '';
    $type = $config['type'] ?? 'status';
    $repeat = $config['repeat'] ?? FALSE;

    $this->getLogger()->info('Setting ECA message', [
      'message' => $message,
      'type' => $type,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'message' => $message,
        'type' => $type,
        'repeat' => $repeat,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute a log action.
   *
   * @param array $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeLogActionAction(array $config, array $context): array {
    $message = $config['message'] ?? '';
    $level = $config['level'] ?? 'info';
    $channel = $config['channel'] ?? 'eca';

    $this->loggerFactory->get($channel)->log($level, $message, $context);

    return [
      'success' => TRUE,
      'output' => [
        'logged' => TRUE,
        'message' => $message,
        'level' => $level,
        'channel' => $channel,
      ],
      'errors' => [],
    ];
  }

  /**
   * Execute a custom action.
   *
   * @param string $action_type
   *   The custom action type.
   * @param array $config
   *   The action configuration.
   * @param array $context
   *   The context data.
   *
   * @return array
   *   The action result.
   */
  protected function executeCustomAction(string $action_type, array $config, array $context): array {
    $this->getLogger()->info('Executing custom ECA action', [
      'action_type' => $action_type,
      'config' => $config,
    ]);

    return [
      'success' => TRUE,
      'output' => [
        'action_type' => $action_type,
        'custom_action' => TRUE,
      ],
      'errors' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // ECA actions can accept any inputs.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'action_type' => [
        'type' => 'string',
        'description' => 'The type of ECA action that was executed',
      ],
      'action_result' => [
        'type' => 'object',
        'description' => 'The result from the ECA action execution',
      ],
      'success' => [
        'type' => 'boolean',
        'description' => 'Whether the action was successful',
      ],
      'output' => [
        'type' => 'object',
        'description' => 'Output data from the action',
      ],
      'errors' => [
        'type' => 'array',
        'description' => 'Any errors that occurred during execution',
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
        'actionType' => [
          'type' => 'string',
          'title' => 'Action Type',
          'description' => 'The type of ECA action to execute',
          'enum' => [
            'create_entity',
            'update_entity',
            'delete_entity',
            'send_email',
            'redirect_user',
            'set_message',
            'log_action',
          ],
          'default' => 'log_action',
        ],
        'actionConfig' => [
          'type' => 'object',
          'title' => 'Action Configuration',
          'description' => 'Configuration for the ECA action',
          'default' => [],
        ],
        'ecaContext' => [
          'type' => 'object',
          'title' => 'ECA Context',
          'description' => 'Context data for the ECA action',
          'default' => [],
        ],
        'description' => [
          'type' => 'string',
          'title' => 'Description',
          'description' => 'Optional description for this ECA action',
          'default' => '',
        ],
      ],
      'required' => ['actionType'],
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
          'description' => 'Input data for the ECA action',
          'required' => FALSE,
        ],
        'entity' => [
          'type' => 'object',
          'title' => 'Entity',
          'description' => 'Entity data for entity-related actions',
          'required' => FALSE,
        ],
        'user' => [
          'type' => 'object',
          'title' => 'User',
          'description' => 'User data for user-related actions',
          'required' => FALSE,
        ],
        'parameters' => [
          'type' => 'object',
          'title' => 'Parameters',
          'description' => 'Additional parameters for the action',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
