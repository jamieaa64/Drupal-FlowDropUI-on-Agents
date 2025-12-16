<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * TypedData container for FlowDrop Agent models.
 *
 * @see \Drupal\flowdrop_ai_provider\TypedData\FlowDropAgentModelDefinition
 */
#[DataType(
  id: 'flowdrop_agent_model',
  label: new TranslatableMarkup('FlowDrop Agent Model'),
  definition_class: FlowDropAgentModelDefinition::class,
)]
class FlowDropAgentModel extends Map {

  /**
   * Gets the model name/label.
   *
   * @return string
   *   The model name.
   */
  public function getName(): string {
    return $this->get('label')->getValue() ?? '';
  }

  /**
   * Gets the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getModelId(): string {
    return $this->get('model_id')->getValue() ?? '';
  }

  /**
   * Gets the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt(): string {
    return $this->get('system_prompt')->getValue() ?? '';
  }

  /**
   * Gets the list of agents.
   *
   * @return array
   *   The agents array.
   */
  public function getAgents(): array {
    $agents = $this->get('agents');
    return $agents ? $agents->getValue() ?? [] : [];
  }

  /**
   * Gets the list of tools.
   *
   * @return array
   *   The tools array.
   */
  public function getTools(): array {
    $tools = $this->get('tools');
    return $tools ? $tools->getValue() ?? [] : [];
  }

  /**
   * Gets the edges (connections).
   *
   * @return array
   *   The edges array.
   */
  public function getEdges(): array {
    $edges = $this->get('edges');
    return $edges ? $edges->getValue() ?? [] : [];
  }

  /**
   * Checks if this is an orchestration agent.
   *
   * @return bool
   *   TRUE if orchestration agent.
   */
  public function isOrchestrationAgent(): bool {
    return (bool) ($this->get('orchestration_agent')->getValue() ?? FALSE);
  }

  /**
   * Checks if this is a triage agent.
   *
   * @return bool
   *   TRUE if triage agent.
   */
  public function isTriageAgent(): bool {
    return (bool) ($this->get('triage_agent')->getValue() ?? FALSE);
  }

  /**
   * Gets the UI metadata.
   *
   * @return array
   *   The UI metadata array.
   */
  public function getUiMetadata(): array {
    $metadata = $this->get('ui_metadata');
    return $metadata ? $metadata->getValue() ?? [] : [];
  }

}
