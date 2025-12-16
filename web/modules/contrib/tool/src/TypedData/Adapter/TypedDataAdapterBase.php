<?php

declare(strict_types=1);

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Base class for data_type_adapter plugins.
 */
abstract class TypedDataAdapterBase extends PluginBase implements TypedDataAdapterInterface, ConfigurableInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateElement(array $element, TypedDataInterface $data, SubformStateInterface $form_state) {
    // @todo come back to clone part.
    //   $data = clone $data;
    $this->extractFormValues($data, $element, $form_state);
    $violations = $data->validate();
    foreach ($violations as $violation) {
      $error_element = $this->errorElement($element, $violation, $form_state);
      $form_state->setError($error_element, $violation->getMessage());
    }

  }

  /**
   * {@inheritdoc}
   */
  public function extractConfigurationValues(TypedDataInterface $data): void {
    if (isset($this->configuration['value'])) {
      $data->setValue($this->configuration['value']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, FormStateInterface $form_state) {
    return $element['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, array &$form, FormStateInterface $form_state): void {
    // Ensure empty values correctly end up as NULL value.
    $value = $form_state->getValue('value');
    if ($value === '') {
      $value = NULL;
    }
    $data->setValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

}
