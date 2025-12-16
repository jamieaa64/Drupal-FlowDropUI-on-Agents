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

### Phase 1: Foundation
- [ ] Study Modeler API integration pattern in depth
- [ ] Study ECA Integration implementation in depth
- [ ] Map FlowDrop's current save/load mechanism
- [ ] Design the ConfigMapper service interface
- [ ] Create schema definitions for Assistant/Agent

### Phase 2: Save Flow
- [ ] Create AssistantRepository service
- [ ] Create AgentRepository service
- [ ] Implement FlowDrop → Config transformation
- [ ] Modify FlowDrop UI save action to use new services
- [ ] Test: Create flow in UI, verify config entities created

### Phase 3: Load Flow
- [ ] Implement Config → FlowDrop transformation (reverse)
- [ ] Create "Open existing" UI in FlowDrop
- [ ] Load Assistant config and render on canvas
- [ ] Load associated Agents as nodes
- [ ] Load Tool references as attached nodes
- [ ] Test: Open config-created agent in FlowDrop UI

### Phase 4: Tool Drawer
- [ ] Query Tool module for available tools
- [ ] Populate drawer with Tool plugins
- [ ] Remove non-config-mappable nodes from drawer
- [ ] Implement Tool attachment to Agent nodes
- [ ] Test: Attach tools, save, verify in config

### Phase 5: Edit & Update
- [ ] Implement update flow (modify existing configs)
- [ ] Handle config conflicts/validation
- [ ] Test: Edit existing agent, save changes

### Phase 6: Multi-Agent Flows
- [ ] Support multiple Agent nodes on canvas
- [ ] Save/load flow connections between Agents
- [ ] Test: Multi-agent orchestration

### Phase 7: Polish
- [ ] UI position metadata storage
- [ ] Error handling and validation
- [ ] User feedback/messaging
- [ ] Documentation

---

## 6. Files to Modify/Create

### Existing Files to Modify
```
flowdrop_ai_provider/
├── src/Plugin/FlowDropNodeProcessor/ChatModel.php  # May need updates
└── flowdrop_ai_provider.info.yml                   # Add dependencies

flowdrop/modules/flowdrop_ui/
├── src/Service/                                    # Modify save service
└── app/flowdrop/src/                              # Frontend changes
```

### New Files to Create
```
flowdrop_ai_provider/
├── src/
│   ├── Schema/
│   │   ├── AssistantSchema.php
│   │   └── AgentSchema.php
│   ├── Services/
│   │   ├── AssistantRepository.php
│   │   ├── AgentRepository.php
│   │   └── ConfigMapper.php
│   ├── TypedData/
│   │   ├── AssistantDefinition.php
│   │   └── AgentDefinition.php
│   └── Normalizer/
│       └── FlowDropConfigNormalizer.php
└── flowdrop_ai_provider.services.yml              # Register services
```

---

## 7. Open Questions (To Resolve During Development)

1. **UI Position Metadata** - Where to store node X/Y positions? Options:
   - Third-party storage in Assistant config
   - Separate UI state config
   - Browser local storage (loses cross-device)

2. **Validation** - How strict should config validation be on save?
   - Strict: Reject invalid configs
   - Permissive: Save with warnings

3. **Existing Workflows** - What happens to existing FlowDrop workflow entities?
   - Migration path?
   - Keep working but deprecated?

4. **Tool Discovery** - How to filter which Tools appear in drawer?
   - All available Tools?
   - Only "agent-compatible" Tools?
   - User-configurable?

5. **Assistant API Integration** - Exact structure of Assistant config needs research:
   - What fields does `ai_assistant_api` expect?
   - How is agent orchestration defined?

6. **Modeler API Details** - Need to understand:
   - How does Modeler API handle multiple model types?
   - What hooks/events are available?

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

## 10. Next Steps

1. **Commit this planning doc to main**
2. **Create feature branch**: `feature/flowdrop-agent-integration`
3. **Start Phase 1**: Deep-dive into Modeler API and ECA Integration code
4. **First code task**: Create the ConfigMapper service skeleton
