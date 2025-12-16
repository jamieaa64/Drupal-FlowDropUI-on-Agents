<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines the schema for FlowDrop Agent models.
 *
 * This TypedData definition represents the structure of a FlowDrop workflow
 * that maps to AI Agent configuration entities.
 *
 * @see \Drupal\ai_integration_eca_agents\TypedData\EcaModelDefinition
 */
class FlowDropAgentModelDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(): array {
    if (!isset($this->propertyDefinitions)) {
      $this->propertyDefinitions = [];

      // Primary identifier for the model (maps to agent ID).
      $this->propertyDefinitions['model_id'] = DataDefinition::create('string')
        ->setLabel('Model ID')
        ->setDescription('Unique identifier for the agent model')
        ->setRequired(TRUE)
        ->addConstraint('Regex', ['pattern' => '/^[\w]+$/']);

      // Human-readable label.
      $this->propertyDefinitions['label'] = DataDefinition::create('string')
        ->setLabel('Label')
        ->setDescription('Human-readable name for the agent')
        ->setRequired(TRUE)
        ->addConstraint('Length', ['max' => 255])
        ->addConstraint('NotBlank');

      // Description used by orchestration/triage agents.
      $this->propertyDefinitions['description'] = DataDefinition::create('string')
        ->setLabel('Description')
        ->setDescription('Description used by triage agents to select this agent')
        ->setRequired(TRUE);

      // System prompt for the agent.
      $this->propertyDefinitions['system_prompt'] = DataDefinition::create('string')
        ->setLabel('System Prompt')
        ->setDescription('Core instructions for agent behavior')
        ->setRequired(TRUE);

      // List of agent nodes in the workflow.
      $this->propertyDefinitions['agents'] = ListDataDefinition::create('map')
        ->setLabel('Agents')
        ->setDescription('AI Agent nodes in the workflow')
        ->setRequired(FALSE);

      // List of tool references attached to agents.
      $this->propertyDefinitions['tools'] = ListDataDefinition::create('map')
        ->setLabel('Tools')
        ->setDescription('Tools attached to agents')
        ->setRequired(FALSE);

      // Flow edges defining connections between nodes.
      $this->propertyDefinitions['edges'] = ListDataDefinition::create('map')
        ->setLabel('Edges')
        ->setDescription('Connections between nodes')
        ->setRequired(FALSE);

      // Agent type flags.
      $this->propertyDefinitions['orchestration_agent'] = DataDefinition::create('boolean')
        ->setLabel('Orchestration Agent')
        ->setDescription('If true, agent only picks other agents for work')
        ->setRequired(FALSE);

      $this->propertyDefinitions['triage_agent'] = DataDefinition::create('boolean')
        ->setLabel('Triage Agent')
        ->setDescription('If true, agent can pick other agents AND do own work')
        ->setRequired(FALSE);

      // Execution settings.
      $this->propertyDefinitions['max_loops'] = DataDefinition::create('integer')
        ->setLabel('Max Loops')
        ->setDescription('Maximum iterations before stopping')
        ->setRequired(FALSE);

      // UI metadata for positions.
      $this->propertyDefinitions['ui_metadata'] = MapDataDefinition::create()
        ->setLabel('UI Metadata')
        ->setDescription('Visual editor metadata (positions, etc.)')
        ->setRequired(FALSE);
    }

    return $this->propertyDefinitions;
  }

}
