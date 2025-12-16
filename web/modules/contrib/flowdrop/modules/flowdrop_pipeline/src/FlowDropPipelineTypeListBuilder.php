<?php

declare(strict_types=1);

namespace Drupal\flowdrop_pipeline;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of flowdrop pipeline type entities.
 *
 * @see \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineType
 */
final class FlowDropPipelineTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No flowdrop pipeline types available. <a href=":link">Add flowdrop pipeline type</a>.',
      [':link' => Url::fromRoute('entity.flowdrop_pipeline_type.add_form')->toString()],
    );

    return $build;
  }

}
