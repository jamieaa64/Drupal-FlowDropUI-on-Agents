<?php

declare(strict_types=1);

namespace Drupal\flowdrop\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * FlowDrop hooks implementation.
 */
class FlowDropHooks {

  /**
   * Implements hook_form_alter().
   */
  #[Hook('flowdrop_node_processor_info_alter')]
  public function flowdropNodeProcessorInfoAlter(array &$data) : void {}

}
