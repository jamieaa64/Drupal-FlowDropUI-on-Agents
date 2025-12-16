# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and implementation plans.

- **DO NOT REMOVE THIS SECTION** - it helps orient future agents to the branch context
- **Current Branch**: `feature/flowdrop-phase2-implementation`
- **Branch Goal**: Phase 2 - Implement FlowDrop to AI Agent config mapping services
- **Update the branch name above** when working on a different branch
- **Previous Branch**: `feature/flowdrop-agent-integration` (Phase 1 research - merged to main)

---

## Quick Context (Read This First)

### What We're Building
FlowDrop UI is a visual workflow editor (Svelte-based). We're modifying it to save directly to Drupal's AI Agent/Assistant/Tool configuration entities instead of its own workflow entities.

### The Core Change
```
BEFORE: FlowDrop UI → saves to → flowdrop_workflow entities
AFTER:  FlowDrop UI → saves to → AI Agent/Assistant/Tool configs
```

### Key Files
- **CLAUDE-PLANNING.md** - Full implementation plan with all phases
- **CLAUDE.md** - General project guidance
- **Reference code**: `web/modules/contrib/ai_integration_eca/modules/agents/` (ECA's approach)

---

## Project Summary

**Short Title**: Visual Flow Builder for AI Agents
**Short Description**: FlowDrop UI becomes a pure visual editor that saves directly to AI Agent configurations.

### Architecture Decisions Made
| Decision | Choice |
|----------|--------|
| Save target | AI Agent/Assistant/Tool config only (not FlowDrop workflows) |
| Transformation layer | Via Modeler API pattern (like ECA Integration) |
| Tools vs Function Calls | Tools only in UI; converted to function calls under the hood |
| Drawer contents | Only items that can map to Agent/Tool config |
| Workflow = Assistant | 1:1 mapping (each canvas = one Assistant) |
| Edit existing | Full edit for any Agent/Assistant (not just FlowDrop-created) |
| Multi-agent | Supported from start |

### Visual Mapping
```
Canvas = 1 Assistant
├── Text Input (red) → Assistant input
├── Agent Node (blue) → AI Agent config
│   └── Tool Nodes (orange, attached) → Tools enabled for agent
├── Agent Node (blue) → Another AI Agent config
│   └── Tool Nodes (orange, attached)
└── Chat Output (green) → Assistant output
```

---

## Current Phase: Phase 2 - Service Implementation ✅ COMPLETE

### Phase 1 Objectives
1. **Study Modeler API integration pattern** - Understand how it works
2. **Study ECA Integration implementation** - It's our reference pattern
3. **Map FlowDrop's current save/load mechanism** - Understand what to change
4. **Design ConfigMapper service interface** - Plan the transformation layer
5. **Create schema definitions** - For Assistant/Agent configs

### Phase 1 Tasks (Detailed)

#### Task 1.1: Study Modeler API
**Goal**: Understand how Modeler API provides abstraction for visual editors

**Files to study**:
```
web/modules/contrib/modeler_api/
├── src/
│   ├── Plugin/ModelerApiModelOwner/     # How models are "owned"
│   └── ...
```

**Questions to answer**:
- How does Modeler API handle different model types?
- What hooks/events are available?
- How does it connect UI to backend storage?

#### Task 1.2: Study ECA Integration (Primary Reference)
**Goal**: Understand the proven pattern we're following

**Key files to study**:
```
web/modules/contrib/ai_integration_eca/modules/agents/src/
├── Plugin/AiAgent/Eca.php              # The AI Agent plugin
│   - determineSolvability() - determines task type
│   - solve() - executes the task
│   - buildModel() - creates/updates ECA config
│
├── Schema/Eca.php                       # JSON Schema definition
│   - Defines structure for ECA models
│
├── Services/
│   ├── EcaRepository/EcaRepositoryInterface.php
│   │   - get() - load ECA entity
│   │   - build() - create ECA from model data
│   │
│   └── DataProvider/DataProviderInterface.php
│       - getModels() - list available models
│       - getComponents() - list available components
│
├── TypedData/
│   ├── EcaModelDefinition.php          # Typed data for models
│   └── EcaPluginDefinition.php         # Typed data for plugins
│
└── Normalizer/                          # Serialization handling
```

**Key patterns to extract**:
1. How EcaRepository::build() creates config entities from UI data
2. How Schema defines the expected structure
3. How TypedData provides type safety
4. How the Normalizer handles serialization

#### Task 1.3: Map FlowDrop's Current Save/Load
**Goal**: Understand what we're replacing

**Files to study**:
```
web/modules/contrib/flowdrop/modules/
├── flowdrop_workflow/
│   ├── src/Entity/                     # Workflow entity definition
│   └── src/                            # CRUD operations
│
├── flowdrop_ui/
│   ├── src/Service/                    # Backend services
│   └── app/flowdrop/src/               # Svelte frontend
│       ├── lib/stores/                 # State management
│       └── lib/api/                    # API calls to backend
```

**Questions to answer**:
- What data structure does FlowDrop use for workflows?
- How does the save action work (frontend → backend)?
- What API endpoints exist?
- Where is the "Save" button handler?

#### Task 1.4: Design ConfigMapper Service
**Goal**: Design the interface for transforming FlowDrop data ↔ Config entities

**Service responsibilities**:
```php
interface ConfigMapperInterface {
  // FlowDrop canvas → Config entities
  public function canvasToAssistant(array $canvasData): array;
  public function nodeToAgent(array $nodeData): array;
  public function attachedToolsToConfig(array $toolNodes): array;

  // Config entities → FlowDrop canvas (for loading)
  public function assistantToCanvas(string $assistantId): array;
  public function agentToNode(string $agentId): array;
}
```

**Design questions**:
- What's the exact FlowDrop data structure?
- What's the exact AI Agent config structure?
- How to handle node positions (UI metadata)?
- How to handle connections/edges?

#### Task 1.5: Create Schema Definitions
**Goal**: Define JSON schemas for Assistant/Agent in our context

**Files to create**:
```
flowdrop_ai_provider/src/Schema/
├── AssistantSchema.php
└── AgentSchema.php
```

**Schema should define**:
- Required fields
- Optional fields
- Field types
- Validation rules

---

## Reference: AI Agent Config Structure (ACTUAL)

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

---

## Reference: FlowDrop Canvas Structure

From studying FlowDrop UI (approximate):

```javascript
// Canvas/workflow data structure
{
  id: 'workflow-123',
  name: 'My Workflow',
  nodes: [
    {
      id: 'node-1',
      type: 'text_input',
      position: { x: 100, y: 100 },
      data: { ... }
    },
    {
      id: 'node-2',
      type: 'simple_agent',
      position: { x: 300, y: 100 },
      data: {
        model: 'gpt-4',
        temperature: 0.7,
        systemPrompt: '...'
      }
    },
    {
      id: 'tool-1',
      type: 'http_request',
      position: { x: 350, y: 200 },
      parentNode: 'node-2',  // Attached to agent
      data: { ... }
    }
  ],
  edges: [
    { source: 'node-1', target: 'node-2' },
    { source: 'node-2', target: 'node-3' }
  ]
}
```

---

## File Locations Quick Reference

### Modules We're Working With
```
web/modules/contrib/
├── ai/                          # Core AI module
├── ai_agents/                   # AI Agents framework
├── ai_integration_eca/          # ECA integration (REFERENCE)
├── ai_provider_openai/          # OpenAI provider
├── flowdrop/                    # FlowDrop core
├── flowdrop_ai_provider/        # Our main work area
├── modeler_api/                 # Modeler abstraction
└── tool/                        # Tool module
```

### Where We'll Make Changes
```
flowdrop_ai_provider/            # Primary work area
├── src/
│   ├── Schema/                  # NEW: Schema definitions
│   ├── Services/                # NEW: Repository services
│   ├── TypedData/               # NEW: Typed data definitions
│   └── Normalizer/              # NEW: Serialization
└── flowdrop_ai_provider.services.yml  # Register services

flowdrop/modules/flowdrop_ui/    # Secondary (later phases)
├── src/Service/                 # Modify save service
└── app/flowdrop/src/            # Frontend changes
```

---

## Environment Setup Notes

### DDEV Commands
```bash
ddev start                       # Start environment
ddev drush cr                    # Clear cache
ddev drush en <module>           # Enable module
ddev drush cex -y                # Export config
ddev drush cim -y                # Import config
```

### API Keys
Environment variables available in DDEV:
- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`

Configured in `~/.ddev/global_config.yaml`

---

## Progress Tracking

### Phase 1 Checklist
- [x] Task 1.1: Study Modeler API integration pattern
- [x] Task 1.2: Study ECA Integration implementation in depth
- [x] Task 1.3: Map FlowDrop's current save/load mechanism
- [x] Task 1.4: Design ConfigMapper service interface
- [x] Task 1.5: Create schema definitions for Assistant/Agent

### Notes/Findings

#### Modeler API Pattern (Task 1.1)
- **Plugin-based bridge pattern** connecting UI/visual modelers with backend systems
- Model owners and modelers are completely decoupled through plugin interfaces
- Key classes:
  - `ModelOwnerInterface` - Owns config entities (ECA, AI Agents)
  - `ModelerInterface` - UI providers (BPMN.io)
  - `Api.php` - Central orchestrator
- Storage options: THIRD_PARTY (default), SEPARATE, or NONE
- Uses `prepareModelFromData()` to validate and transform UI data → config entities
- Dynamic route generation for each owner+modeler combination

#### ECA Integration Pattern (Task 1.2)
- Complete reference implementation in `ai_integration_eca/modules/agents/`
- **Data Flow**: UI Data → ModelMapper → TypedData → Validation → EcaRepository → ECA Entity
- Key components:
  - `TypedData/EcaModelDefinition.php` - Schema definition using ComplexDataDefinitionBase
  - `Services/ModelMapper.php` - Bidirectional conversion (Payload ↔ TypedData ↔ Entity)
  - `Services/EcaRepository.php` - Entity creation/update with validation
  - `Services/DataProvider.php` - Aggregates available components for UI
  - Custom validation constraints (SuccessorsAreValidConstraint)
- Multi-layer validation: JSON → TypedData Schema → Plugin validation → Entity validation

#### FlowDrop Save/Load (Task 1.3)
- **Frontend**: `@d34dman/flowdrop` npm package (Svelte-based)
- **Save Flow**:
  1. User clicks Save → `window.flowdropSave()` called
  2. PUT `/api/flowdrop/workflows/{id}` with JSON body
  3. `WorkflowsController::updateWorkflow()` validates and saves to `flowdrop_workflow` entity
- **Data Structures**:
  - `FlowDropWorkflow` config entity with: id, label, description, nodes[], edges[], metadata
  - `WorkflowDTO`, `WorkflowNodeDTO`, `WorkflowEdgeDTO` for data transfer
- **Node Structure**: id, typeId (plugin), label, config, metadata, position{x,y}, inputs[], outputs[]
- **Edge Structure**: id, source, target, sourceHandle, targetHandle, isTrigger, branchName

#### AI Agent Config Structure (Task 1.3)
- Entity: `ai_agents.ai_agent.*`
- **NO provider/model settings** on agent - handled by AI module provider system
- Tools stored as map: `'tool_id' => TRUE`
- Tool settings per-tool: return_directly, description_override, use_artifacts
- Tool usage limits: property-level restrictions (allow/force values)
- Agent types: orchestration_agent (only picks), triage_agent (picks AND works), worker (neither)

#### ConfigMapper Design (Task 1.4)
**Key Insight**: We need TWO mapping approaches:
1. **Simple Agent Mode**: FlowDrop nodes map directly to AI Agent tools
2. **Multi-Agent Mode**: FlowDrop graph maps to multiple AI Agent entities + orchestration

USER RESPONSE: I don't see why there needs to be a simple agent mode. An Agent node will map onto a single Agent configuration, if a flow has more than one agent its because the Agent has tools that are other agents (but those will be saved on that Agent node and the configuration of the first agent pointing to it) - For now we can aim to always start with an Assistant or "Orchestration Agent"

**Service Interface**:
```php
interface FlowDropAgentMapperInterface {
  // FlowDrop → AI Agent (Save)
  public function workflowToAgentConfigs(WorkflowDTO $workflow): array;
  public function nodeToAgentConfig(WorkflowNodeDTO $node, array $connectedTools): AiAgentConfig;
  public function extractToolsFromWorkflow(WorkflowDTO $workflow, string $agentNodeId): array;

  // AI Agent → FlowDrop (Load)
  public function agentConfigsToWorkflow(array $agentIds): WorkflowDTO;
  public function agentConfigToNode(AiAgentConfig $agent): WorkflowNodeDTO;
  public function toolsToToolNodes(array $tools, string $parentAgentNodeId): array;

  // UI Metadata
  public function storePositions(string $agentId, array $positions): void;
  public function loadPositions(string $agentId): array;
}
```

**Mapping Logic**:
- FlowDrop `chat_model` node → AI Agent entity
- FlowDrop tool nodes attached to agent → Agent's `tools` array
- FlowDrop edges between agents → Orchestration/triage relationship
- Node positions → Third-party settings on agent config

**UI Position Storage**: Store in agent's third-party settings:
```yaml
third_party_settings:
  flowdrop_ai_provider:
    positions:
      agent_node: {x: 100, y: 100}
      tool_1: {x: 150, y: 200}
```

#### Schema Definitions Created (Task 1.5)

**Files created in `flowdrop_ai_provider/src/`:**

1. **TypedData/FlowDropAgentModelDefinition.php** - ComplexDataDefinitionBase for agent models
   - Properties: model_id, label, description, system_prompt, agents, tools, edges, orchestration_agent, triage_agent, max_loops, ui_metadata

2. **TypedData/FlowDropAgentModel.php** - TypedData container (#[DataType] plugin)
   - Helper methods: getName(), getModelId(), getSystemPrompt(), getAgents(), getTools(), etc.

3. **Services/FlowDropAgentMapperInterface.php** - Main mapping service interface
   - Save methods: workflowToModel(), workflowToAgentConfigs(), nodeToAgentConfig()
   - Load methods: agentConfigsToWorkflow(), agentConfigToNode(), toolsToToolNodes()
   - Position methods: storePositions(), loadPositions()
   - Validation: validateWorkflowMapping(), fromPayload(), fromEntity()

4. **Services/AgentRepositoryInterface.php** - Agent entity CRUD interface
   - Methods: build(), buildMultiple(), load(), loadMultiple(), getAll(), delete(), validate()

5. **Services/ToolDataProviderInterface.php** - Tool data for UI drawer
   - Methods: getAvailableTools(), getToolsByCategory(), getTool(), getToolConfigSchema()

6. **Exception/MappingException.php** - Workflow mapping errors
7. **Exception/ValidationException.php** - Data validation errors with violation support

---

## Questions for User
*(Add questions here if you get stuck)*

1. **Tool ID Format**: Should we use the `ai_agent:` prefix format (like `ai_agent:http_request`) or the `tool:` prefix format? Need to verify which format the AI Agents module expects.

USER RESPONSE: DOn't know, we are aiming to support doing things the "proper" way according to the tool api

2. **Provider/Model Selection**: The AI Agents module doesn't store provider/model directly on agents. How should FlowDrop UI handle model selection? Options:
   - Use global default from AI module settings
   - Store in third-party settings on agent
   - Add to a separate "AI Settings" entity

   USER RESPONSE: IT should only ever save to the settings that already exist within the configruation entities and should make use of whatever is there.

   IT looks like the Assistant has provider settings

---

## Phase 2 Implementation Summary

### Services Implemented

**1. FlowDropAgentMapper** (`src/Services/FlowDropAgentMapper.php`)
- Bidirectional conversion between FlowDrop workflows and AI Agent configs
- Key methods:
  - `workflowToAgentConfigs()` - Convert workflow to agent configs (Save)
  - `agentConfigsToWorkflow()` - Convert agents back to workflow (Load)
  - `storePositions()` / `loadPositions()` - UI metadata via third-party settings
  - `validateWorkflowMapping()` - Validate before save
- Handles multi-agent workflows with orchestration detection
- Stores UI positions in agent's third-party settings

**2. AgentRepository** (`src/Services/AgentRepository.php`)
- CRUD operations for AI Agent entities
- Key methods:
  - `build()` - Create/update agent from data array
  - `buildMultiple()` - Batch create from workflow conversion
  - `validate()` - Validate data without saving
  - `getFlowDropAgents()` - Get agents created by FlowDrop
- Tracks FlowDrop-created agents via third-party settings

**3. ToolDataProvider** (`src/Services/ToolDataProvider.php`)
- Provides tool information for FlowDrop UI drawer
- Key methods:
  - `getAvailableTools()` - List all tools (33 tools available)
  - `getToolsByCategory()` - Grouped for UI display
  - `getAvailableAgents()` - List agents that can be used as sub-agents
  - `getAgentSchema()` - JSON Schema for agent config

### Services Configuration

Updated `flowdrop_ai_provider.services.yml`:
```yaml
services:
  flowdrop_ai_provider.agent_mapper:
    class: Drupal\flowdrop_ai_provider\Services\FlowDropAgentMapper
    arguments: ['@entity_type.manager', '@typed_data_manager', '@serializer']

  flowdrop_ai_provider.agent_repository:
    class: Drupal\flowdrop_ai_provider\Services\AgentRepository
    arguments: ['@entity_type.manager', '@typed_data_manager']

  flowdrop_ai_provider.tool_data_provider:
    class: Drupal\flowdrop_ai_provider\Services\ToolDataProvider
    arguments: ['@plugin.manager.tool', '@entity_type.manager']
```

### Module Dependencies Updated

Added to `flowdrop_ai_provider.info.yml`:
- `flowdrop:flowdrop_workflow` - For WorkflowDTO classes
- `tool:tool` - For ToolManager service

### Testing Results

All services verified working:
- ToolDataProvider: 33 tools available
- AgentRepository: 5 existing agents loaded
- FlowDropAgentMapper: Successfully converts workflow ↔ agent configs

---

## Current Phase: Phase 3 - API Integration ✅ COMPLETE

### Phase 3 Objectives
1. ✅ Create API endpoints for FlowDrop UI to call these services
2. Modify FlowDrop UI save/load to use new services (Future Phase)
3. ✅ Add proper error handling and user feedback
4. Create integration tests (Future Phase)

### API Endpoints Created

#### Agent CRUD Endpoints
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/flowdrop-ai/agents` | List all agents |
| POST | `/api/flowdrop-ai/agents` | Create new agent |
| GET | `/api/flowdrop-ai/agents/{id}` | Get specific agent |
| PUT | `/api/flowdrop-ai/agents/{id}` | Update agent |
| DELETE | `/api/flowdrop-ai/agents/{id}` | Delete agent |
| POST | `/api/flowdrop-ai/agents/validate` | Validate agent data |

#### Workflow Conversion Endpoints
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/flowdrop-ai/workflow/save` | Save workflow as agent configs |
| GET/POST | `/api/flowdrop-ai/workflow/load` | Load agents as workflow |
| POST | `/api/flowdrop-ai/workflow/validate` | Validate workflow mapping |
| GET | `/api/flowdrop-ai/agents/{id}/workflow` | Get agent as workflow |

#### UI Position Storage
| Method | Path | Description |
|--------|------|-------------|
| PUT/POST | `/api/flowdrop-ai/agents/{id}/positions` | Store UI positions |
| GET | `/api/flowdrop-ai/agents/{id}/positions` | Load UI positions |

#### Tool Discovery Endpoints
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/flowdrop-ai/tools` | List all tools |
| GET | `/api/flowdrop-ai/tools/by-category` | List tools by category |
| GET | `/api/flowdrop-ai/tools/search?q=query` | Search tools |
| GET | `/api/flowdrop-ai/tools/{toolId}` | Get specific tool |
| GET | `/api/flowdrop-ai/tools/{toolId}/schema` | Get tool config schema |
| POST | `/api/flowdrop-ai/tools/{toolId}/validate` | Validate tool config |

#### Agent Discovery Endpoints
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/flowdrop-ai/available-agents` | List agents for sub-agent use |
| GET | `/api/flowdrop-ai/schema/agent` | Get JSON schema for agents |

### Files Created

**Controllers:**
- `src/Controller/Api/AgentsController.php` - Agent CRUD and workflow conversion
- `src/Controller/Api/ToolsController.php` - Tool discovery and metadata

**Routing:**
- `flowdrop_ai_provider.routing.yml` - All API routes (20 endpoints)

### Testing Results

All API endpoints verified working:
- Agent list: 5 agents returned
- Tool list: 33 tools returned
- Tool search: Works correctly (e.g., "entity" returns 20 tools)
- Agent as workflow: Converts agent to FlowDrop workflow format
- Available agents: Returns agents with tool_id format for orchestration

---

## Next Phase: Phase 4 - Frontend Integration

### Phase 4 Objectives
1. Modify FlowDrop UI (Svelte) to call new API endpoints
2. Add "Save to AI Agent" option in FlowDrop save dialog
3. Add "Load from AI Agent" option in FlowDrop load dialog
4. Create new node type drawer for AI-specific nodes

---

## Commits on This Branch
*(Track commits as you make them)*

1. Initial branch creation from main
2. Phase 1 research complete - Added schema definitions and service interfaces
3. Phase 2 complete - Implemented FlowDropAgentMapper, AgentRepository, and ToolDataProvider services
4. Phase 3 complete - Added REST API endpoints for agents and tools
