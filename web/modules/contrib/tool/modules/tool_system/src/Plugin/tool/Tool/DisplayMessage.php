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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the display message tool.
 */
#[Tool(
  id: 'display_message',
  label: new TranslatableMarkup('Display message'),
  description: new TranslatableMarkup("Display a message to the user via Drupal's messenger system."),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'message' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Message"),
      description: new TranslatableMarkup("The message to display to the user.")
    ),
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Message Type"),
      description: new TranslatableMarkup("The type of message (status, warning, error)."),
      default_value: 'status',
      constraints: [
        'Choice' => [
          'choices' => ['status', 'warning', 'error'],
          'message' => new TranslatableMarkup('The message type must be one of: status, warning, error.'),
        ],
      ],
    ),
    'repeat' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Allow Repeat"),
      description: new TranslatableMarkup("Whether to allow duplicate messages to be displayed."),
      required: FALSE,
      default_value: FALSE,
    ),
  ],
  output_definitions: [
    'displayed' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Displayed"),
      description: new TranslatableMarkup("Whether the message was successfully displayed.")
    ),
  ],
)]
final class DisplayMessage extends ToolBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['message' => $message, 'type' => $type, 'repeat' => $repeat] = $values + ['repeat' => FALSE];
    try {
      // Display the message using the appropriate method.
      switch ($type) {
        case 'status':
          $this->messenger->addStatus($message, $repeat);
          break;

        case 'warning':
          $this->messenger->addWarning($message, $repeat);
          break;

        case 'error':
          $this->messenger->addError($message, $repeat);
          break;

        default:
          $this->messenger->addStatus($message, $repeat);
      }

      return ExecutableResult::success($this->t('Successfully displayed @type message.', [
        '@type' => $type,
      ]), ['displayed' => TRUE]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error displaying message: @error', [
        '@error' => $e->getMessage(),
      ]), ['displayed' => FALSE]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use display_message tool');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
