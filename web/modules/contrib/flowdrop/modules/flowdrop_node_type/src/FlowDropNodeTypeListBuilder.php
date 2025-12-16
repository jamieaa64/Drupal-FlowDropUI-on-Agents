<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_type;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of flowdrop node types.
 */
final class FlowDropNodeTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['category'] = $this->t('Category');
    $header['version'] = $this->t('Version');
    $header['enabled'] = $this->t('Enabled');
    $header['executor_plugin'] = $this->t('Executor Plugin');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $row['category'] = $this->t('@category', ['@category' => ucfirst($entity->getCategory())]);
    $row['version'] = $entity->getVersion();
    $row['enabled'] = $entity->isEnabled() ? $this->t('Yes') : $this->t('No');
    $row['executor_plugin'] = $entity->getExecutorPlugin() ?: $this->t('None');
    return $row + parent::buildRow($entity);
  }

}
