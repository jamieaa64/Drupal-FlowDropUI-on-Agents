<?php

declare(strict_types=1);

namespace Drupal\flowdrop_runtime\DTO\Compiler;

/**
 * Represents a node's mapping to a processor plugin.
 */
class NodeMapping {

  public function __construct(
    private readonly string $nodeId,
    private readonly string $processorId,
    private readonly array $config,
    private readonly array $metadata,
  ) {}

  /**
   * Get the node ID.
   *
   * @return string
   *   The node ID.
   */
  public function getNodeId(): string {
    return $this->nodeId;
  }

  /**
   * Get the processor plugin ID.
   *
   * @return string
   *   The processor plugin ID.
   */
  public function getProcessorId(): string {
    return $this->processorId;
  }

  /**
   * Get the node configuration.
   *
   * @return array
   *   The node configuration.
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Get the metadata.
   *
   * @return array
   *   The metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Get the node label.
   *
   * @return string
   *   The node label.
   */
  public function getLabel(): string {
    return $this->metadata['label'] ?? $this->nodeId;
  }

  /**
   * Get the node description.
   *
   * @return string
   *   The node description.
   */
  public function getDescription(): string {
    return $this->metadata['description'] ?? '';
  }

  /**
   * Convert to array for serialization.
   *
   * @return array
   *   Array representation of the node mapping.
   */
  public function toArray(): array {
    return [
      'node_id' => $this->nodeId,
      'processor_id' => $this->processorId,
      'config' => $this->config,
      'metadata' => $this->metadata,
    ];
  }

}
