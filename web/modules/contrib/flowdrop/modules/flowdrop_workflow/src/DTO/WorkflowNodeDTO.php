<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\DTO;

/**
 * Data Transfer Object for a workflow node.
 *
 * This DTO normalizes the node structure from the frontend JSON format
 * to a consistent internal representation used by the orchestrator.
 */
class WorkflowNodeDTO {

  /**
   * The unique node ID.
   *
   * @var string
   */
  private readonly string $id;

  /**
   * The node type ID (executor plugin ID).
   *
   * @var string
   */
  private readonly string $typeId;

  /**
   * The display label.
   *
   * @var string
   */
  private readonly string $label;

  /**
   * The node configuration.
   *
   * @var array<string, mixed>
   */
  private readonly array $config;

  /**
   * The node metadata.
   *
   * @var array<string, mixed>
   */
  private readonly array $metadata;

  /**
   * The node position.
   *
   * @var array{x: float, y: float}|null
   */
  private readonly ?array $position;

  /**
   * Input port definitions.
   *
   * @var array<int, array<string, mixed>>
   */
  private readonly array $inputs;

  /**
   * Output port definitions.
   *
   * @var array<int, array<string, mixed>>
   */
  private readonly array $outputs;

  /**
   * The original raw node data.
   *
   * @var array<string, mixed>
   */
  private readonly array $rawData;

  /**
   * Constructs a WorkflowNodeDTO.
   *
   * @param string $id
   *   The unique node ID.
   * @param string $typeId
   *   The node type ID (executor plugin ID).
   * @param string $label
   *   The display label.
   * @param array<string, mixed> $config
   *   The node configuration.
   * @param array<string, mixed> $metadata
   *   The node metadata.
   * @param array{x: float, y: float}|null $position
   *   The node position.
   * @param array<int, array<string, mixed>> $inputs
   *   Input port definitions.
   * @param array<int, array<string, mixed>> $outputs
   *   Output port definitions.
   * @param array<string, mixed> $rawData
   *   The original raw node data.
   */
  public function __construct(
    string $id,
    string $typeId,
    string $label,
    array $config,
    array $metadata,
    ?array $position,
    array $inputs,
    array $outputs,
    array $rawData,
  ) {
    $this->id = $id;
    $this->typeId = $typeId;
    $this->label = $label;
    $this->config = $config;
    $this->metadata = $metadata;
    $this->position = $position;
    $this->inputs = $inputs;
    $this->outputs = $outputs;
    $this->rawData = $rawData;
  }

  /**
   * Create a WorkflowNodeDTO from raw node data.
   *
   * This factory method handles the JSON structure from the frontend:
   * - node.id: The unique node ID
   * - node.type: UI component type (e.g., "universalNode") - NOT the executor
   * - node.data.label: Display label
   * - node.data.config: Node configuration
   * - node.data.metadata.id: Actual node type ID
   * - node.data.metadata.executor_plugin: Executor plugin ID
   * - node.data.metadata.inputs: Input port definitions
   * - node.data.metadata.outputs: Output port definitions.
   *
   * @param array<string, mixed> $nodeData
   *   The raw node data from workflow JSON.
   *
   * @return self
   *   The created WorkflowNodeDTO.
   */
  public static function fromArray(array $nodeData): self {
    $id = $nodeData['id'] ?? '';
    $data = $nodeData['data'] ?? [];
    $metadata = $data['metadata'] ?? [];

    // Get the actual node type ID - prefer executor_plugin, fall back to id.
    $typeId = $metadata['executor_plugin']
      ?? $metadata['id']
      ?? $nodeData['type']
      ?? '';

    // Get label from data.label, fall back to node ID.
    $label = $data['label'] ?? $metadata['name'] ?? $id;

    // Get config from data.config, merge with metadata.config defaults.
    $defaultConfig = $metadata['config'] ?? [];
    $config = array_merge($defaultConfig, $data['config'] ?? []);

    // Get position.
    $position = isset($nodeData['position']) ? [
      'x' => (float) ($nodeData['position']['x'] ?? 0),
      'y' => (float) ($nodeData['position']['y'] ?? 0),
    ] : NULL;

    // Get inputs and outputs from metadata.
    $inputs = $metadata['inputs'] ?? [];
    $outputs = $metadata['outputs'] ?? [];

    return new self(
      id: $id,
      typeId: $typeId,
      label: $label,
      config: $config,
      metadata: $metadata,
      position: $position,
      inputs: $inputs,
      outputs: $outputs,
      rawData: $nodeData,
    );
  }

  /**
   * Get the node ID.
   *
   * @return string
   *   The node ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get the node type ID (executor plugin ID).
   *
   * @return string
   *   The node type ID.
   */
  public function getTypeId(): string {
    return $this->typeId;
  }

  /**
   * Get the display label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * Get the node configuration.
   *
   * @return array<string, mixed>
   *   The configuration array.
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Get a specific config value.
   *
   * @param string $key
   *   The config key.
   * @param mixed $default
   *   The default value if key doesn't exist.
   *
   * @return mixed
   *   The config value.
   */
  public function getConfigValue(string $key, mixed $default = NULL): mixed {
    return $this->config[$key] ?? $default;
  }

  /**
   * Get the node metadata.
   *
   * @return array<string, mixed>
   *   The metadata array.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Get a specific metadata value.
   *
   * @param string $key
   *   The metadata key.
   * @param mixed $default
   *   The default value if key doesn't exist.
   *
   * @return mixed
   *   The metadata value.
   */
  public function getMetadataValue(string $key, mixed $default = NULL): mixed {
    return $this->metadata[$key] ?? $default;
  }

  /**
   * Get the node position.
   *
   * @return array{x: float, y: float}|null
   *   The position or NULL if not set.
   */
  public function getPosition(): ?array {
    return $this->position;
  }

  /**
   * Get input port definitions.
   *
   * @return array<int, array<string, mixed>>
   *   The input ports.
   */
  public function getInputs(): array {
    return $this->inputs;
  }

  /**
   * Get output port definitions.
   *
   * @return array<int, array<string, mixed>>
   *   The output ports.
   */
  public function getOutputs(): array {
    return $this->outputs;
  }

  /**
   * Check if this node has a trigger input.
   *
   * @return bool
   *   TRUE if the node has a trigger input.
   */
  public function hasTriggerInput(): bool {
    foreach ($this->inputs as $input) {
      $dataType = $input['dataType'] ?? '';
      if ($dataType === 'trigger') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check if this node is a gateway (has branching outputs).
   *
   * @return bool
   *   TRUE if the node is a gateway type.
   */
  public function isGateway(): bool {
    $type = $this->metadata['type'] ?? '';
    return $type === 'gateway';
  }

  /**
   * Get the category of this node.
   *
   * @return string
   *   The category (e.g., "inputs", "outputs", "models").
   */
  public function getCategory(): string {
    return $this->metadata['category'] ?? 'default';
  }

  /**
   * Get the original raw node data.
   *
   * @return array<string, mixed>
   *   The raw data.
   */
  public function getRawData(): array {
    return $this->rawData;
  }

  /**
   * Convert back to array format for serialization.
   *
   * @return array<string, mixed>
   *   Array representation.
   */
  public function toArray(): array {
    return [
      'id' => $this->id,
      'type_id' => $this->typeId,
      'label' => $this->label,
      'config' => $this->config,
      'metadata' => $this->metadata,
      'position' => $this->position,
      'inputs' => $this->inputs,
      'outputs' => $this->outputs,
    ];
  }

}
