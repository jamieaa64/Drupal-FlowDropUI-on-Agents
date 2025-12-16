<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\Service\Orchestrator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin manager for orchestrator services.
 *
 * This manager handles orchestrator services that are registered with
 * the 'flowdrop_runtime.orchestrator' service tag.
 */
class OrchestratorPluginManager implements ContainerInjectionInterface {

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Array of orchestrator service definitions.
   *
   * @var array
   */
  protected array $definitions = [];

  /**
   * Constructs a new OrchestratorPluginManager.
   */
  public function __construct(
    ContainerInterface $container,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->container = $container;
    $this->cacheBackend = $cache_backend;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container,
      $container->get('cache.discovery'),
      $container->get('module_handler')
    );
  }

  /**
   * Gets all orchestrator definitions.
   *
   * @return array
   *   Array of orchestrator definitions.
   */
  public function getDefinitions(): array {
    if (empty($this->definitions)) {
      $this->definitions = $this->buildDefinitions();
    }

    return $this->definitions;
  }

  /**
   * Creates an orchestrator instance.
   *
   * @param string $orchestrator_id
   *   The orchestrator ID.
   * @param array $configuration
   *   Optional configuration array.
   *
   * @return \Drupal\flowdrop_runtime\Service\Orchestrator\OrchestratorInterface
   *   The orchestrator instance.
   *
   * @throws \InvalidArgumentException
   *   When the orchestrator ID is not found.
   */
  public function createInstance(string $orchestrator_id, array $configuration = []): OrchestratorInterface {
    $definitions = $this->getDefinitions();

    if (!isset($definitions[$orchestrator_id])) {
      throw new \InvalidArgumentException("Orchestrator '{$orchestrator_id}' not found.");
    }

    $service_id = $definitions[$orchestrator_id]['service_id'];
    $orchestrator = $this->container->get($service_id);

    if (!$orchestrator instanceof OrchestratorInterface) {
      throw new \InvalidArgumentException("Service '{$service_id}' does not implement OrchestratorInterface.");
    }

    return $orchestrator;
  }

  /**
   * Checks if an orchestrator definition exists.
   *
   * @param string $orchestrator_id
   *   The orchestrator ID.
   *
   * @return bool
   *   TRUE if the definition exists, FALSE otherwise.
   */
  public function hasDefinition(string $orchestrator_id): bool {
    $definitions = $this->getDefinitions();
    return isset($definitions[$orchestrator_id]);
  }

  /**
   * Builds orchestrator definitions from tagged services.
   *
   * @return array
   *   Array of orchestrator definitions.
   */
  protected function buildDefinitions(): array {
    $definitions = [];

    // For now, we'll manually define the available orchestrators
    // In a real implementation, this would scan for tagged services.
    $definitions['synchronous'] = [
      'id' => 'synchronous',
      'label' => 'Synchronous Orchestrator',
      'description' => 'Executes workflows synchronously with real-time updates',
      'service_id' => 'flowdrop_runtime.synchronous_orchestrator',
      'class' => 'Drupal\flowdrop_runtime\Service\Orchestrator\SynchronousOrchestrator',
    ];

    $definitions['asynchronous'] = [
      'id' => 'asynchronous',
      'label' => 'Asynchronous Orchestrator',
      'description' => 'Executes workflows asynchronously using queue system',
      'service_id' => 'flowdrop_runtime.asynchronous_orchestrator',
      'class' => 'Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator',
    ];

    return $definitions;
  }

}
