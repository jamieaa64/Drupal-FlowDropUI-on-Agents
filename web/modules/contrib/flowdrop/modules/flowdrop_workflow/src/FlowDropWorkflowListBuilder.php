<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of FlowDropWorkflow entities.
 */
class FlowDropWorkflowListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Workflow');
    $header['description'] = $this->t('Description');
    $header['nodes'] = $this->t('Nodes');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Modified');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\flowdrop_workflow\Entity\FlowDropWorkflow $entity */
    $row['label'] = $entity->label();
    $row['description'] = $entity->getDescription() ?: $this->t('No description');
    $row['nodes'] = count($entity->getNodes());
    $row['created'] = $entity->getCreated() ? date('Y-m-d H:i:s', $entity->getCreated()) : $this->t('Unknown');
    $row['changed'] = $entity->getChanged() ? date('Y-m-d H:i:s', $entity->getChanged()) : $this->t('Unknown');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    return $operations;
  }

}
