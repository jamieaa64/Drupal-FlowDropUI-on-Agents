<?php

declare(strict_types=1);

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Interface for data_type_adapter plugins.
 */
interface TypedDataAdapterInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Determines if the plugin is applicable for the given data definition.
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool;

  /**
   * Returns a form element for the given typed data.
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array;

  /**
   * Extracts form values into the given typed data.
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, SubformStateInterface $form_state): void;

}
