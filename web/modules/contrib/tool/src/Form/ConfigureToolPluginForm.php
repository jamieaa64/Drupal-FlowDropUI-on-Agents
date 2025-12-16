<?php

namespace Drupal\tool\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for configuring tool plugins.
 */
class ConfigureToolPluginForm extends ToolPluginFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitConfigurationForm() method.
  }

}
