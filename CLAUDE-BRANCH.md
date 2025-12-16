# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and implementation plans.

- **DO NOT REMOVE THIS SECTION** - it helps orient future agents to the branch context
- **Current Branch**: `feature/flowdrop-agent-integration`
- **Branch Goal**: Make FlowDrop UI save directly to AI Agent/Assistant/Tool configs
- **Update the branch name above** when working on a different branch

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

## Current Phase: Phase 1 - Foundation

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

## Reference: AI Agent Config Structure

From studying `web/modules/contrib/ai_agents/`:

```php
// Agent config entity fields (approximate)
$agent = [
  'id' => 'my_agent',
  'label' => 'My Agent',
  'description' => 'Agent description',
  'plugin' => 'agent_plugin_id',
  'provider' => 'openai',
  'model' => 'gpt-4',
  'settings' => [
    'temperature' => 0.7,
    'max_tokens' => 1000,
  ],
  'prompts' => [
    'system' => 'You are a helpful assistant...',
  ],
  'tools' => [
    'tool:http_request',
    'tool:calculator',
  ],
];
```

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
- [ ] Task 1.1: Study Modeler API integration pattern
- [ ] Task 1.2: Study ECA Integration implementation in depth
- [ ] Task 1.3: Map FlowDrop's current save/load mechanism
- [ ] Task 1.4: Design ConfigMapper service interface
- [ ] Task 1.5: Create schema definitions for Assistant/Agent

### Notes/Findings
*(Add notes here as you discover things)*

---

## Questions for User
*(Add questions here if you get stuck)*

---

## Commits on This Branch
*(Track commits as you make them)*

1. Initial branch creation from main
