<?php

namespace Drupal\tool\Tool;

/**
 * Interface for condition tools.
 */
interface ConditionToolInterface {

  /**
   * Defines the output definitions for the condition tool.
   */
  public static function defineOutputDefinitions(): array;

}
