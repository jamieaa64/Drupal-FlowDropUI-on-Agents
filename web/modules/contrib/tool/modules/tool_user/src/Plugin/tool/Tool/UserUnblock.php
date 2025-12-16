<?php

declare(strict_types=1);

namespace Drupal\tool_user\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\EntityInputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\user\UserInterface;

/**
 * Plugin implementation of the user unblock tool.
 */
#[Tool(
  id: 'user_unblock',
  label: new TranslatableMarkup('Unblock user'),
  description: new TranslatableMarkup('Unblock a user account.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'user' => new EntityInputDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user entity to unblock.")
    ),
  ],
  output_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The unblocked user entity.")
    ),
  ],
)]
class UserUnblock extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['user' => $user] = $values;

    try {
      if (!$user instanceof UserInterface) {
        return ExecutableResult::failure($this->t('Invalid user entity.'));
      }

      // Check if user is already active.
      if ($user->isActive()) {
        return ExecutableResult::success($this->t('User "@username" is already active.', [
          '@username' => $user->getAccountName(),
        ]), ['user' => $user]);
      }

      // Unblock the user.
      $user->activate();
      $user->save();

      return ExecutableResult::success($this->t('Successfully unblocked user "@username".', [
        '@username' => $user->getAccountName(),
      ]), ['user' => $user]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error unblocking user: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to administer users.
    $access_result = AccessResult::allowedIfHasPermission($account, 'administer users');

    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
