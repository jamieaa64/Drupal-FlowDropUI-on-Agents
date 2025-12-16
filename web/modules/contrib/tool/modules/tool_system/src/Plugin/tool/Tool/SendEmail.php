<?php

declare(strict_types=1);

namespace Drupal\tool_system\Plugin\tool\Tool;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the send email tool.
 */
#[Tool(
  id: 'send_email',
  label: new TranslatableMarkup('Send email'),
  description: new TranslatableMarkup('Send an email with specified recipient, subject and body.'),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'recipient' => new InputDefinition(
      data_type: 'email',
      label: new TranslatableMarkup("Recipient"),
      description: new TranslatableMarkup("The email address of the recipient.")
    ),
    'subject' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Subject"),
      description: new TranslatableMarkup("The subject of the message.")
    ),
    'message' => new InputDefinition(
      data_type: 'text',
      label: new TranslatableMarkup("Message"),
      description: new TranslatableMarkup("The message that should be sent.")
    ),
    'token_data' => new InputDefinition(
// @todo Create a token data type?
      data_type: 'entity',
      label: new TranslatableMarkup("Token Data"),
      description: new TranslatableMarkup("The associative array of data for token replacements, avoid using 'recipient', 'subject' and 'message' as keys as they are already used."),
      required: FALSE,
      multiple: TRUE,
    ),
  ],
)]
class SendEmail extends ToolBase {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The email validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected EmailValidatorInterface $emailValidator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->token = $container->get('token');
    $instance->mailManager = $container->get('plugin.manager.mail');
    $instance->languageManager = $container->get('language_manager');
    $instance->emailValidator = $container->get('email.validator');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['recipient' => $recipient, 'subject' => $subject, 'message' => $message, 'token_data' => $token_data] = $values;
    $data = [
      'subject' => $subject,
      'message' => $message,
    ] + (array) $token_data;

    // @todo Token replacement prior to context validation or during set
    $data['recipient'] = PlainTextOutput::renderFromHtml($this->token->replace($recipient, $data));
    // If the recipient is a registered user with a language preference, use
    // the recipient's preferred language. Otherwise, use the system default
    // language.
    $recipient_accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $data['recipient']]);
    $recipient_account = reset($recipient_accounts);
    $langcode = $recipient_account ? $recipient_account->getPreferredLangcode() : $this->languageManager->getDefaultLanguage()->getId();
    $message = $this->mailManager->mail('system', 'action_send_email', $data['recipient'], $langcode, ['context' => $data]);
    // Error logging is handled by \Drupal\Core\Mail\MailManager::mail().
    if ($message['result']) {
      return ExecutableResult::success($this->t('Sent email to %recipient', ['%recipient' => $recipient]));
    }
    return ExecutableResult::failure($this->t('Failed to send email to %recipient', ['%recipient' => $recipient]));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'use send_email tool');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
