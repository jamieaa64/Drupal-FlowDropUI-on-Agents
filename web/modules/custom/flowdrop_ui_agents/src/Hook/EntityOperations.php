<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Entity operation hooks for FlowDrop UI Agents.
 */
class EntityOperations {

  /**
   * Constructs the EntityOperations hook handler.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_entity_operation().
   *
   * Adds "Edit with FlowDrop for AI Agents" for:
   * - ai_agent entities (always, even when that modeler is already stored)
   * - ai_assistant entities (to edit their linked ai_agent)
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    $entityType = $entity->getEntityTypeId();

    // Check permission for AI Agent editing.
    $permission = 'modeler api edit ai_agent with flowdrop_agents';
    if (!$this->currentUser->hasPermission($permission)) {
      return $operations;
    }

    if ($entityType === 'ai_agent') {
      // Always add "Edit with FlowDrop for AI Agents" option for AI Agents.
      $operations['edit_flowdrop_agents'] = [
        'title' => t('Edit with FlowDrop for AI Agents'),
        'url' => Url::fromRoute('entity.ai_agent.edit_with.flowdrop_agents', [
          'ai_agent' => $entity->id(),
        ]),
        'weight' => 10,
      ];
    }
    elseif ($entityType === 'ai_assistant') {
      // For AI Assistants, check if they have a linked AI Agent.
      $agentId = $entity->get('ai_agent');
      if ($agentId) {
        // Verify the linked agent exists.
        $agent = $this->entityTypeManager->getStorage('ai_agent')->load($agentId);
        if ($agent) {
          // Add option to edit the assistant (which includes its agent).
          $operations['edit_flowdrop_agents'] = [
            'title' => t('Edit with FlowDrop for AI Agents'),
            'url' => Url::fromRoute('flowdrop_ui_agents.assistant.edit', [
              'ai_assistant' => $entity->id(),
            ]),
            'weight' => 10,
          ];
        }
      }
    }

    return $operations;
  }

}
