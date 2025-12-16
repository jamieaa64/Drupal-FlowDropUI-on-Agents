<?php

declare(strict_types=1);

namespace Drupal\tool_user\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\TypedData\EntityInputDefinition;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the user add role tool.
 */
#[Tool(
  id: 'user_add_role',
  label: new TranslatableMarkup('Add role to user'),
  description: new TranslatableMarkup('Add a role to a user account.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'user' => new EntityInputDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The user entity to add the role to.")
    ),
    'role_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Role ID"),
      description: new TranslatableMarkup("The machine name of the role to add (e.g., editor, administrator).")
    ),
  ],
  output_definitions: [
    'user' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User"),
      description: new TranslatableMarkup("The updated user entity.")
    ),
  ],
)]
class UserAddRole extends ToolBase {

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    [
      'user' => $user,
      'role_id' => $role_id,
    ] = $values;

    try {
      if (!$user instanceof UserInterface) {
        return ExecutableResult::failure($this->t('Invalid user entity.'));
      }

      // Check if role exists.
      $role_storage = $this->entityTypeManager->getStorage('user_role');
      $role = $role_storage->load($role_id);

      if (!$role) {
        return ExecutableResult::failure($this->t('Role "@role" does not exist.', [
          '@role' => $role_id,
        ]));
      }

      // Check if user already has the role.
      if ($user->hasRole($role_id)) {
        return ExecutableResult::success($this->t('User "@username" already has the "@role" role.', [
          '@username' => $user->getAccountName(),
          '@role' => $role->label(),
        ]), ['user' => $user]);
      }

      // Add the role.
      $user->addRole($role_id);
      $user->save();

      return ExecutableResult::success($this->t('Successfully added "@role" role to user "@username".', [
        '@role' => $role->label(),
        '@username' => $user->getAccountName(),
      ]), ['user' => $user]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error adding role to user: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to assign roles.
    // @todo confirm this is the right role to use vs 'administer roles and permissions'.
    $access_result = AccessResult::allowedIfHasPermission($account, 'administer users');

    return $return_as_object ? $access_result : $access_result->isAllowed();
  }

}
