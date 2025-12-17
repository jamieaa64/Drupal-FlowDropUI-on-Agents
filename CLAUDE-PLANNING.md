# CLAUDE-PLANNING.md

## Project: FlowDrop UI for AI Agents

Visual flow builder that saves directly to AI Agent/Assistant/Tool configurations.

---

## 1. Architecture Overview

### Core Principle
**FlowDrop UI becomes a pure visual editor** - it does not save to its own workflow entities. Instead, it saves directly to:
- **Assistant configs** (the orchestration layer)
- **Agent configs** (the workers)
- **Tool configs** (what agents can use)

### Visual Mapping
```
┌─────────────────────────────────────────────────────────────┐
│  FlowDrop UI Canvas = 1 Assistant                           │
│                                                             │
│  ┌──────────┐     ┌─────────────────┐     ┌────────────┐   │
│  │  Text    │     │  Simple Agent   │     │   Chat     │   │
│  │  Input   │────▶│                 │────▶│   Output   │   │
│  │  (red)   │     │    (blue)       │     │  (green)   │   │
│  └──────────┘     │                 │     └────────────┘   │
│                   │  ┌───────────┐  │                       │
│                   │  │HTTP Req   │  │                       │
│                   │  ├───────────┤  │  ◀── Tools (orange)   │
│                   │  │Calculator │  │      attached to      │
│                   │  ├───────────┤  │      Agent            │
│                   │  │Date/Time  │  │                       │
│                   │  └───────────┘  │                       │
│                   └─────────────────┘                       │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow
```
FlowDrop UI ──▶ Modeler API ──▶ AI Agent/Assistant Config
     │                               │
     │         (transformation)      │
     │                               ▼
     │                         Drupal Config
     │                         Entities
     │                               │
     ◀───────────────────────────────┘
            (load existing)
```

---

## 2. Key Architectural Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Save target | AI Agent config only | FlowDrop UI is pure UI, no separate workflow storage |
| Transformation | Via Modeler API | Follows ECA Integration pattern, proven approach |
| Tools vs Function Calls | Tools only | Future-proof; Tool module converts to function calls under the hood |
| Drawer contents | Only items that map to Agent/Tool config | If it can't be saved as config, it shouldn't be in the drawer |
| Workflow = Assistant | 1:1 mapping | Each canvas is one Assistant, containing Agents |
| Edit existing | Full edit for all | Any Agent/Assistant can be opened, not just FlowDrop-created ones |
| Multi-agent | Supported from start | Same mapping pattern repeated; connections define orchestration |
| Chatbot connection | Manual (outside FlowDrop) | Assistant + Agents in FlowDrop; Chatbot linked separately |

---

## 3. Technical Approach

### 3.1 Using Modeler API (Reference: ECA Integration)

The ECA Integration module provides a proven pattern:

```
ai_integration_eca_agents/
├── src/
│   ├── Plugin/AiAgent/Eca.php          # The AI Agent plugin
│   ├── Schema/Eca.php                   # JSON Schema for ECA models
│   ├── Services/
│   │   ├── EcaRepository/               # CRUD for ECA entities
│   │   └── DataProvider/                # Provides model data to LLM
│   ├── TypedData/
│   │   ├── EcaModelDefinition.php       # Typed data definition
│   │   └── EcaPluginDefinition.php      # Plugin definitions
│   └── Normalizer/                      # Serialization
```

**We need equivalent for FlowDrop:**
```
flowdrop_ai_provider/
├── src/
│   ├── Plugin/AiAgent/FlowDropAgent.php    # AI Agent plugin (if needed)
│   ├── Schema/Assistant.php                 # JSON Schema for Assistant
│   ├── Schema/Agent.php                     # JSON Schema for Agent
│   ├── Services/
│   │   ├── AgentRepository/                 # CRUD for Agent configs
│   │   ├── AssistantRepository/             # CRUD for Assistant configs
│   │   └── ConfigMapper/                    # FlowDrop ↔ Config mapping
│   ├── TypedData/
│   │   ├── AssistantDefinition.php
│   │   └── AgentDefinition.php
│   └── Normalizer/
│       └── FlowDropNormalizer.php           # Canvas ↔ Config conversion
```

### 3.2 Tool Integration

The Tool module (`drupal/tool`) provides:
- `ToolBase` class for creating tools
- Typed inputs/outputs
- Submodules: `tool_entity`, `tool_content`, `tool_system`, `tool_user`

**Key:** There's a submodule that converts Tools to AiFunctionCall format for backward compatibility with AI Agents.

**FlowDrop drawer will:**
1. Query available Tool plugins from Tool module
2. Display them as draggable orange nodes
3. When attached to an Agent, store the Tool reference in Agent config
4. At runtime, Tool module handles the function call conversion

### 3.3 Config Entity Mapping

#### Assistant Config
```yaml
# What FlowDrop canvas saves to
id: my_assistant
label: 'My Assistant'
agents:
  - agent_1
  - agent_2
flow:
  # Connection/orchestration data
  edges:
    - source: input
      target: agent_1
    - source: agent_1
      target: agent_2
    - source: agent_2
      target: output
```

#### Agent Config
```yaml
# What each Agent node saves to
id: agent_1
label: 'Simple Agent'
provider: openai
model: gpt-4
tools:
  - tool:http_request
  - tool:calculator
  - tool:date_time
prompts:
  system: 'You are a helpful assistant...'
settings:
  temperature: 0.7
  max_tokens: 1000
```

---

## 4. Component Mapping

### UI Element → Config Mapping

| FlowDrop UI Element | Maps To | Config Location |
|---------------------|---------|-----------------|
| Canvas (workflow) | Assistant | `ai_assistant.assistant.{id}` |
| Text Input node | Assistant input config | Assistant's input settings |
| Agent node (blue) | AI Agent | `ai_agents.agent.{id}` |
| Tool node (orange) | Tool reference | Agent's `tools` array |
| Chat Output node | Assistant output config | Assistant's output settings |
| Connection (edge) | Flow definition | Assistant's `flow.edges` |
| Node position | UI metadata | Stored for re-rendering (TBD where) |

### Node Config Panel → Agent Settings

| Panel Field | Agent Config Field |
|-------------|-------------------|
| Model dropdown | `model` |
| Temperature slider | `settings.temperature` |
| Max tokens | `settings.max_tokens` |
| System prompt | `prompts.system` |
| Tools attached | `tools[]` |

---

## 5. Implementation Phases

### Phase 1: Foundation ✅ COMPLETE
- [x] Study Modeler API integration pattern in depth
- [x] Study ECA Integration implementation in depth
- [x] Map FlowDrop's current save/load mechanism
- [x] Design the ConfigMapper service interface
- [x] Create schema definitions for Assistant/Agent

**Notes:**
- Decided to work directly with AI Agent entities, not Assistants
- Created `FlowDropAgentMapper` service with `FlowDropAgentMapperInterface`
- Created `FlowDropAgentModel` TypedData definition
- Studied ECA Integration's `ModelMapper` pattern extensively

### Phase 2: Save Flow ✅ COMPLETE (Merged with mapping services)
- [x] Create AssistantRepository service → **Changed: Using AI Agent directly**
- [x] Create AgentRepository service → **Changed: FlowDropAgentMapper handles this**
- [x] Implement FlowDrop → Config transformation (`workflowToAgentConfigs()`)
- [x] Modify FlowDrop UI save action to use new services
- [x] Test: Create flow in UI, verify config entities created

**Notes:**
- No separate repository services - `FlowDropAgentMapper` handles bidirectional conversion
- Save endpoint: `POST /api/flowdrop-ai/workflow/save`
- Tools stored as `ai_agent:TOOL_ID` format in agent's tools array

### Phase 3: Load Flow ✅ COMPLETE (Part of API endpoints)
- [x] Implement Config → FlowDrop transformation (`agentConfigsToWorkflow()`)
- [x] Create "Open existing" UI in FlowDrop (`AgentEditorController`)
- [x] Load Assistant config and render on canvas → **Changed: Load Agent config**
- [x] Load associated Agents as nodes → **Changed: Single agent = single node**
- [x] Load Tool references as attached nodes
- [x] Test: Open config-created agent in FlowDrop UI

**Notes:**
- API endpoint: `GET /api/flowdrop-ai/agents/{id}/workflow`
- Edit page: `/admin/config/ai/flowdrop-ai/agents/{id}/edit`
- Positions loaded from agent's third-party settings

### Phase 4: Tool Drawer ✅ COMPLETE
- [x] Query Tool module for available tools (`ToolDataProvider`)
- [x] Populate drawer with Tool plugins (via API endpoints)
- [x] Remove non-config-mappable nodes from drawer → **Changed: Drawer shows tools only**
- [x] Implement Tool attachment to Agent nodes
- [x] Test: Attach tools, save, verify in config

**Notes:**
- API endpoints: `/api/flowdrop-ai/tools`, `/api/flowdrop-ai/tools/by-category`
- Tools displayed in sidebar with teaser mode for quick loading
- Full tool details fetched on demand

### Phase 5: Edit & Update ✅ COMPLETE (Config panels + tool connections)
- [x] Implement update flow (modify existing configs)
- [x] Handle config conflicts/validation
- [x] Test: Edit existing agent, save changes
- [x] **Added:** Fix tool-agent visual connections (edges with tool handles)
- [x] **Added:** Fix config panel opening (JSON Schema format for configSchema)
- [x] **Added:** Add nodeId to node data for proper handle ID generation

**Notes:**
- ConfigSchema must be JSON Schema format with `properties` object, not array
- Node data must include `nodeId` field for FlowDrop to generate handle IDs
- Tool ports use `dataType: "tool"` for special styling
- Edge handles: `{nodeId}-output-tools` → `{nodeId}-input-tool`

### Phase 6: Module Restructure & Modeler API Integration ✅ COMPLETE
**Major direction change based on user feedback**

- [x] **6.1 Study correct reference**: `ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php`
  - Understood ModelOwner plugin pattern
  - Learned Component types and config key requirements
- [x] **6.2 Install and study BPMN.io module** (version 3 branch)
  - Installed `bpmn_io` module
  - Discovered `flowdrop_modeler` exists but is ECA-specific
- [x] **6.3 Create new custom module**: `web/modules/custom/flowdrop_ui_agents/`
  - Created `FlowDropAgents` Modeler plugin
  - Created `AgentWorkflowMapper` and `WorkflowParser` services
  - JavaScript integration with FlowDrop UI
- [x] **6.4 Set up patch tracking**
  - Created `patches/` directory with README
  - No patches needed yet - clean custom module approach
- [x] **6.5 SAVE functionality working**
  - Tested and verified save persists to AI Agent config
  - Fixed workflow data capture timing issue
  - Fixed Modeler API Component creation

**Key Files Created:**
```
web/modules/custom/flowdrop_ui_agents/
├── flowdrop_ui_agents.info.yml
├── flowdrop_ui_agents.services.yml
├── flowdrop_ui_agents.libraries.yml
├── src/Plugin/ModelerApiModeler/FlowDropAgents.php
├── src/Service/AgentWorkflowMapper.php
├── src/Service/WorkflowParser.php
├── js/flowdrop-agents-editor.js
└── css/flowdrop-agents-editor.css
```

**Access URL:** `/admin/config/ai/agents/{agent_id}/edit_with/flowdrop_agents`

### Phase 6.5: Visual & UX Improvements 🔲 NOT STARTED
**Port better features from flowdrop_ai_provider before removing it**

- [ ] **6.5.1 Enhanced Tool Sidebar**
  - Port `ToolDataProvider::getAvailableTools()` to show ALL tools
  - Group tools by category (tools, agents, etc.)
  - Add agents as draggable items

- [ ] **6.5.2 Visual Node Distinction**
  - Tools: smaller orange boxes (like flowdrop_ai_provider)
  - Agents: larger purple/blue boxes
  - Different icons per node type

- [ ] **6.5.3 Tool Config Schema**
  - Port per-tool settings (return_directly, require_usage, use_artifacts)
  - Tool description override
  - Progress message

- [ ] **6.5.4 Remove flowdrop_ai_provider**
  - Disable and uninstall module
  - Remove from composer.json if local
  - Clean up any orphaned config

**Reference:** `flowdrop_ai_provider/src/Services/ToolDataProvider.php` has good patterns for:
- Tool discovery and categorization
- Config schema generation
- Agent listing

### Phase 7: Multi-Agent Flows 🔲 NOT STARTED
- [ ] Support multiple Agent nodes on canvas
- [ ] Save/load flow connections between Agents
- [ ] Implement agent-to-agent orchestration edges
- [ ] Test: Multi-agent orchestration

**Considerations:**
- May need orchestration agent (triage_agent: true) as parent
- Edge connections between agents define execution flow
- Need to handle agent dependency ordering

### Phase 8: Polish & Production Ready 🔲 NOT STARTED
- [ ] **Critical: Ensure save works reliably**
- [ ] Full round-trip testing: create, save, reload, edit, save
- [ ] Error handling and validation feedback in UI
- [ ] User feedback/messaging (success/error toasts)
- [ ] Documentation
- [ ] Position persistence verification

**Notes:**
- User explicitly mentioned not feeling comfortable saving yet
- Need to make the save experience trustworthy

---

## 6. Files Created/Modified

### Files Created (Phases 1-5)
```
flowdrop_ai_provider/
├── flowdrop_ai_provider.routing.yml         # All routes (pages + API)
├── flowdrop_ai_provider.services.yml        # Service definitions
├── flowdrop_ai_provider.links.menu.yml      # Admin menu links
├── js/
│   └── ai-agent-editor.js                   # FlowDrop integration JS
├── src/
│   ├── Controller/
│   │   ├── AgentEditorController.php        # List/edit pages
│   │   └── Api/
│   │       ├── AgentsController.php         # Agent workflow API
│   │       └── ToolsController.php          # Tool discovery API
│   ├── Exception/
│   │   ├── MappingException.php
│   │   └── ValidationException.php
│   ├── Service/
│   │   └── FlowDropAiEndpointConfigService.php  # API config
│   ├── Services/
│   │   ├── FlowDropAgentMapper.php          # Core mapper service
│   │   ├── FlowDropAgentMapperInterface.php
│   │   ├── ToolDataProvider.php             # Tool information
│   │   └── ToolDataProviderInterface.php
│   └── TypedData/
│       ├── FlowDropAgentModel.php           # Validation model
│       └── FlowDropAgentModelDefinition.php
└── flowdrop_ai_provider.libraries.yml       # JS library definition
```

### Files Not Created (Changed from original plan)
- `AssistantSchema.php` - Not needed, using AI Agent directly
- `AssistantRepository.php` - Not needed, mapper handles CRUD
- `AgentRepository.php` - Not needed, mapper handles CRUD
- `FlowDropConfigNormalizer.php` - Not needed, using getRawData() on DTOs

### Key File Responsibilities

| File | Purpose |
|------|---------|
| `FlowDropAgentMapper.php` | Bidirectional conversion: Workflow ↔ AI Agent config |
| `ToolDataProvider.php` | Queries Tool module, provides sidebar data |
| `AgentsController.php` | REST API for workflow CRUD operations |
| `ToolsController.php` | REST API for tool discovery |
| `AgentEditorController.php` | Drupal pages for editing agents |
| `ai-agent-editor.js` | Initializes FlowDrop with AI Agent data |

---

## 7. Open Questions

### Resolved ✅

1. **UI Position Metadata** - Where to store node X/Y positions?
   - **Answer:** Third-party settings in AI Agent config entity
   - `$agent->setThirdPartySetting('flowdrop_ai_provider', 'positions', $positions)`

2. **Tool Discovery** - How to filter which Tools appear in drawer?
   - **Answer:** Show all tools from `ai_agent` provider (tools exposed via Tool AI Connector)
   - Filter by provider prefix: `ai_agent:*`

3. **Assistant API Integration** - Exact structure of Assistant config needs research
   - **Answer:** Not using Assistants - working directly with AI Agent entities
   - Assistants are just orchestration agents, unnecessary complexity for single-agent flows

### Still Open 🔲

4. **Validation** - How strict should config validation be on save?
   - Options: Strict (reject) vs Permissive (save with warnings)
   - Current: Basic validation via TypedData, no UI feedback yet

5. **Existing Workflows** - What happens to existing FlowDrop workflow entities?
   - Not addressed yet - this is a separate integration, not replacing workflow entities
   - May need migration path if deprecating workflow entities

6. **Multi-Agent Orchestration** - How to represent agent-to-agent flow?
   - Need to determine: edges between agents, orchestration agent creation
   - How does triage_agent vs orchestration_agent affect execution?

7. **Save Persistence** - How to handle save failures gracefully?
   - Need: error handling, rollback, user feedback
   - Current: Basic try/catch, no UI feedback

---

## 8. Reference Code Locations

### ECA Integration (Reference Pattern)
```
web/modules/contrib/ai_integration_eca/modules/agents/src/
├── Plugin/AiAgent/Eca.php                    # Main agent plugin
├── Services/EcaRepository/EcaRepositoryInterface.php
├── Services/DataProvider/DataProviderInterface.php
├── Schema/Eca.php
└── TypedData/EcaModelDefinition.php
```

### AI Agents Core
```
web/modules/contrib/ai_agents/src/
├── Plugin/AiAgent/                           # Agent plugins
├── Plugin/AiFunctionCall/                    # Function call plugins
├── Entity/AiAgent.php                        # Agent entity
└── PluginBase/AiAgentBase.php               # Base class
```

### Tool Module
```
web/modules/contrib/tool/src/
├── Tool/ToolBase.php                         # Base class
├── Attribute/Tool.php                        # Plugin attribute
└── Plugin/tool/                              # Tool plugins
```

### FlowDrop Current
```
web/modules/contrib/flowdrop/modules/
├── flowdrop_ui/app/flowdrop/src/            # Svelte frontend
├── flowdrop_workflow/                        # Current workflow entity
└── flowdrop_ai_provider/                     # AI integration (modify this)
```

---

## 9. Success Criteria

### Minimum Viable Product
1. Create a flow in FlowDrop UI with one Agent and Tools
2. Save → creates real AI Agent config entity
3. Open existing AI Agent in FlowDrop UI
4. Edit and save changes
5. Agent is usable via AI Chatbot

### Full Implementation
1. Multi-agent flows with orchestration
2. All valid Tools appear in drawer
3. Full round-trip: Create, save, close, reopen, edit, save
4. Works with Assistants created outside FlowDrop
5. Clean removal of non-compatible drawer items

---

## 10. Progress & Next Steps

### Completed ✅
1. ~~Commit this planning doc to main~~
2. ~~Create feature branch~~: `feature/flowdrop-phase2-implementation`
3. ~~Phase 1-5 implementation~~ (see commits)

### Current Status
- **Branch:** `feature/flowdrop-phase2-implementation`
- **Phases Complete:** 1-5
- **Current Phase:** 6 - Module Restructure & Modeler API Integration
- **Last Commit:** Phase 5 - Fix tool-agent connections and config panels

### Phase 6 Priority (User Direction Change)
1. **Study correct reference** - `ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php` (NOT ECA)
2. **Install BPMN.io module** - Version 3 branch for working example
3. **Create custom module** - `web/modules/custom/flowdrop_ui_agents/`
4. **Track patches** - For any contrib module changes needed
5. **Improve config forms** - User doesn't feel comfortable saving yet

### Key User Feedback
> "I was wrong to use ECA AI Integration as a reference. We can remove that entirely (that is for putting AI Agents inside ECA). Instead: ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php shows how the Modeller API can be used to connect flowdrop to AI Agents."

### Commits on Branch
```
acca036 Phase 5: Fix tool-agent connections and config panels
0d464b1 Phase 4: Add FlowDrop editor integration for AI Agents
a6004b3 Phase 3: Add REST API endpoints for FlowDrop AI integration
8356132 Phase 2: Implement FlowDrop to AI Agent mapping services
46247d6 Add CLAUDE-NOTES.md with investigation findings
6c4147e Phase 1: Research complete + add contrib modules for patching
```

---

## 11. Future Work (Parked)

### Layout Toggle for Compact/Normal Nodes

**Status:** Parked - requires FlowDrop modifications

**Problem:** FlowDrop's node type system requires `metadata.supportedTypes` to include the target type (e.g., "simple") for a node to render differently. Our agent and tool nodes only declare `supportedTypes: ['agent']` or `supportedTypes: ['tool']`, so changing `config.nodeType` to "simple" has no effect.

**Investigation findings:**
- FlowDrop uses `resolveNodeType()` which checks if `configNodeType` is in `metadata.supportedTypes`
- If not found, it falls back to the primary type
- The `layout` property inside SimpleNode only affects compact/normal variants within that specific component
- To enable layout switching, nodes need `supportedTypes: ['simple', 'agent']` or similar

**Solutions (to be evaluated):**
1. **Update AgentWorkflowMapper** - Add "simple" to `supportedTypes` array in node metadata
2. **Patch FlowDrop** - Allow any nodeType regardless of supportedTypes
3. **Custom CSS approach** - Use CSS classes to toggle node appearance without changing nodeType

**Files involved:**
- `js/flowdrop-agents-editor.js` - JS toggle code (removed for now)
- `src/Service/AgentWorkflowMapper.php` - Node metadata generation
- FlowDrop: `flowdrop.es.js` lines 21738-21756 (`getAvailableNodeTypes`, `resolveNodeType`)

### Port Config Warning

**Status:** Low priority - cosmetic issue

The warning "Invalid port config received from API, using default" appears because FlowDrop fetches `/api/flowdrop-agents/port-config` on init. The endpoint works but returns in a format FlowDrop doesn't recognize as valid. FlowDrop falls back to defaults which work fine.

**To fix:** Compare our port-config response format with FlowDrop's expected `validatePortConfig()` requirements.
