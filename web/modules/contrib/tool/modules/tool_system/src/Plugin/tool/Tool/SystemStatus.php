<?php

declare(strict_types=1);

namespace Drupal\tool_system\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\SystemManager;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the system status tool.
 */
#[Tool(
  id: 'system_status',
  label: new TranslatableMarkup('Get system status'),
  description: new TranslatableMarkup('Get the Drupal system status information including warnings and errors.'),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'status_report' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup("Status Report"),
      description: new TranslatableMarkup("Array containing the system status report data.")
    ),
    'has_warnings' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Has Warnings"),
      description: new TranslatableMarkup("Whether the system has warnings.")
    ),
    'has_errors' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup("Has Errors"),
      description: new TranslatableMarkup("Whether the system has errors.")
    ),
  ],
)]
final class SystemStatus extends ToolBase {

  /**
   * The system manager service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected SystemManager $systemManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->systemManager = $container->get('system.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    try {
      // Get the system requirements.
      $requirements = $this->systemManager->listRequirements();

      // Process the requirements to extract status information.
      $status_report = [];
      $has_warnings = FALSE;
      $has_errors = FALSE;

      foreach ($requirements as $requirement_key => $requirement) {
        $requirement['severity'] ??= RequirementSeverity::OK;
        $requirement['value'] ??= '';
        $requirement['description'] ??= '';
        if (is_int($requirement['severity'])) {
          $requirement['severity'] = RequirementSeverity::from($requirement['severity']);
        }
        $status_report[$requirement_key] = [
          'title' => (string) $requirement['title'],
          'value' => (string) $requirement['value'],
          'description' => $requirement['description'] ?? '',
          'severity' => [
            'value' => $requirement['severity']->value,
            'status' => $requirement['severity']->status(),
            'label' => (string) $requirement['severity']->title(),
          ],
        ];

        // Check for warnings and errors.
        $severity = $requirement['severity'] ?? REQUIREMENT_OK;
        if ($severity == REQUIREMENT_WARNING) {
          $has_warnings = TRUE;
        }
        elseif ($severity == REQUIREMENT_ERROR) {
          $has_errors = TRUE;
        }
      }

      $total_items = count($status_report);
      $message = $this->t('Successfully retrieved system status with @count items. Warnings: @warnings, Errors: @errors', [
        '@count' => $total_items,
        '@warnings' => $has_warnings ? 'Yes' : 'No',
        '@errors' => $has_errors ? 'Yes' : 'No',
      ]);

      return ExecutableResult::success($message, [
        'status_report' => $status_report,
        'has_warnings' => $has_warnings,
        'has_errors' => $has_errors,
      ]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error retrieving system status: @error', [
        '@error' => $e->getMessage(),
      ]), [
        'status_report' => [],
        'has_warnings' => FALSE,
        'has_errors' => FALSE,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    // Check if user has permission to access administration pages.
    $access = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
