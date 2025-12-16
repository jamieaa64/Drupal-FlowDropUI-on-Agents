<?php

declare(strict_types=1);

namespace Drupal\flowdrop\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Drupal\flowdrop\DTO\OutputInterface;

/**
 * Interface for node executors.
 *
 * This interface defines the contract for node processors that
 * execute workflow nodes using strongly-typed DTOs for better
 * type safety and serialization.
 */
interface NodeExecutorInterface {

  /**
   * Execute a node with given inputs and configuration.
   *
   * @param \Drupal\flowdrop\DTO\InputInterface $inputs
   *   The inputs for the node.
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The configuration for the node.
   *
   * @return \Drupal\flowdrop\DTO\OutputInterface
   *   The execution result.
   *
   * @throws \Exception
   *   When execution fails.
   */
  public function execute(InputInterface $inputs, ConfigInterface $config): OutputInterface;

}
