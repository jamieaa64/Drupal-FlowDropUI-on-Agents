<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\tool\TypedData\Adapter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterBase;
use Drupal\tool\Attribute\TypedDataAdapter;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterTrait;

/**
 * Plugin implementation for handling multiple values (lists).
 */
#[TypedDataAdapter(
  id: 'list',
  label: new TranslatableMarkup('List'),
  description: new TranslatableMarkup('Adapter for handling multiple values in list data types.'),
// Higher priority for list items.
  weight: -10,
)]
final class ListAdapter extends TypedDataAdapterBase {
  use TypedDataAdapterTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    return $data_definition->getDataType() === 'list';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    $element['#typed_data'] = $data;
    // There is some discrepancy between how propertyDefinitions are handled
    // during serialization(vs 'list' itemDefinition), so we store them here for
    // map item definitions.
    // @see https://www.drupal.org/project/drupal/issues/3557455
    /** @var \Drupal\Core\TypedData\ListDataDefinitionInterface $list_definition */
    $list_definition = $data->getDataDefinition();
    $item_definition = $list_definition->getItemDefinition();
    if ($item_definition instanceof MapDataDefinition) {
      $element['#typed_data_properties'] = $item_definition->getPropertyDefinitions();
    }
    $element['#process'][] = [$this, 'processListAdapter'];
    return $element;
  }

  /**
   * Form process callback for the list adapter.
   */
  public function processListAdapter(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $data = $element['#typed_data'];
    $list_definition = $data->getDataDefinition();
    $item_definition = $list_definition->getItemDefinition();

    // There is some discrepancy between how propertyDefinitions are handled
    // during serialization(vs 'list' itemDefinition), so we get them here for
    // map item definitions.
    // @see https://www.drupal.org/project/drupal/issues/3557455
    if ($item_definition->getDataType() == 'map'
      && !empty($element['#typed_data_properties'])
      && !$item_definition->getPropertyDefinitions()) {
      foreach ($element['#typed_data_properties'] as $property_name => $property_definition) {
        $item_definition->setPropertyDefinition($property_name, $property_definition);
      }
    }

    // Generate unique field name for form state storage.
    $name = implode('_', $element['#array_parents']);

    // Get or initialize the item count in form state.
    $items_count = $form_state->get(['list_adapter_items_count', $name]);
    if ($items_count === NULL) {
      // Initial state - count existing items in data.
      $items_count = count($data);
      // Always show at least one item (empty).
      if ($items_count === 0) {
        $items_count = 1;
      }
      $form_state->set(['list_adapter_items_count', $name], $items_count);
    }

    // Check if there's a delta to remove from this rebuild.
    $remove_delta = $form_state->get(['list_adapter_remove_delta', $name]);

    // Get values from the original data source.
    $values = [];
    $delta = 0;
    $source_delta = 0;
    foreach ($data as $item) {
      // Skip the delta that was marked for removal.
      if ($remove_delta !== NULL && $source_delta === $remove_delta) {
        $source_delta++;
        // Clear the remove flag after processing.
        $form_state->set(['list_adapter_remove_delta', $name], NULL);
        continue;
      }
      $values[$delta] = $item;
      $delta++;
      $source_delta++;
    }

    // Fill remaining slots with empty items up to items_count.
    $max_items = $list_definition->getSetting('max_items') ?? NULL;
    while ($delta < $items_count && ($max_items === NULL || $delta < $max_items)) {
      $new_item = $item_definition->getClass()::createInstance($item_definition);
      $values[$delta] = $new_item;
      $delta++;
    }
    $element['#tree'] = TRUE;
    $element['#type'] = 'fieldset';
    $element['#title'] = $list_definition->getLabel();
    $element['#description'] = $list_definition->getDescription();
    $element['#prefix'] = '<div id="' . $name . '">';
    $element['#suffix'] = '</div>';

    // Create form elements for each item.
    foreach ($values as $delta => $item) {
      $element['items'][$delta] = [
        '#type' => 'container',
        'value' => [],
      ];

      // Get the appropriate adapter for the item type.
      $item_adapter = $this->getAdapterInstance($item_definition, "items][{$delta}");
      $item_form_state = SubformState::createForSubform($element['items'][$delta], $element, $form_state);

      $element['items'][$delta] = $item_adapter->formElement($item, $element['items'][$delta], $item_form_state);

      // Build validation parents for limit_validation_errors.
      $validation_parents = $element['#array_parents'];
      $validation_parents[] = 'items';
      $validation_parents[] = $delta;

      // Add remove button for existing items (except the last empty one).
      $element['items'][$delta]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => "remove_item_{$name}_{$delta}",
        '#submit' => [[$this, 'removeItem']],
        '#ajax' => [
          'callback' => [$this, 'removeItemAjax'],
          'wrapper' => $name,
        ],
        '#delta' => $delta,
        '#limit_validation_errors' => [$validation_parents],
      ];
    }

    // Build validation parents for the add more button.
    $validation_parents = $element['#array_parents'];
    $validation_parents[] = 'add_more';

    // Add "Add more" button if not at max capacity.
    if ($max_items === NULL || count($values) - 1 < $max_items) {
      $element['add_more'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add another item'),
        '#name' => "add_more_{$name}",
        '#submit' => [[$this, 'addMore']],
        '#ajax' => [
          'callback' => [$this, 'addMoreAjax'],
          'wrapper' => $name,
        ],
        '#limit_validation_errors' => [$validation_parents],
      ];
    }
    return $element;
  }

  /**
   * Submit handler for "Add more" button.
   */
  public function addMore(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $name = implode('_', $parents);

    // Increment the item count.
    $items_count = $form_state->get(['list_adapter_items_count', $name]) ?? 1;
    $form_state->set(['list_adapter_items_count', $name], $items_count + 1);

    // Trigger form rebuild to add another item.
    $form_state->setRebuild();
  }

  /**
   * Submit handler for "Remove" button.
   */
  public function removeItem(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#delta'];
    $list_parents = array_slice($triggering_element['#array_parents'], 0, -3);
    $name = implode('_', $list_parents);
    // Track which delta to remove.
    $form_state->set(['list_adapter_remove_delta', $name], $delta);

    // Decrement the item count.
    $items_count = $form_state->get(['list_adapter_items_count', $name]) ?? 1;
    if ($items_count > 1) {
      $form_state->set(['list_adapter_items_count', $name], $items_count - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * AJAX callback for add operations.
   */
  public function addMoreAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * AJAX callback for remove operations.
   */
  public function removeItemAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#array_parents'], 0, -3);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    /** @var \Drupal\Core\TypedData\ListDataDefinitionInterface $list_definition */
    $list_definition = $data->getDataDefinition();
    $item_definition = $list_definition->getItemDefinition();
    $result = [];
    foreach ($values['items'] as $delta => $item_value) {
      // Get the appropriate adapter for the item type.
      $item_adapter = $this->getAdapterInstance($item_definition, "items][{$delta}");
      $item_form_state = SubformState::createForSubform($form['items'][$delta], $form, $form_state);
      $item_data = $item_definition->getClass()::createInstance($item_definition);
      $item_adapter->extractFormValues($item_data, $form['items'][$delta], $item_form_state);
      $result[] = $item_data->getValue();
    }
    $data->setValue($result);
  }

}
