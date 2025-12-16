<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_category\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flowdrop_node_category\Entity\FlowDropNodeCategory;
use Drupal\flowdrop_node_category\FlowDropNodeCategoryInterface;

/**
 * FlowDrop Node Category form.
 */
final class FlowDropNodeCategoryForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);
    if (!$this->entity instanceof FlowDropNodeCategoryInterface) {
      return $form;
    }
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [FlowDropNodeCategory::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['icon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon'),
      '#default_value' => $this->entity->getIcon(),
      '#description' => $this->t('The icon identifier (e.g., mdi:cog, mdi:text).'),
    ];

    $form['color'] = [
      '#type' => 'color',
      '#title' => $this->t('Color'),
      '#default_value' => $this->entity->getColor(),
      '#description' => $this->t('The color for this node type.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('A brief description of what this category does.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        default => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
