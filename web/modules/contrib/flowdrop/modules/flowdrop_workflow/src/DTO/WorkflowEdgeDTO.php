<?php

declare(strict_types=1);

namespace Drupal\flowdrop_workflow\DTO;

/**
 * Data Transfer Object for a workflow edge.
 *
 * This DTO normalizes the edge structure from the frontend JSON format
 * to a consistent internal representation used by the orchestrator.
 *
 * Edge handle format: {nodeId}-{direction}-{portName}
 * Examples:
 * - "text_input.1-output-text"
 * - "chat_model.1-input-message"
 * - "if_else.1-output-True" (gateway branch)
 * - "node.1-input-trigger" (trigger input)
 */
class WorkflowEdgeDTO {

  /**
   * The unique edge ID.
   *
   * @var string
   */
  private readonly string $id;

  /**
   * The source node ID.
   *
   * @var string
   */
  private readonly string $source;

  /**
   * The target node ID.
   *
   * @var string
   */
  private readonly string $target;

  /**
   * The source handle (port identifier).
   *
   * @var string
   */
  private readonly string $sourceHandle;

  /**
   * The target handle (port identifier).
   *
   * @var string
   */
  private readonly string $targetHandle;

  /**
   * Whether this is a trigger edge (controls execution flow).
   *
   * @var bool
   */
  private readonly bool $isTrigger;

  /**
   * The branch name for gateway outputs (e.g., "True", "False").
   *
   * @var string
   */
  private readonly string $branchName;

  /**
   * The source port name.
   *
   * @var string
   */
  private readonly string $sourcePortName;

  /**
   * The target port name.
   *
   * @var string
   */
  private readonly string $targetPortName;

  /**
   * Additional edge data from frontend.
   *
   * @var array<string, mixed>
   */
  private readonly array $data;

  /**
   * The original raw edge data.
   *
   * @var array<string, mixed>
   */
  private readonly array $rawData;

  /**
   * Constructs a WorkflowEdgeDTO.
   *
   * @param string $id
   *   The unique edge ID.
   * @param string $source
   *   The source node ID.
   * @param string $target
   *   The target node ID.
   * @param string $sourceHandle
   *   The source handle.
   * @param string $targetHandle
   *   The target handle.
   * @param bool $isTrigger
   *   Whether this is a trigger edge.
   * @param string $branchName
   *   The branch name for gateway outputs.
   * @param string $sourcePortName
   *   The source port name.
   * @param string $targetPortName
   *   The target port name.
   * @param array<string, mixed> $data
   *   Additional edge data.
   * @param array<string, mixed> $rawData
   *   The original raw edge data.
   */
  public function __construct(
    string $id,
    string $source,
    string $target,
    string $sourceHandle,
    string $targetHandle,
    bool $isTrigger,
    string $branchName,
    string $sourcePortName,
    string $targetPortName,
    array $data,
    array $rawData,
  ) {
    $this->id = $id;
    $this->source = $source;
    $this->target = $target;
    $this->sourceHandle = $sourceHandle;
    $this->targetHandle = $targetHandle;
    $this->isTrigger = $isTrigger;
    $this->branchName = $branchName;
    $this->sourcePortName = $sourcePortName;
    $this->targetPortName = $targetPortName;
    $this->data = $data;
    $this->rawData = $rawData;
  }

  /**
   * Create a WorkflowEdgeDTO from raw edge data.
   *
   * This factory method handles the JSON structure from the frontend:
   * - edge.id: Unique edge identifier
   * - edge.source: Source node ID
   * - edge.target: Target node ID
   * - edge.sourceHandle: Format "{nodeId}-output-{portName}"
   * - edge.targetHandle: Format "{nodeId}-input-{portName}"
   * - edge.data: Additional edge metadata.
   *
   * @param array<string, mixed> $edgeData
   *   The raw edge data from workflow JSON.
   *
   * @return self
   *   The created WorkflowEdgeDTO.
   */
  public static function fromArray(array $edgeData): self {
    $id = $edgeData['id'] ?? uniqid('edge_', TRUE);
    $source = $edgeData['source'] ?? '';
    $target = $edgeData['target'] ?? '';
    $sourceHandle = $edgeData['sourceHandle'] ?? '';
    $targetHandle = $edgeData['targetHandle'] ?? '';
    $data = $edgeData['data'] ?? [];

    // Determine if this is a trigger edge.
    // Trigger inputs have "-input-trigger" in the target handle.
    $isTrigger = str_contains($targetHandle, '-input-trigger');

    // Extract branch name from source handle.
    // Format: {nodeId}-output-{branchName}.
    $branchName = self::extractPortName($sourceHandle, 'output');

    // Extract port names.
    $sourcePortName = self::extractPortName($sourceHandle, 'output');
    $targetPortName = self::extractPortName($targetHandle, 'input');

    return new self(
      id: $id,
      source: $source,
      target: $target,
      sourceHandle: $sourceHandle,
      targetHandle: $targetHandle,
      isTrigger: $isTrigger,
      branchName: $branchName,
      sourcePortName: $sourcePortName,
      targetPortName: $targetPortName,
      data: $data,
      rawData: $edgeData,
    );
  }

  /**
   * Extract port name from a handle string.
   *
   * Handle format: {nodeId}-{direction}-{portName}
   * Example: "text_input.1-output-text" → "text"
   *
   * @param string $handle
   *   The handle string.
   * @param string $direction
   *   The direction ('output' or 'input').
   *
   * @return string
   *   The port name, or empty string if parsing failed.
   */
  private static function extractPortName(string $handle, string $direction): string {
    if (empty($handle)) {
      return '';
    }

    // Find the pattern: -{direction}-.
    $pattern = "/-{$direction}-/";
    $parts = preg_split($pattern, $handle, 2);

    if ($parts !== FALSE && count($parts) === 2) {
      return $parts[1];
    }

    return '';
  }

  /**
   * Get the edge ID.
   *
   * @return string
   *   The edge ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get the source node ID.
   *
   * @return string
   *   The source node ID.
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * Get the target node ID.
   *
   * @return string
   *   The target node ID.
   */
  public function getTarget(): string {
    return $this->target;
  }

  /**
   * Get the source handle.
   *
   * @return string
   *   The source handle.
   */
  public function getSourceHandle(): string {
    return $this->sourceHandle;
  }

  /**
   * Get the target handle.
   *
   * @return string
   *   The target handle.
   */
  public function getTargetHandle(): string {
    return $this->targetHandle;
  }

  /**
   * Check if this is a trigger edge.
   *
   * @return bool
   *   TRUE if this is a trigger edge.
   */
  public function isTrigger(): bool {
    return $this->isTrigger;
  }

  /**
   * Get the branch name (for gateway outputs).
   *
   * @return string
   *   The branch name (e.g., "True", "False").
   */
  public function getBranchName(): string {
    return $this->branchName;
  }

  /**
   * Get the source port name.
   *
   * @return string
   *   The source port name.
   */
  public function getSourcePortName(): string {
    return $this->sourcePortName;
  }

  /**
   * Get the target port name.
   *
   * @return string
   *   The target port name.
   */
  public function getTargetPortName(): string {
    return $this->targetPortName;
  }

  /**
   * Get the source output name (alias for getSourcePortName).
   *
   * This is used for mapping outputs in the execution plan.
   *
   * @return string
   *   The source output name.
   */
  public function getSourceOutputName(): string {
    return $this->sourcePortName;
  }

  /**
   * Get the target input name (alias for getTargetPortName).
   *
   * This is used for mapping inputs in the execution plan.
   *
   * @return string
   *   The target input name.
   */
  public function getTargetInputName(): string {
    return $this->targetPortName;
  }

  /**
   * Get additional edge data.
   *
   * @return array<string, mixed>
   *   The edge data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Get a specific data value.
   *
   * @param string $key
   *   The data key.
   * @param mixed $default
   *   The default value if key doesn't exist.
   *
   * @return mixed
   *   The data value.
   */
  public function getDataValue(string $key, mixed $default = NULL): mixed {
    return $this->data[$key] ?? $default;
  }

  /**
   * Get the original raw edge data.
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
      'source' => $this->source,
      'target' => $this->target,
      'source_handle' => $this->sourceHandle,
      'target_handle' => $this->targetHandle,
      'is_trigger' => $this->isTrigger,
      'branch_name' => $this->branchName,
      'source_port_name' => $this->sourcePortName,
      'target_port_name' => $this->targetPortName,
      'data' => $this->data,
    ];
  }

}
