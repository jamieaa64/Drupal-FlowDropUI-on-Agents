<?php

declare(strict_types=1);

namespace Drupal\tool_system\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the log message tool.
 */
#[Tool(
  id: 'log_message',
  label: new TranslatableMarkup('Log message'),
  description: new TranslatableMarkup('Log a message to the Drupal logging system.'),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'message' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Message"),
      description: new TranslatableMarkup("The message to log.")
    ),
    'level' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Log Level"),
      description: new TranslatableMarkup("The log level (emergency, alert, critical, error, warning, notice, info, debug)."),
      default_value: 'info',
      constraints: [
        'Choice' => [
          'choices' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
          'message' => new TranslatableMarkup('The log level must be one of: emergency, alert, critical, error, warning, notice, info, debug.'),
        ],
      ],
    ),
    'channel' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Channel"),
      description: new TranslatableMarkup("The log channel (defaults to 'tool')."),
      required: FALSE,
      default_value: 'tool',
    ),
    'context' => new InputDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Context"),
      description: new TranslatableMarkup("Additional context data to include with the log message."),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'logged' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Logged"),
      description: new TranslatableMarkup("Whether the message was successfully logged.")
    ),
  ],
)]
final class LogMessage extends ToolBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('tool');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    [
      'message' => $message,
      'level' => $level,
      'channel' => $channel,
      'context' => $context,
    ] = $values + ['context' => []];

    try {
      // Ensure context is an array.
      $context = $context ?? [];
      if (!is_array($context)) {
        $context = ['data' => $context];
      }

      // Log the message using the appropriate level.
      switch ($level) {
        case 'emergency':
          $this->logger->emergency($message, $context);
          break;

        case 'alert':
          $this->logger->alert($message, $context);
          break;

        case 'critical':
          $this->logger->critical($message, $context);
          break;

        case 'error':
          $this->logger->error($message, $context);
          break;

        case 'warning':
          $this->logger->warning($message, $context);
          break;

        case 'notice':
          $this->logger->notice($message, $context);
          break;

        case 'info':
          $this->logger->info($message, $context);
          break;

        case 'debug':
          $this->logger->debug($message, $context);
          break;

        default:
          $this->logger->info($message, $context);
      }

      return ExecutableResult::success($this->t('Successfully logged @level message to @channel channel.', [
        '@level' => $level,
        '@channel' => $channel,
      ]), ['logged' => TRUE]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error logging message: @message', [
        '@message' => $e->getMessage(),
      ]), ['logged' => FALSE]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use log_message tool');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
