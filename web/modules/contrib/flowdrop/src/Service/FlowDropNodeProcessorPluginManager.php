<?php

declare(strict_types=1);

namespace Drupal\flowdrop\Service;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\AttributeClassDiscovery;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\FlowDropNodeProcessorInterface;

/**
 * Plugin manager for FlowDropNode plugins using attribute-based discovery.
 */
class FlowDropNodeProcessorPluginManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/FlowDropNodeProcessor',
      $namespaces,
      $module_handler,
      FlowDropNodeProcessorInterface::class,
      FlowDropNodeProcessor::class
    );
    $this->setCacheBackend($cache_backend, 'flowdrop_node_processor_plugins');
    $this->alterInfo('flowdrop_node_processor_info');
    $this->discovery = new AttributeClassDiscovery(
      'Plugin/FlowDropNodeProcessor',
      $namespaces,
      FlowDropNodeProcessor::class
    );
    $this->factory = new ContainerFactory($this);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    // Use the container factory to properly inject dependencies.
    return $this->factory->createInstance($plugin_id, $configuration);
  }

}
