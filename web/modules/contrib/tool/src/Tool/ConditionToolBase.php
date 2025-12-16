<?php

namespace Drupal\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for condition tools.
 */
abstract class ConditionToolBase extends ToolBase implements ConditionToolInterface {

  /**
   * {@inheritdoc}
   */
  public static function defineOutputDefinitions(): array {
    return [
      'result' => new ContextDefinition(
        data_type: 'boolean',
        label: new TranslatableMarkup('Result'),
        description: new TranslatableMarkup('True/False result of the condition check.'),
      ),
    ];
  }

}
