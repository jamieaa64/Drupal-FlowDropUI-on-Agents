# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and immediate next steps.

- **Current Branch**: `main`
- **Phase**: 7 Complete, Ready for Phase 8
- **For historical context**: See `CLAUDE-NOTES.md` and `CLAUDE-PLANNING.md`

---

## Current Status (2024-12-17)

### What's Working
- FlowDrop visual editor for **AI Agents** at `/admin/config/ai/agents/{agent}/edit_with/flowdrop_agents`
- FlowDrop visual editor for **AI Assistants** at `/admin/config/ai/ai-assistant/{assistant}/edit-flowdrop`
- Multi-agent visualization with 3 modes: Expanded, Grouped, Collapsed
- Assistant node type with teal color, different icon, and assistant-specific settings
- Save functionality for both Agents and Assistants
- Save notifications (toast messages) for success/error
- Unsaved changes indicator (amber badge + pulsing save button)
- Sidebar shows ALL 108 tools + agents (categorized)
- "Edit with FlowDrop for AI Agents" appears in dropdown for both Agents and Assistants

### Test URLs
- **Agent**: `/admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents`
- **Assistant**: `/admin/config/ai/ai-assistant/bundle_lister_assistant/edit-flowdrop`

---

## Phase 8: Testing, Polish & New Features

### Priority 1: Testing & Edge Cases
1. **Test editing agents within an assistant flow** - Ensure sub-agent changes save correctly
2. **Test assistants with multiple sub-agents** - Verify all tools attach correctly
3. **Opening an Agent attached to an Assistant** - Should redirect to open the Assistant instead (or show warning)

### Priority 2: New Node Types
1. **Deepchat Chatbot node** - New node type that attaches to Assistants for chat UI integration

### Priority 3: UI/UX Improvements
1. **Tool Drawer categories** - Match categories to Select Tools Widget for consistency
2. **Node config panel ordering** - Match form field order/priorities (hide advanced features)
3. **Prompt text boxes** - Full-screen editor for more space (may be out of scope)
4. **Auto-attach on drag** - When dragging a tool onto an Agent, auto-connect and position nicely
5. **Initial tool spacing** - Tools overlap on first load, need better default positioning

### Priority 4: RAG Integration
1. **RAG Search tools** - Add to sidebar and test functionality
2. **Link to indexes** - RAG tools should connect to Search API indexes

### Priority 5: Tool Config Improvements
1. **Better tool configuration panel** - Improve UX of tool settings
2. **Tool usage limits** - Expose in UI if not already

### Priority 6: AI-Assisted Setup (Stretch Goal)
1. **Create an AI Assistant** that helps users set up agents/assistants via the visual editor
2. Would require meta-level prompting and understanding of the UI

---

## Module Structure

```
web/modules/custom/flowdrop_ui_agents/
├── flowdrop_ui_agents.info.yml
├── flowdrop_ui_agents.services.yml
├── flowdrop_ui_agents.libraries.yml
├── flowdrop_ui_agents.routing.yml
├── src/
│   ├── Controller/
│   │   ├── AssistantEditorController.php   # NEW: Assistant FlowDrop editor
│   │   └── Api/
│   │       ├── NodesController.php         # Sidebar API (108 tools)
│   │       └── AssistantSaveController.php # NEW: Assistant save endpoint
│   ├── Hook/
│   │   └── EntityOperations.php            # NEW: Dropdown menu items
│   ├── Plugin/ModelerApiModeler/
│   │   └── FlowDropAgents.php              # Modeler plugin
│   └── Service/
│       ├── AgentWorkflowMapper.php         # AI Agent ↔ FlowDrop
│       └── WorkflowParser.php              # JSON → Components
├── js/
│   └── flowdrop-agents-editor.js           # Notifications, unsaved indicator
└── css/
    └── flowdrop-agents-editor.css          # Assistant styling, animations
```

---

## Key Technical Details

### API Endpoints
- `GET /api/flowdrop-agents/nodes` - All tools + agents
- `GET /api/flowdrop-agents/nodes/by-category` - Grouped
- `GET /api/flowdrop-agents/nodes/{id}/metadata` - Single node
- `POST /api/flowdrop-agents/assistant/{id}/save` - Save assistant + agent

### Node Types
| Type | Color | Icon | Description |
|------|-------|------|-------------|
| `agent` | Purple | `mdi:robot` | AI Agent (main or sub-agent) |
| `assistant` | Teal | `mdi:account-voice` | AI Assistant (wraps agent) |
| `agent-collapsed` | Light purple (dashed) | `mdi:robot-outline` | Collapsed sub-agent |
| `tool` | Orange | `mdi:tools` | Function call tool |

### Assistant vs Agent
- **Agent**: Core entity with tools, system prompt, max loops
- **Assistant**: Wrapper around Agent that adds: LLM provider/model, history settings, roles, error message
- When editing Assistant, changes save to BOTH entities

---

## For Next Agent

```
Continue with Phase 8 - Testing, Polish & New Features

IMMEDIATE TESTING NEEDED:
1. Edit agents within an assistant flow - do changes save correctly?
2. Test assistants with multiple sub-agents
3. What happens when opening an Agent that belongs to an Assistant?

NEW FEATURES TO ADD:
1. Deepchat Chatbot node type for Assistants
2. RAG Search tools integration
3. Auto-attach tools when dragged onto agent
4. Better initial tool spacing/positioning
5. Full-screen prompt editor
6. Category matching with Select Tools Widget

TEST URLS:
- Agent: /admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents
- Assistant: /admin/config/ai/ai-assistant/bundle_lister_assistant/edit-flowdrop

MODULE: web/modules/custom/flowdrop_ui_agents/
```

---

## Reference Files

| File | Purpose |
|------|---------|
| `CLAUDE-NOTES.md` | Technical findings, issue details |
| `CLAUDE-PLANNING.md` | Full implementation plan |
| `CLAUDE.md` | Project guidance, DDEV commands |
