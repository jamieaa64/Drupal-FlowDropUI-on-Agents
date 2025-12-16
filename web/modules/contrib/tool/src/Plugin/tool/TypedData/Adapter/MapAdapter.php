<?php

declare(strict_types=1);

namespace Drupal\tool\Plugin\tool\TypedData\Adapter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterBase;
use Drupal\tool\Attribute\TypedDataAdapter;
use Drupal\tool\TypedData\Adapter\TypedDataAdapterTrait;

/**
 * Plugin implementation of the data_type_adapter.
 */
#[TypedDataAdapter(
  id: 'map',
  label: new TranslatableMarkup('Map'),
  description: new TranslatableMarkup('Map inputs for map data types.'),
)]
final class MapAdapter extends TypedDataAdapterBase {

  use TypedDataAdapterTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(DataDefinitionInterface $data_definition): bool {
    return $data_definition->getDataType() === 'map';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(TypedDataInterface $data, array $element, SubformStateInterface $form_state): array {
    $element['value'] = [
      '#type' => 'container',
    ];
    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data->getDataDefinition();
    foreach ($map_definition->getPropertyDefinitions() as $property_name => $property_definition) {
      $adapter = $this->getAdapterInstance($property_definition, $property_name);
      $property_data = $property_definition->getClass()::createInstance($property_definition);
      if (!isset($element['value'][$property_name])) {
        $element['value'][$property_name] = [];
      }
      $property_form_state = SubformState::createForSubform($element['value'][$property_name], $element, $form_state);
      $element['value'][$property_name] = $adapter->formElement($property_data, $element['value'][$property_name], $property_form_state);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, FormStateInterface $form_state): void {
    $result = [];
    /** @var \Drupal\Core\TypedData\MapDataDefinition $map_definition */
    $map_definition = $data->getDataDefinition();
    foreach ($map_definition->getPropertyDefinitions() as $property_name => $property_definition) {
      $adapter = $this->getAdapterInstance($property_definition, $property_name);
      $property_data = $property_definition->getClass()::createInstance($property_definition);
      $property_form_state = SubformState::createForSubform($form['value'][$property_name], $form, $form_state);
      $form[$property_name] = $adapter->formElement($property_data, $form['value'][$property_name], $property_form_state);
      $adapter->extractFormValues($property_data, $form['value'][$property_name], $property_form_state);
      $result[$property_name] = $property_data->getValue();
    }
    $data->setValue($result);
  }

}
