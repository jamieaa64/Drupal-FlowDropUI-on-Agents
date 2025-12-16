<?php

declare(strict_types=1);

namespace Drupal\flowdrop_job;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of flowdrop job type entities.
 *
 * @see \Drupal\flowdrop_job\Entity\FlowDropJobType
 */
final class FlowDropJobTypeListBuilder extends ConfigEntityListBuilder {

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
      'No flowdrop job types available. <a href=":link">Add flowdrop job type</a>.',
      [':link' => Url::fromRoute('entity.flowdrop_job_type.add_form')->toString()],
    );

    return $build;
  }

}
