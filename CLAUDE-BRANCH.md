# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and immediate next steps.

- **Current Branch**: `main` (ready to commit Phase 6 completion)
- **Phase**: 6 Complete, Ready for Phase 6.5
- **For historical context**: See `CLAUDE-NOTES.md` and `CLAUDE-PLANNING.md`

---

## Current Status (2024-12-17)

### Phase 6 COMPLETE - Save is Working!

Created `web/modules/custom/flowdrop_ui_agents/` with full Modeler API integration:

| Component | File | Purpose |
|-----------|------|---------|
| Modeler Plugin | `FlowDropAgents.php` | Registers `flowdrop_agents` modeler |
| Mapper Service | `AgentWorkflowMapper.php` | AI Agent ↔ FlowDrop conversion |
| Parser Service | `WorkflowParser.php` | JSON → Modeler API Components |
| JavaScript | `flowdrop-agents-editor.js` | Editor init & save handling |
| CSS | `flowdrop-agents-editor.css` | Basic styling |

**Test URL:** `/admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents`

### What Works Now

- Load AI Agent into FlowDrop visual editor
- Display agent node with connected tools
- Edit agent properties (description, system prompt, etc.)
- **SAVE changes back to AI Agent config entity**
- Ctrl+S / Cmd+S keyboard shortcut

---

## Next Phase: 6.5 - Visual & UX Improvements

Before removing `flowdrop_ai_provider`, we need to port its better features:

### Priority 1: Enhanced Sidebar
Currently only shows 1 tool. Need to show ALL available tools like `flowdrop_ai_provider` does.

**Files to reference:**
- `flowdrop_ai_provider/src/Services/ToolDataProvider.php` - `getAvailableTools()`
- `flowdrop_ai_provider/src/Controller/Api/ToolsController.php` - API endpoints

### Priority 2: Visual Node Distinction
Tools should look different from Agents:
- **Agents**: Larger purple/blue boxes
- **Tools**: Smaller orange boxes

**Reference:** `FlowDropAgentMapper::toolsToToolNodes()` has the styling:
```php
'color' => 'var(--color-ref-orange-500)',
'icon' => 'mdi:tools',
```

### Priority 3: Tool Config Schema
Per-tool settings that should be editable:
- `return_directly` - Return tool result without LLM rewriting
- `require_usage` - Agent must use this tool
- `use_artifacts` - Store large responses
- `description_override` - Custom tool description
- `progress_message` - UI feedback during execution

### Priority 4: Remove flowdrop_ai_provider
After features ported:
```bash
ddev drush pmu flowdrop_ai_provider
# Remove from composer.json if needed
```

---

## Files Created in Phase 6

```
web/modules/custom/flowdrop_ui_agents/
├── flowdrop_ui_agents.info.yml
├── flowdrop_ui_agents.services.yml
├── flowdrop_ui_agents.libraries.yml
├── src/
│   ├── Plugin/ModelerApiModeler/
│   │   └── FlowDropAgents.php
│   └── Service/
│       ├── AgentWorkflowMapper.php
│       └── WorkflowParser.php
├── js/
│   └── flowdrop-agents-editor.js
├── css/
│   └── flowdrop-agents-editor.css
└── patches/
    └── README.md
```

---

## How the System Works

### Architecture
```
┌─────────────────┐      ┌─────────────────────┐      ┌──────────────────┐
│   AI Agent      │ ←──→ │  flowdrop_agents    │ ←──→ │   FlowDrop UI    │
│   Config Entity │      │  (Modeler Plugin)   │      │   (Visual Editor)│
└─────────────────┘      └─────────────────────┘      └──────────────────┘
        │                          │                           │
   ai_agents_agent           AgentWorkflowMapper          flowdrop_ui
   (ModelOwner)              WorkflowParser               (JS Library)
```

### Load Flow
1. User visits `/admin/config/ai/agents/{id}/edit_with/flowdrop_agents`
2. `FlowDropAgents::convert()` → `AgentWorkflowMapper::agentToWorkflow()`
3. Returns workflow JSON with nodes, edges, metadata
4. FlowDrop UI renders the visual editor

### Save Flow
1. User clicks "Save AI Agent" or Ctrl+S
2. JavaScript calls `app.getWorkflow()` to get current state
3. POST to `/admin/modeler_api/ai_agent/flowdrop_agents/save`
4. `FlowDropAgents::parseData()` → `WorkflowParser::toComponents()`
5. Returns `Component[]` objects
6. `Agent::addComponent()` updates AI Agent entity

---

## Key Technical Notes

### Component Types (from modeler_api/Api.php)
```php
COMPONENT_TYPE_START = 1    // Agent node
COMPONENT_TYPE_ELEMENT = 4  // Tool node
COMPONENT_TYPE_LINK = 5     // Edge/connection
```

### Agent Config Keys (must match AiAgentForm)
```php
'agent_id', 'label', 'description', 'system_prompt',
'max_loops', 'orchestration_agent', 'triage_agent',
'secured_system_prompt', 'default_information_tools',
'structured_output_enabled', 'structured_output_schema'
```

### JavaScript Timing Issue (FIXED)
Must use `editorContainer.flowdropApp` NOT `window.currentFlowDropApp` because the navbar onclick handler captures scope before the global is set.

---

## For Next Agent

```
Continue with Phase 6.5 - Visual & UX Improvements

CURRENT STATE: Phase 6 complete, save working!
MODULE: web/modules/custom/flowdrop_ui_agents/

NEXT TASKS:
1. Port ToolDataProvider to show all tools in sidebar
2. Add visual distinction between tool and agent nodes
3. Add per-tool config settings
4. Then remove flowdrop_ai_provider module

REFERENCE CODE:
- flowdrop_ai_provider/src/Services/ToolDataProvider.php
- flowdrop_ai_provider/src/Services/FlowDropAgentMapper.php

TEST URL: /admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents
```

---

## Reference Files

| File | Purpose |
|------|---------|
| `CLAUDE-NOTES.md` | Historical findings, technical details |
| `CLAUDE-PLANNING.md` | Full implementation plan, all phases |
| `CLAUDE.md` | General project guidance, DDEV commands |
