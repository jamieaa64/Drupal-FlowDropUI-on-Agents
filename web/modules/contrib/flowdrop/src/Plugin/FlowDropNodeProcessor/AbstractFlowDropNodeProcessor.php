<?php

declare(strict_types=1);

namespace Drupal\flowdrop\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Drupal\flowdrop\DTO\OutputInterface;
use Drupal\flowdrop\DTO\Output;

/**
 * Abstract base class for FlowDropNode plugins.
 *
 * This class provides common functionality for FlowDropNode plugins and
 * implements default behavior for the FlowDropNodeInterface methods.
 */
abstract class AbstractFlowDropNodeProcessor extends PluginBase implements FlowDropNodeProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * Get Logger Channel.
   */
  abstract protected function getLogger(): LoggerChannelInterface;

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'data' => [
          'type' => 'mixed',
          'title' => 'Input Data',
          'description' => 'Input data for the node',
          'required' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'data' => [
          'type' => 'mixed',
          'description' => 'Output data from the node',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->pluginDefinition["type"] ?? "default";
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginDefinition["id"] ?? $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->pluginDefinition["label"] ?? $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->pluginDefinition["description"] ?? "";
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory(): string {
    return $this->pluginDefinition["category"] ?? "processing";
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->pluginDefinition["version"] ?? "1.0.0";
  }

  /**
   * {@inheritdoc}
   */
  public function getInputs(): array {
    return $this->pluginDefinition["inputs"] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputs(): array {
    return $this->pluginDefinition["outputs"] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): array {
    return $this->pluginDefinition["config"] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    return $this->pluginDefinition["tags"] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $inputs, ConfigInterface $config): OutputInterface {
    $this->getLogger()->info("Executing node @id", ["@id" => $this->getPluginId()]);

    try {
      $result = $this->process($inputs, $config);
      $output = new Output();
      $output->setStatus('success');

      // Convert the result to output format.
      $output->fromArray($result);

      return $output;
    }
    catch (\Exception $e) {
      $this->getLogger()->error("Node execution failed: @error", ["@error" => $e->getMessage()]);

      $output = new Output();
      $output->setError($e->getMessage());

      return $output;
    }
  }

  /**
   * Process the node data.
   *
   * This is the template method that subclasses must implement.
   * The execute() method handles the common execution logic,
   * while this method contains the specific node processing logic.
   *
   * @param \Drupal\flowdrop\DTO\InputInterface $inputs
   *   The input data to process.
   * @param \Drupal\flowdrop\DTO\ConfigInterface $config
   *   The configuration data.
   *
   * @return array
   *   The processed result as an array.
   */
  abstract protected function process(InputInterface $inputs, ConfigInterface $config): array;

}
