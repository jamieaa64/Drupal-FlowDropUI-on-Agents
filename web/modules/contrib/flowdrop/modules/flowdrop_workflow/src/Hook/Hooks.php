<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the flowdrop_workflow module.
 */
final class Hooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) : array {
    return [
      'html__flowdrop_workflow_fullscreen' => [
        'render element' => 'elements',
        'base hook' => 'html',
        'template' => 'html--flowdrop-workflow-fullscreen',
      ],
      'page__flowdrop_workflow_editor' => [
        'render element' => 'elements',
        'base hook' => 'page',
        'template' => 'page--flowdrop-workflow-editor',
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_html_alter().
   */
  #[Hook('theme_suggestions_html_alter')]
  public function themeSuggestionsHtmlAlter(array &$suggestions, array $variables): void {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name == 'flowdrop_workflow.editor.workflow.entity') {
      $suggestions[] = 'html__flowdrop_workflow_fullscreen';
    }
  }

  /**
   * Implements hook_theme_suggestions_page_alter().
   */
  #[Hook('theme_suggestions_page_alter')]
  public function themeSuggestionsPageAlter(array &$suggestions, array $variables): void {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name == 'flowdrop_workflow.editor.workflow.entity') {
      $suggestions[] = 'page__flowdrop_workflow_editor';
    }
  }

}
