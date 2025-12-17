# Multi-Agent Visualization Design

## Overview

Enable FlowDrop UI to display and edit nested agent hierarchies, where an agent can have sub-agents as tools.

**Example hierarchy:**
```
bundle_lister_assistant (orchestration_agent)
└── tool: ai_agents::ai_agent::agent_bundle_lister
    └── agent_bundle_lister
        └── tool: tool:entity_bundle_list
```

## View Settings

Two independent toggles in the FlowDrop navbar (dropdowns):

### 1. Layout Toggle
- **Normal**: Full node display with all input/output ports visible
- **Compact**: Simplified n8n-style nodes showing just icon and name

Implementation: JavaScript-only, sets `node.data.config.layout` property on all nodes.

### 2. Agent Expansion Toggle
- **Expanded**: Flat view - sub-agents and their tools appear as regular nodes on canvas
- **Grouped**: Sub-agents shown as visual container boxes with their tools inside
- **Collapsed**: Sub-agents shown as single node with badge (e.g., "Agent Bundle Lister [1 tool]")

Implementation: AJAX call to re-fetch workflow data with different expansion mode.

## Visual Examples

```
EXPANDED:                    GROUPED:                     COLLAPSED:
┌─────────────────┐         ┌─────────────────┐          ┌─────────────────┐
│ Bundle Lister   │         │ Bundle Lister   │          │ Bundle Lister   │
│ Assistant       │         │ Assistant       │          │ Assistant       │
└────────┬────────┘         └────────┬────────┘          └────────┬────────┘
         │                           │                            │
         ▼                           ▼                            ▼
┌─────────────────┐         ┌─────────────────────┐      ┌─────────────────┐
│ Agent Bundle    │         │ Agent Bundle Lister │      │ Agent Bundle    │
│ Lister          │         │ ┌─────────────────┐ │      │ Lister [1 tool] │
└────────┬────────┘         │ │ entity_bundle   │ │      └─────────────────┘
         │                  │ │ _list           │ │
         ▼                  │ └─────────────────┘ │
┌─────────────────┐         └─────────────────────┘
│ entity_bundle   │
│ _list           │
└─────────────────┘
```

## Data Model

### JavaScript State (no persistence)
```javascript
const viewSettings = {
  nodeLayout: 'normal',      // 'compact' | 'normal'
  agentExpansion: 'expanded' // 'expanded' | 'grouped' | 'collapsed'
};
```

### Node Metadata
Each node tracks its owner agent:
```javascript
node.data.metadata.ownerAgentId = 'agent_bundle_lister'
```

## Backend Changes

### AgentWorkflowMapper.php

New method signature:
```php
public function agentToWorkflow(
  ConfigEntityInterface $agent,
  string $expansionMode = 'expanded',
  int $depth = 0,
  int $maxDepth = 3
): array
```

Sub-agent detection:
```php
if (str_starts_with($toolId, 'ai_agents::ai_agent::')) {
  $subAgentId = str_replace('ai_agents::ai_agent::', '', $toolId);
  // Handle based on expansionMode
}
```

### New API Endpoint

```
GET /api/flowdrop-agents/workflow/{agent_id}?expansion={mode}
```

Returns workflow data with specified expansion mode.

## Save Flow (Multi-Entity)

### What Can Be Created in UI
- New agents: YES (can create inline and connect)
- New tools: NO (drag existing tools from sidebar)

### Save Order
Agents saved in dependency order (topological sort):
1. Leaf agents (no sub-agents) saved first
2. Intermediate agents saved next
3. Parent agents saved last

### WorkflowParser Changes
```php
public function toComponents(array $workflow): array {
  // Group nodes by ownerAgentId
  $nodesByAgent = $this->groupNodesByOwner($workflow['nodes']);

  // Sort by dependency (leaf first)
  $sortedAgents = $this->topologicalSort($nodesByAgent);

  // Create components for each agent
  foreach ($sortedAgents as $agentId => $agentNodes) {
    $components[] = $this->createAgentComponent($agentId, $agentNodes);
  }

  return $components;
}
```

### Safety Measures
- Only save agents that were loaded (prevent accidental creation)
- Track dirty state per agent
- Validate user has permission to edit each agent

## UI Controls

Navbar layout:
```
┌─────────────────────────────────────────────────────────────────────────┐
│ AI Agent Name          [Layout: ▼] [Agents: ▼]  [Save AI Agent] [Back] │
└─────────────────────────────────────────────────────────────────────────┘
```

Dropdown options:
- Layout: "Normal" / "Compact"
- Agents: "Expanded" / "Grouped" / "Collapsed"

## Implementation Phases

### Phase 1: View Toggles (UI only)
- Add dropdown controls to navbar
- Implement layout toggle (JS-only, immediate)
- Implement agent expansion toggle (triggers re-fetch)

### Phase 2: Expanded Mode
- Update AgentWorkflowMapper to recursively load sub-agents
- Add ownerAgentId to node metadata
- New API endpoint for workflow with expansion param

### Phase 3: Grouped Mode
- Add visual grouping/container rendering
- Position sub-agent tools within parent bounds

### Phase 4: Collapsed Mode
- Create collapsed agent node type with badge
- Show tool count in collapsed view

### Phase 5: Multi-Entity Save
- Group nodes by ownerAgentId in WorkflowParser
- Topological sort for save order
- Update Modeler API integration for multi-save

### Phase 6: New Agent Creation
- Allow creating new agent nodes in UI
- Generate temporary IDs for new agents
- Save new agents in correct dependency order

## Recursion Limit

Maximum depth: 3 levels to prevent infinite loops.
```
Agent A → Agent B → Agent C → STOP (don't expand further)
```

## Files to Modify

| File | Changes |
|------|---------|
| `js/flowdrop-agents-editor.js` | Add toolbar dropdowns, view state, AJAX re-fetch |
| `src/Service/AgentWorkflowMapper.php` | Add expansion mode, recursive loading, ownerAgentId |
| `src/Service/WorkflowParser.php` | Group by owner, topological sort, multi-save |
| `src/Controller/Api/NodesController.php` | New endpoint for workflow with expansion |
| `flowdrop_ui_agents.routing.yml` | Add new route |
| `css/flowdrop-agents-editor.css` | Styles for grouped containers, collapsed badges |
