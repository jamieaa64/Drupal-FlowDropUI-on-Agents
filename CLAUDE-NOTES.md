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

## 2024-12-17: Phase 6 Complete - flowdrop_ui_agents Module

### Summary
Created new custom module `flowdrop_ui_agents` that provides FlowDrop visual editing for AI Agents via Modeler API integration. **SAVE IS NOW WORKING!**

### Module Structure
```
web/modules/custom/flowdrop_ui_agents/
├── flowdrop_ui_agents.info.yml
├── flowdrop_ui_agents.services.yml
├── flowdrop_ui_agents.libraries.yml
├── src/
│   ├── Plugin/ModelerApiModeler/
│   │   └── FlowDropAgents.php          # Modeler plugin (flowdrop_agents)
│   └── Service/
│       ├── AgentWorkflowMapper.php     # AI Agent ↔ FlowDrop conversion
│       └── WorkflowParser.php          # JSON → Modeler API Components
├── js/
│   └── flowdrop-agents-editor.js       # FlowDrop initialization & save
└── css/
    └── flowdrop-agents-editor.css
```

### How It Works

**LOAD Flow:**
1. User visits `/admin/config/ai/agents/{agent_id}/edit_with/flowdrop_agents`
2. `FlowDropAgents::convert()` calls `AgentWorkflowMapper::agentToWorkflow()`
3. AI Agent entity → FlowDrop workflow JSON (nodes, edges, metadata)
4. JSON passed to FlowDrop UI via drupalSettings

**SAVE Flow:**
1. User clicks "Save AI Agent" button
2. JavaScript gets workflow from `app.getWorkflow()`
3. POST to `/admin/modeler_api/ai_agent/flowdrop_agents/save`
4. Modeler API calls `FlowDropAgents::parseData()` → `WorkflowParser::parse()`
5. Then calls `FlowDropAgents::readComponents()` → `WorkflowParser::toComponents()`
6. Returns `Component[]` objects with type, config, successors
7. `Agent::addComponent()` (ModelOwner) updates AI Agent entity

### Key Technical Findings

#### 1. Modeler API Flow
```php
// In FlowDropAgents.php
public function parseData(ModelOwnerInterface $owner, string $data): void {
  $this->modelOwner = $owner;  // CRITICAL: Store owner reference
  $this->parsedData = $this->workflowParser()->parse($data);
  $this->workflowParser()->setOwner($owner);  // Pass to parser
}

public function readComponents(): array {
  return $this->workflowParser()->toComponents($this->parsedData);
}
```

#### 2. Component Types for AI Agents
```php
Api::COMPONENT_TYPE_START = 1    // Agent node (main agent config)
Api::COMPONENT_TYPE_ELEMENT = 4  // Tool node
Api::COMPONENT_TYPE_LINK = 5     // Edge/connection
```

#### 3. Agent Config Keys (Must Match AiAgentForm)
```php
$agentConfig = [
  'agent_id' => $agentId,
  'label' => '...',
  'description' => '...',
  'system_prompt' => '...',
  'max_loops' => 3,
  'orchestration_agent' => FALSE,
  'triage_agent' => FALSE,
  'secured_system_prompt' => '[ai_agent:agent_instructions]',
  'default_information_tools' => '',
  'structured_output_enabled' => FALSE,
  'structured_output_schema' => '',
];
```

#### 4. JavaScript getWorkflow() Timing
The save function must access the FlowDrop app via `editorContainer.flowdropApp` NOT `window.currentFlowDropApp` because the navbar onclick handler fires before `window.currentFlowDropApp` is set.

### Issues Encountered & Fixed

1. **TranslatableMarkup in strcasecmp()** - Cast tool names to `(string)` before sorting
2. **FlowDrop library not loaded** - Changed dependency from `flowdrop_ui/flowdrop` to `flowdrop_ui/editor`
3. **Wrong API base URL** - Changed from `/api/flowdrop-ai` to `/api/flowdrop`
4. **Workflow data not captured** - Used `editorContainer.flowdropApp` instead of global reference
5. **Owner not set on parser** - Added `$this->modelOwner` storage and pass to parser in `parseData()`

### Comparison: flowdrop_ui_agents vs flowdrop_ai_provider

| Feature | flowdrop_ui_agents (NEW) | flowdrop_ai_provider (OLD) |
|---------|--------------------------|----------------------------|
| Integration | Modeler API plugin | Custom REST API |
| Save mechanism | Modeler API Components | Direct entity save |
| Tool display | Basic (same as agent) | Visual distinction (smaller, orange) |
| Sidebar | Single tool visible | All tools + agents categorized |
| Save URL | Auto-generated by Modeler API | Custom `/api/flowdrop-ai/*` |

### What flowdrop_ai_provider Does Better

1. **Visual Tool Nodes** - Smaller orange boxes vs uniform nodes
2. **Rich Sidebar** - All tools categorized, agents listed separately
3. **Tool Config Schema** - Per-tool settings (return_directly, require_usage, etc.)
4. **Category Colors** - Different colors per category

### Recommended Next Steps

1. **Enhance sidebar** - Port `ToolDataProvider::getAvailableTools()` to show all tools
2. **Visual node styling** - Port tool node styling (smaller, orange)
3. **Remove flowdrop_ai_provider** - After features ported
4. **Test multi-agent flows** - Phase 7

---

## 2024-12-17: Modeler API Architecture Discovery (Phase 6)

### Critical Finding: FlowDrop Modeler Already Exists!

The `flowdrop_modeler` submodule exists in `web/modules/contrib/flowdrop/modules/flowdrop_modeler/` and provides:
- A `ModelerApiModeler` plugin with ID `flowdrop_modeler`
- Services for converting ModelOwner components to FlowDrop workflow format
- Integration with Modeler API

### Modeler API Two-Plugin Architecture

| Plugin Type | Interface | Role |
|-------------|-----------|------|
| **ModelOwner** | `ModelOwnerInterface` | Owns config entities, defines component types, builds config forms |
| **Modeler** | `ModelerInterface` | Provides visual UI, parses/serializes raw data, converts to/from components |

### Component Types (from `modeler_api/Api.php`)

```php
Api::COMPONENT_TYPE_START = 1      // Entry point (Agent start)
Api::COMPONENT_TYPE_SUBPROCESS = 2  // Sub-agent
Api::COMPONENT_TYPE_SWIMLANE = 3    // Visual grouping
Api::COMPONENT_TYPE_ELEMENT = 4     // Tool/Action
Api::COMPONENT_TYPE_LINK = 5        // Connection/Edge
Api::COMPONENT_TYPE_GATEWAY = 6     // Decision point
Api::COMPONENT_TYPE_ANNOTATION = 7  // Comment/Note
```

### AI Agents ModelOwner Plugin (`ai_agents_agent`)

Located at: `web/modules/contrib/ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php`

**Key Methods:**
- `usedComponents()` - Returns Component objects from AiAgent entity
- `addComponent()` - Adds component back to AiAgent entity
- `buildConfigurationForm()` - Returns Drupal form for component config
- `availableOwnerComponents()` - Returns available plugins (tools, agents)
- `supportedOwnerComponentTypes()` - Returns supported component types

**Supported Component Types:**
```php
Api::COMPONENT_TYPE_START => 'agent'      // The main agent
Api::COMPONENT_TYPE_SUBPROCESS => 'wrapper' // Sub-agents
Api::COMPONENT_TYPE_ELEMENT => 'tool'       // Tools
Api::COMPONENT_TYPE_LINK => 'link'          // Connections
```

### FlowDrop Modeler Plugin (`flowdrop_modeler`)

Located at: `web/modules/contrib/flowdrop/modules/flowdrop_modeler/src/Plugin/ModelerApiModeler/FlowDrop.php`

**Dependencies:**
- `bpmn_io` module (for parser - this is a potential issue!)
- `flowdrop_ui` module

**Key Services:**
- `WorkflowOrchestratorService` - Coordinates workflow processing
- `AvailableNodeTypesService` - Generates sidebar node types from ModelOwner
- `BpmnToWorkflowMapper` - Maps components to workflow format
- `WorkflowLayoutService` - Calculates node positions

**Critical Issue:** The module currently uses `bpmn_io.parser` for `parseData()` and `readComponents()`. This means:
- LOAD works: `convert()` transforms ModelOwner → FlowDrop workflow
- SAVE may not work: `parseData()` expects BPMN XML, not FlowDrop JSON!

### BPMN.iO Modeler Plugin (`bpmn_io`)

Located at: `web/modules/contrib/bpmn_io/src/Plugin/ModelerApiModeler/BpmnIo.php`

Reference implementation showing:
- How to render visual editor
- How to parse raw data (XML) to Components
- How to serialize Components back to raw data
- Config form handling via off-canvas dialog

### Routes Auto-Generated by Modeler API

When a ModelOwner has `configEntityBasePath()` and Modelers are available:

| Route Pattern | Description |
|---------------|-------------|
| `/{basePath}` | List all entities |
| `/{basePath}/add/{modeler_id}` | Create new with specific modeler |
| `/{basePath}/{entity}/edit_with/{modeler_id}` | Edit with specific modeler |
| `/{basePath}/{entity}/view_with/{modeler_id}` | View with specific modeler |

For AI Agents (basePath = `admin/config/ai/agents`):
- `/admin/config/ai/agents/add/flowdrop_modeler` - Create with FlowDrop
- `/admin/config/ai/agents/{agent_id}/edit_with/flowdrop_modeler` - Edit with FlowDrop

### Implications for Implementation

**What We Don't Need:**
1. ~~New ModelOwner plugin~~ - `ai_agents_agent` already exists
2. ~~New REST endpoints~~ - Modeler API provides save/config routes
3. ~~Complete bidirectional mapper~~ - `flowdrop_modeler` services exist

**What We May Need:**
1. **FlowDrop JSON Parser** - Replace BPMN parser with FlowDrop-native parser
2. **AI Agent Customizations** - Possibly in custom module if contrib can't be patched
3. **Form Improvements** - Config panels may need enhancement

### Next Steps for Investigation

1. Test existing integration: Does `/admin/config/ai/agents/{agent}/edit_with/flowdrop_modeler` work?
2. Investigate save flow: Does saving persist to AI Agent config?
3. Identify what patches/customizations are needed

---
