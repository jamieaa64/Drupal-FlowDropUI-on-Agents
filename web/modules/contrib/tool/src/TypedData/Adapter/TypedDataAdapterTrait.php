<?php

namespace Drupal\tool\TypedData\Adapter;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataTrait;

/**
 * Trait for typed data adapters.
 */
trait TypedDataAdapterTrait {
  use TypedDataTrait;

  /**
   * The adapter plugin manager.
   *
   * @var \Drupal\tool\TypedData\Adapter\TypedDataAdapterManager
   */
  protected TypedDataAdapterManager $adapterManager;

  /**
   * The adapter plugin instances.
   *
   * @var array
   */
  protected array $adapterInstances = [];

  /**
   * Returns the adapter manager.
   */
  protected function getAdapterManager() {
    if (empty($this->adapterManager)) {
      $this->adapterManager = \Drupal::service('plugin.manager.tool.typed_data_adapter');
    }
    return $this->adapterManager;
  }

  /**
   * Gets the adapter instance for a given data definition.
   */
  public function getAdapterInstance(DataDefinitionInterface $data_definition, string $name) {
    if (!isset($this->adapterInstances[$name])) {
      $adapter_definition = $this->getAdapterManager()->getDefinitionFromDataDefinition($data_definition);
      $this->adapterInstances[$name] = $this->getAdapterManager()->createInstance($adapter_definition['id']);
    }
    return $this->adapterInstances[$name];
  }

}
