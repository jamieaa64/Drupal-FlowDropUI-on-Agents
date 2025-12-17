# CLAUDE-NOTES.md

Running history of investigations, findings, and notes from AI agent sessions.

---

## 2024-12-17: Phase 6.5 Complete - API Endpoints & All Tools in Sidebar

### Summary
Created custom API endpoints for FlowDrop sidebar that return ALL AI tools (108 function call plugins) and agents, not just FlowDrop Node Types.

### Key Finding: FlowDrop Uses API, Not drupalSettings
FlowDrop calls API endpoints to populate the sidebar - it does NOT use `drupalSettings.availableTools`:

```
FlowDrop Init → Reads endpointConfig.baseUrl + endpoints.nodes.list
            → Calls GET /api/flowdrop-agents/nodes
            → Receives tool/agent definitions
            → Populates sidebar categories
```

### Files Created

**`src/Controller/Api/NodesController.php`** - API endpoints:
- `GET /api/flowdrop-agents/nodes` - All tools + agents (108 nodes)
- `GET /api/flowdrop-agents/nodes/by-category` - Grouped by category
- `GET /api/flowdrop-agents/nodes/{category}` - Single category
- `GET /api/flowdrop-agents/nodes/{id}/metadata` - Node details
- `GET /api/flowdrop-agents/port-config` - Port connection rules

**`flowdrop_ui_agents.routing.yml`** - Route definitions

### Updated Files

**`js/flowdrop-agents-editor.js`** - Changed endpoint config:
```javascript
const endpointConfig = {
  baseUrl: '/api/flowdrop-agents',  // Was /api/flowdrop
  endpoints: {
    nodes: {
      list: '/nodes',
      byCategory: '/nodes/by-category',
      // ...
    },
  },
};
```

### Tool Distribution (108 total)
- **agents**: 5 (AI Agent entities exposed as tools for orchestration)
- **contents**: 8 (content entity related tools)
- **tools**: 92 (entity operations, field management, user actions)

### Service Name Fix
The modeler_api service is `plugin.manager.modeler_api.model_owner` (not `plugin.manager.modeler_api_model_owner`).

---

## 2024-12-17: Known Issues for Next Phase

### Issue 1: Edge Lines Not Rendering on Load
**Problem:** When loading an existing agent, the connecting lines between tools and agents don't appear, even though the data includes edges.

**Same issue existed in flowdrop_ai_provider** - this is a FlowDrop rendering issue, not our mapper issue.

**Likely Causes:**
1. Edge `sourceHandle`/`targetHandle` IDs don't match generated handle IDs
2. FlowDrop needs edges added after nodes are rendered
3. Position data affecting edge rendering

**Investigation needed:**
- Check browser console for edge-related errors
- Verify handle ID format matches: `${nodeId}-output-${portId}` / `${nodeId}-input-${portId}`
- Compare edge data with working FlowDrop workflows

### Issue 2: Multi-Agent/Sub-Agent Workflow Not Working
**Problem:** When agent A has agent B as a tool (for orchestration/triage), the visual representation doesn't show agent B's own tools.

**Example:**
- "Bundle Lister Assistant" has "Agent Bundle Tool"
- "Agent Bundle Tool" is actually an AI Agent with its own tools
- FlowDrop should show nested agent structure

**Current Behavior:**
- Only shows the wrapper tool, not the sub-agent's tools

**Expected Behavior:**
- Show sub-agents distinctly (maybe as expandable groups)
- Or show sub-agents as agent nodes with their tools

**Investigation needed:**
- Check how `ai_agents::ai_agent::*` tools are handled in mapper
- Determine if we need recursive workflow building
- Consider UX for nested agents (expand/collapse? separate view?)

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
├── flowdrop_ui_agents.routing.yml      # NEW: API routes
├── src/
│   ├── Controller/Api/
│   │   └── NodesController.php          # NEW: Sidebar API
│   ├── Plugin/ModelerApiModeler/
│   │   └── FlowDropAgents.php           # Modeler plugin
│   └── Service/
│       ├── AgentWorkflowMapper.php      # AI Agent ↔ FlowDrop
│       └── WorkflowParser.php           # JSON → Components
├── js/
│   └── flowdrop-agents-editor.js
└── css/
    └── flowdrop-agents-editor.css
```

### How It Works

**LOAD Flow:**
1. User visits `/admin/config/ai/agents/{agent_id}/edit_with/flowdrop_agents`
2. `FlowDropAgents::convert()` calls `AgentWorkflowMapper::agentToWorkflow()`
3. AI Agent entity → FlowDrop workflow JSON (nodes, edges, metadata)
4. JSON passed to FlowDrop UI via drupalSettings
5. FlowDrop calls `/api/flowdrop-agents/nodes` to populate sidebar

**SAVE Flow:**
1. User clicks "Save AI Agent" button
2. JavaScript gets workflow from `app.getWorkflow()`
3. POST to `/admin/modeler_api/ai_agent/flowdrop_agents/save`
4. Modeler API calls `FlowDropAgents::parseData()` → `WorkflowParser::parse()`
5. Returns `Component[]` objects via `readComponents()`
6. `Agent::addComponent()` (ModelOwner) updates AI Agent entity

### Key Technical Details

#### Component Types for AI Agents
```php
Api::COMPONENT_TYPE_START = 1    // Agent node (main agent config)
Api::COMPONENT_TYPE_ELEMENT = 4  // Tool node
Api::COMPONENT_TYPE_LINK = 5     // Edge/connection
```

#### Agent Config Keys (Must Match AiAgentForm)
```php
$agentConfig = [
  'agent_id', 'label', 'description', 'system_prompt',
  'max_loops', 'orchestration_agent', 'triage_agent',
  'secured_system_prompt', 'default_information_tools',
  'structured_output_enabled', 'structured_output_schema',
];
```

#### JavaScript getWorkflow() Timing
Use `editorContainer.flowdropApp` NOT `window.currentFlowDropApp` - the navbar onclick handler fires before the global is set.

### Issues Fixed During Phase 6
1. **TranslatableMarkup in strcasecmp()** - Cast to `(string)` before sorting
2. **FlowDrop library not loaded** - Use `flowdrop_ui/editor` dependency
3. **Wrong API base URL** - Custom `/api/flowdrop-agents/` endpoints
4. **Workflow data not captured** - Use `editorContainer.flowdropApp`
5. **Owner not set on parser** - Store and pass in `parseData()`
6. **Wrong service name** - `plugin.manager.modeler_api.model_owner`

---

## 2024-12-16: Agent/Assistant/Tool Integration Investigation

### How Assistant → Agent → Tool Works
1. **Creating an Assistant** creates a backing **AI Agent** entity with the same ID
2. The backing agent is set to `orchestration_agent: TRUE`
3. Selected "sub-agents" are stored in the agent's `tools` array as `ai_agents::ai_agent::AGENT_ID`
4. When the Assistant runs, it actually runs via its backing Agent

### Plugin ID Formats
- **Agent tools**: `ai_agents::ai_agent::AGENT_ID` (double colons)
- **Tool plugins**: `tool:TOOL_ID` (single colon)
- **AI Agent tools**: `ai_agent:TOOL_NAME` (single colon)

### Key Code Paths
- Assistant Form Save: `ai_assistant_api/src/Form/AiAssistantForm.php:540-543`
- Agent Loading Tools: `ai_agents/src/PluginBase/AiAgentEntityWrapper.php:950`
- Plugin Registration: `ai_agents/ai_agents.module:20-21`

---

## Reference: Data Structures

### AI Agent Config Structure
```php
$agent = [
  'id' => 'my_agent',
  'label' => 'My Agent',
  'description' => 'Used by triage agents',
  'system_prompt' => 'You are...',
  'secured_system_prompt' => '[ai_agent:agent_instructions]',
  'tools' => ['ai_agent:tool_id' => TRUE],
  'tool_settings' => ['ai_agent:tool_id' => ['return_directly' => 0]],
  'orchestration_agent' => FALSE,
  'triage_agent' => FALSE,
  'max_loops' => 3,
];
```

### FlowDrop Node Structure
```javascript
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
      inputs: [...],
      outputs: [...],
      configSchema: { properties: {...} }
    }
  }
}
```

### Handle ID Format
- Input: `${nodeId}-input-${portId}`
- Output: `${nodeId}-output-${portId}`

---

## Modeler API Architecture

### Two-Plugin Architecture
| Plugin Type | Role |
|-------------|------|
| **ModelOwner** | Owns config entities, defines components |
| **Modeler** | Provides visual UI, parses data |

### Component Types
```php
Api::COMPONENT_TYPE_START = 1      // Entry point
Api::COMPONENT_TYPE_SUBPROCESS = 2  // Sub-agent
Api::COMPONENT_TYPE_ELEMENT = 4     // Tool/Action
Api::COMPONENT_TYPE_LINK = 5        // Connection
```

### Auto-Generated Routes
- `/{basePath}/add/{modeler_id}` - Create new
- `/{basePath}/{entity}/edit_with/{modeler_id}` - Edit
- `/{basePath}/{entity}/view_with/{modeler_id}` - View
