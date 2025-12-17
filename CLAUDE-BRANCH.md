# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and immediate next steps.

- **Current Branch**: `main`
- **Phase**: 6.5 Complete, Ready for Phase 7
- **For historical context**: See `CLAUDE-NOTES.md` and `CLAUDE-PLANNING.md`

---

## Current Status (2024-12-17)

### What's Working
- FlowDrop visual editor loads for AI Agents
- Sidebar shows ALL 108 tools + 5 agents (categorized)
- Save functionality works via Modeler API
- Tools use orange color, agents use purple

### Test URL
`/admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents`

---

## Known Issues (Next Phase)

### Issue 1: Edge Lines Not Appearing on Load
**Problem:** When loading an existing agent, the connecting lines between tools and agents don't render, even though edge data exists in the workflow.

**Impact:** User can't see which tools are connected to which agent.

**Same issue existed in flowdrop_ai_provider** - this is likely a FlowDrop rendering or handle ID mismatch issue.

**To investigate:**
1. Check browser console for edge-related errors
2. Verify handle IDs match format: `${nodeId}-output-${portId}` / `${nodeId}-input-${portId}`
3. Check if edges need to be added after initial render
4. Compare with working FlowDrop workflow to find differences

### Issue 2: Multi-Agent/Sub-Agent Display
**Problem:** When an agent uses another agent as a tool (orchestration/triage pattern), we only show the wrapper - not the sub-agent's own tools.

**Example scenario:**
```
Bundle Lister Assistant
  └── Agent Bundle Tool (ai_agents::ai_agent::agent_bundle_lister)
        └── This is actually an agent with its OWN tools!
            ├── list_bundles
            ├── get_entity_type_info
            └── etc.
```

**Current behavior:** Shows "Agent Bundle Tool" as a single tool node
**Expected behavior:** Should show sub-agent's structure or indicate it's an agent with nested tools

**Possible solutions:**
1. **Recursive expansion** - When tool ID starts with `ai_agents::ai_agent::`, expand to show its tools
2. **Visual indicator** - Different icon/badge for "agent-as-tool" vs regular tools
3. **Drill-down view** - Click to open sub-agent in nested view
4. **Grouped nodes** - Show sub-agent as a group containing its tools

---

## Phase 7: Edge Rendering & Multi-Agent

### Priority 1: Fix Edge Rendering
1. Debug why edges don't appear on load
2. Check handle ID generation in `AgentWorkflowMapper::createToolNode()`
3. Verify edge data structure matches FlowDrop expectations
4. Test with manually created edges in console

### Priority 2: Multi-Agent Visual Representation
1. Detect when a tool is actually an agent (`ai_agents::ai_agent::*`)
2. Decide on UX approach (expand, badge, or drill-down)
3. Update `agentToWorkflow()` to handle nested agents
4. Consider recursive depth limits

### Priority 3: Cleanup
1. Remove `flowdrop_ai_provider` module if no longer needed
2. Export any new config
3. Update documentation

---

## Module Structure

```
web/modules/custom/flowdrop_ui_agents/
├── flowdrop_ui_agents.info.yml
├── flowdrop_ui_agents.services.yml
├── flowdrop_ui_agents.libraries.yml
├── flowdrop_ui_agents.routing.yml
├── src/
│   ├── Controller/Api/
│   │   └── NodesController.php       # Sidebar API (108 tools)
│   ├── Plugin/ModelerApiModeler/
│   │   └── FlowDropAgents.php        # Modeler plugin
│   └── Service/
│       ├── AgentWorkflowMapper.php   # AI Agent ↔ FlowDrop
│       └── WorkflowParser.php        # JSON → Components
├── js/
│   └── flowdrop-agents-editor.js
└── css/
    └── flowdrop-agents-editor.css
```

---

## Key Technical Details

### API Endpoints
- `GET /api/flowdrop-agents/nodes` - All tools + agents
- `GET /api/flowdrop-agents/nodes/by-category` - Grouped
- `GET /api/flowdrop-agents/nodes/{id}/metadata` - Single node

### Handle ID Format (for edges)
```
Input:  ${nodeId}-input-${portId}
Output: ${nodeId}-output-${portId}
```

### Tool ID Formats
- Regular tools: `ai_agent:tool_name` or `tool:tool_name`
- Agent-as-tool: `ai_agents::ai_agent::agent_id` (double colons)

---

## For Next Agent

```
Continue with Phase 7 - Edge Rendering & Multi-Agent

ISSUE 1: Edge lines don't appear when loading agents
- Check handle ID format in AgentWorkflowMapper
- Debug in browser console
- Compare with working FlowDrop workflows

ISSUE 2: Sub-agents not shown with their tools
- Detect ai_agents::ai_agent::* tool IDs
- Decide on visual approach
- Update agentToWorkflow() for nested structure

TEST URL: /admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents
MODULE: web/modules/custom/flowdrop_ui_agents/
```

---

## Reference Files

| File | Purpose |
|------|---------|
| `CLAUDE-NOTES.md` | Technical findings, issue details |
| `CLAUDE-PLANNING.md` | Full implementation plan |
| `CLAUDE.md` | Project guidance, DDEV commands |
