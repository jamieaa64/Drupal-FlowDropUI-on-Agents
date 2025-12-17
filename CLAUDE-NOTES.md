# CLAUDE-NOTES.md

Running history of investigations, findings, and notes from AI agent sessions.

---

## 2024-12-16: Agent/Assistant/Tool Integration Investigation

### Problem Statement
User encountered error: `The "ai_agents::ai_agent::bundle_lister" plugin does not exist` when trying to use an Assistant with an Agent.

### Key Findings

#### 1. How Assistant → Agent → Tool Works
1. **Creating an Assistant** creates a backing **AI Agent** entity with the same ID
2. The backing agent is set to `orchestration_agent: TRUE`
3. Selected "sub-agents" are stored in the agent's `tools` array as `ai_agents::ai_agent::AGENT_ID`
4. When the Assistant runs, it actually runs via its backing Agent
5. The Agent uses `FunctionCallPluginManager` to load and execute tools

#### 2. How Agents Are Exposed as Function Call Plugins
The `ai_agents.module` file contains `hook_ai_function_call_info_alter()` which:
- Loops through all AI Agent entities
- Registers each as a FunctionCall plugin with ID format `ai_agents::ai_agent::AGENT_ID`
- Points to `AiAgentWrapper` class
- Sets `function_name` to just the agent ID (e.g., `bundle_lister`)

**Code location**: `web/modules/contrib/ai_agents/ai_agents.module:14-46`

#### 3. How AiAgentWrapper Works
- Located at `web/modules/contrib/ai_agents/src/Plugin/AiFunctionCall/AiAgentWrapper.php`
- Uses `$this->pluginDefinition['function_name']` to get agent ID
- Creates agent instance via `AiAgentManager->createInstance(agent_id)`
- Executes via `execute()` method which runs `agent->solve()`

#### 4. Verification - System Works Correctly
```bash
# Agent plugins are registered correctly
ddev drush php:eval "
\$manager = \Drupal::service('plugin.manager.ai.function_calls');
\$definitions = \$manager->getDefinitions();
foreach (\$definitions as \$id => \$def) {
  if (str_starts_with(\$id, 'ai_agents::ai_agent::')) {
    echo \"\$id\n\";
  }
}
"
# Output shows all agents registered with correct format
```

### Root Cause Analysis
The original error was likely caused by:
1. **Misconfigured Assistant** - The Assistant's backing agent was referencing itself or had wrong agent ID
2. **User deleted the configs** before we could fix them
3. The system itself is working correctly - agents ARE exposed as function call plugins via the alter hook

### Technical Details

#### Plugin ID Format
- **Full plugin ID**: `ai_agents::ai_agent::AGENT_ID` (double colons)
- **Function name**: Just the agent ID (e.g., `bundle_lister`)
- **Different from Tool plugins**: `tool:TOOL_ID` (single colon)

#### Relevant Code Paths
1. **Assistant Form Save** (`ai_assistant_api/src/Form/AiAssistantForm.php:540-543`):
   ```php
   foreach ($form_state->getValue('agents_agent') as $key => $val) {
     if ($val) {
       $tools['ai_agents::ai_agent::' . $key] = TRUE;
     }
   }
   ```

2. **Agent Loading Tools** (`ai_agents/src/PluginBase/AiAgentEntityWrapper.php:950`):
   ```php
   $function_call = $this->functionCallPluginManager->createInstance($function_call_name);
   ```

3. **Plugin Registration** (`ai_agents/ai_agents.module:20-21`):
   ```php
   $id = 'ai_agents::ai_agent::' . $agent['id'];
   $definitions[$id] = [...];
   ```

### Resolution
No code changes needed - the system works correctly. The original error was due to misconfigured test entities that were deleted.

### Key Insight: Function Calls vs Tool API
- **Old Function Calls**: Direct plugin system in AI module
- **New Tool API**: Separate `tool` module with its own plugin system
- **Tool AI Connector**: Sub-module that exposes Tools as FunctionCall plugins (workaround for compatibility)
- **Agents use Tools correctly** - they store tool references and load via FunctionCallPluginManager
- **Assistants are Agents** with stripped-down UI - they select other Agents to use as sub-agents

---

## 2024-12-17: FlowDrop AI Provider Implementation (Phases 1-5)

### Overview
Implemented the FlowDrop AI Provider module to enable visual editing of AI Agents using FlowDrop UI. The implementation saves directly to AI Agent config entities, not separate workflow entities.

### Architecture Decisions Made

#### 1. Direct AI Agent Integration (Not Assistants)
**Decision:** Work directly with AI Agent config entities, not Assistants.

**Rationale:**
- AI Agent is the fundamental unit - Assistants are just orchestration agents
- AI Agent already stores tools, system prompts, and settings
- Simpler mapping: 1 FlowDrop canvas = 1 AI Agent with its tools
- Assistants can be created separately to orchestrate multiple agents

#### 2. Tool Storage Using ai_agent Plugin Format
**Decision:** Store tools as `ai_agent:TOOL_ID` in Agent's tools array.

**Rationale:**
- This matches how the AI Agents module stores tool references
- Tool AI Connector module converts to function calls at runtime
- Consistent with existing agent config structure

#### 3. Node Position Storage
**Decision:** Store FlowDrop node positions in Agent's third-party settings.

**Implementation:**
```php
$agent->setThirdPartySetting('flowdrop_ai_provider', 'positions', $positions);
```

**Rationale:**
- Keeps UI metadata with the config entity
- Survives config export/import
- No separate storage entity needed

#### 4. ConfigSchema Format for Config Panels
**Decision:** Use JSON Schema format with `properties` object for node config panels.

**Required format:**
```php
[
  'type' => 'object',
  'properties' => [
    'field_name' => [
      'type' => 'string',
      'title' => 'Field Label',
      'description' => 'Help text',
      'default' => 'default value',
    ],
  ],
  'required' => ['field_name'],
]
```

**Finding:** FlowDrop's ConfigForm component iterates over `configSchema.properties` - array format doesn't work.

### Key Technical Findings

#### 1. FlowDrop Node Data Structure
Nodes must include `nodeId` in their `data` property for handle IDs to work:
```php
$nodeData = [
  'id' => $nodeId,
  'type' => 'universalNode',
  'data' => [
    'nodeId' => $nodeId,  // REQUIRED for handle generation
    'label' => 'Node Label',
    'config' => [...],
    'metadata' => [
      'inputs' => [...],
      'outputs' => [...],
      'configSchema' => [...],
    ],
  ],
  'position' => ['x' => 100, 'y' => 100],
];
```

#### 2. Handle ID Generation
FlowDrop WorkflowNode generates handle IDs as:
- Input handles: `${nodeId}-input-${portId}`
- Output handles: `${nodeId}-output-${portId}`

Edges must reference these exact handle IDs.

#### 3. Tool-Type Ports
For tool connections (not data flow), use `dataType: "tool"`:
```php
'outputs' => [
  [
    'id' => 'tools',
    'name' => 'Tools',
    'type' => 'output',
    'dataType' => 'tool',  // Special type for tool connections
  ],
],
```

This renders with amber/dashed styling in FlowDrop.

#### 4. AI Agent Entity Structure
Key fields from `AiAgent` entity:
- `id`: Machine name
- `label`: Human-readable name
- `description`: Used by triage agents
- `system_prompt`: Main instructions
- `secured_system_prompt`: Protected prompt
- `tools`: Array of tool references (`ai_agent:tool_id`)
- `tool_settings`: Per-tool settings keyed by tool ID
- `max_loops`: Iteration limit (default 3)
- `orchestration_agent`: Boolean - only picks other agents
- `triage_agent`: Boolean - picks agents AND does own work

#### 5. Tool Settings Structure
Per-tool settings available in AI Agents:
```php
'tool_settings' => [
  'ai_agent:http_request' => [
    'return_directly' => FALSE,
    'require_usage' => FALSE,
    'use_artifacts' => FALSE,
    'description_override' => '',
    'progress_message' => '',
  ],
],
```

### Files Created/Modified

#### New Services
- `FlowDropAgentMapper` - Bidirectional conversion between workflows and agent configs
- `FlowDropAgentMapperInterface` - Interface definition
- `ToolDataProvider` - Provides tool information for sidebar and schemas

#### New Controllers
- `AgentEditorController` - Pages for listing/editing agents in FlowDrop
- `AgentsController` (API) - REST endpoints for workflow CRUD
- `ToolsController` (API) - REST endpoints for tool discovery

#### Supporting Classes
- `FlowDropAgentModel` - TypedData model for validation
- `FlowDropAgentModelDefinition` - TypedData definition
- `MappingException`, `ValidationException` - Custom exceptions
- `FlowDropAiEndpointConfigService` - API endpoint configuration

### API Endpoints Created
```
GET  /api/flowdrop-ai/tools                    - List available tools
GET  /api/flowdrop-ai/tools/by-category        - Tools grouped by category
GET  /api/flowdrop-ai/tools/{id}               - Single tool details
GET  /api/flowdrop-ai/tools/{id}/schema        - Tool config schema
GET  /api/flowdrop-ai/agents                   - List AI agents
GET  /api/flowdrop-ai/agents/{id}/workflow     - Load agent as workflow
POST /api/flowdrop-ai/workflow/save            - Save workflow to agent
POST /api/flowdrop-ai/workflow/validate        - Validate workflow
```

### Lessons Learned

1. **FlowDrop expects specific data structures** - nodeId in data, JSON Schema for configSchema, proper handle ID format
2. **Don't use Assistant API for simple agents** - AI Agent entity is sufficient and simpler
3. **Tool integration is straightforward** - ai_agent:tool_id format, Tool AI Connector handles conversion
4. **Third-party settings work well for UI metadata** - positions, expanded state, etc.
5. **FlowDrop's JS is readable** - built/flowdrop.es.js is searchable for understanding behavior

---

## Reference: Data Structures (from Phase 1-5 Research)

### AI Agent Config Structure (ACTUAL)

From studying `web/modules/contrib/ai_agents/src/Entity/AiAgent.php`:

```php
// AI Agent Config Entity - Actual Structure
$agent = [
  'id' => 'my_agent',                    // Machine name (required)
  'label' => 'My Agent',                 // Display name (required)
  'description' => 'Used by triage agents to select this agent', // (required)
  'system_prompt' => 'You are a helpful assistant...',  // (required)
  'secured_system_prompt' => '[ai_agent:agent_instructions]', // Full prompt with tokens

  // Tools as map of boolean values
  'tools' => [
    'ai_agent:get_content_type_info' => TRUE,
    'ai_agent:edit_content_type' => TRUE,
  ],

  // Per-tool settings
  'tool_settings' => [
    'ai_agent:get_content_type_info' => [
      'return_directly' => 0,
      'description_override' => '',
      'require_usage' => FALSE,
      'use_artifacts' => FALSE,
    ],
  ],

  // Property-level restrictions per tool
  'tool_usage_limits' => [
    'ai_agent:get_content_type_info' => [
      'node_type' => [
        'action' => '',  // '', 'only_allow', or 'force_value'
        'hide_property' => 0,
        'values' => '',  // Newline-separated values
      ],
    ],
  ],

  // YAML string of context tools run before agent starts
  'default_information_tools' => "node_types:\n  label: 'Node Types'\n  tool: 'ai_agent:list_config_entities'",

  // Agent type flags
  'orchestration_agent' => FALSE,  // Only picks other agents
  'triage_agent' => FALSE,         // Picks agents AND does own work

  // Execution settings
  'max_loops' => 3,
  'masquerade_roles' => [],
  'exclude_users_role' => FALSE,

  // Structured output
  'structured_output_enabled' => FALSE,
  'structured_output_schema' => '',
];
```

**Key Insight**: AI Agents module does NOT store provider/model settings directly on the agent. Those are handled by the AI module's provider system.

### FlowDrop Canvas Structure

From studying FlowDrop UI:

```javascript
// Canvas/workflow data structure
{
  id: 'workflow-123',
  name: 'My Workflow',
  nodes: [
    {
      id: 'node-1',
      type: 'universalNode',
      position: { x: 100, y: 100 },
      data: {
        nodeId: 'node-1',  // REQUIRED for handle IDs
        label: 'Node Label',
        config: { ... },
        metadata: {
          id: 'plugin_id',
          name: 'Display Name',
          inputs: [...],
          outputs: [...],
          configSchema: { properties: {...} }  // JSON Schema format
        }
      }
    }
  ],
  edges: [
    {
      id: 'edge-1',
      source: 'node-1',
      target: 'node-2',
      sourceHandle: 'node-1-output-response',
      targetHandle: 'node-2-input-trigger'
    }
  ]
}
```

### Phase 1-5 Research Findings Summary

#### Modeler API Pattern (Task 1.1)
- **Plugin-based bridge pattern** connecting UI/visual modelers with backend systems
- Model owners and modelers are completely decoupled through plugin interfaces
- Key classes:
  - `ModelOwnerInterface` - Owns config entities (ECA, AI Agents)
  - `ModelerInterface` - UI providers (BPMN.io)
  - `Api.php` - Central orchestrator
- Storage options: THIRD_PARTY (default), SEPARATE, or NONE
- Uses `prepareModelFromData()` to validate and transform UI data → config entities

#### FlowDrop Save/Load (Task 1.3)
- **Frontend**: `@d34dman/flowdrop` npm package (Svelte-based)
- **Save Flow**:
  1. User clicks Save → `window.flowdropSave()` called
  2. PUT `/api/flowdrop/workflows/{id}` with JSON body
  3. `WorkflowsController::updateWorkflow()` validates and saves
- **Data Structures**:
  - `FlowDropWorkflow` config entity with: id, label, description, nodes[], edges[], metadata
  - `WorkflowDTO`, `WorkflowNodeDTO`, `WorkflowEdgeDTO` for data transfer

#### Key User Decisions Made
1. **No Simple Agent Mode** - An Agent node maps to a single Agent config; multi-agent = tools pointing to other agents
2. **Tool ID Format** - Using "proper" Tool API way (`ai_agent:tool_id`)
3. **Provider/Model Selection** - Only save to settings that already exist in config entities; Assistants have provider settings

---
