# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and immediate next steps.

- **Current Branch**: `feature/flowdrop-phase2-implementation`
- **Phase**: 6 - Module Restructure & Modeler API Integration
- **For historical context**: See `CLAUDE-NOTES.md` and `CLAUDE-PLANNING.md`

---

## User Comments (Phase 6 Direction)

### Key Changes Requested

1. **New Custom Module**: Create `flowdrop_ui_agents` in `web/modules/custom/`
   - **ALL new code goes here** - not in `flowdrop_ai_provider` or `flowdrop_ui`
   - Move relevant code from `flowdrop_ai_provider` (contrib) to custom module
   - Keep track of any patches needed for contrib modules
   - May need changes to FlowDrop UI library itself (via patches)

2. **Reference File Change**: Stop using ECA AI Integration as reference
   - **Wrong reference**: `ai_integration_eca/modules/agents/` (that's for AI Agents inside ECA)
   - **Correct reference**: `ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php`
   - This shows how Modeler API connects FlowDrop to AI Agents

3. **BPMN.io Reference**: Install `bpmn_io` module (version 3 branch) to see working example
   - Has example of loading/saving working with Modeler API

4. **Drupal Forms Integration**: Config pages should load Drupal forms when editing Tools or Agents
   - May need blend of Drupal form and FlowDrop UI
   - Configuration form in FlowDrop needs all important Agent fields (like instructions)
   - User doesn't feel comfortable saving yet - needs to be easier to use

---

## Immediate Tasks for Phase 6

### Task 6.1: Study Correct Reference Implementation
**File**: `web/modules/contrib/ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php`

Questions to answer:
- How does this plugin expose AI Agents to Modeler API?
- What methods does it implement?
- How does save/load work via Modeler API?

### Task 6.2: Install and Study BPMN.io Module
**Module**: `bpmn_io` (version 3 branch)

Questions to answer:
- How does it use Modeler API?
- How does the loading/saving example work?
- Can we use FlowDrop UI as a modeler with this pattern?

### Task 6.3: Create New Custom Module
**Location**: `web/modules/custom/flowdrop_ui_agents/`

**Important**: ALL code for this feature goes in this custom module. Do NOT add to:
- `flowdrop_ai_provider` (contrib)
- `flowdrop_ui` (contrib)
- `flowdrop` (contrib)

Structure:
```
flowdrop_ui_agents/
├── flowdrop_ui_agents.info.yml
├── flowdrop_ui_agents.module
├── flowdrop_ui_agents.services.yml
├── flowdrop_ui_agents.routing.yml
├── src/
│   ├── Controller/           # Move from flowdrop_ai_provider
│   ├── Services/             # Move from flowdrop_ai_provider
│   └── Plugin/
│       └── ... (TBD based on Modeler API research)
├── js/                       # Move from flowdrop_ai_provider
└── patches/
    └── README.md  # Track patches to contrib modules
```

### Task 6.4: Determine Patch Strategy
- What changes to `flowdrop` or `flowdrop_ui` are needed?
- How to track and manage patches?
- Can we use composer patches?

### Task 6.5: Improve Configuration Forms
- Ensure all Agent fields are editable in FlowDrop
- Consider hybrid approach: FlowDrop UI + Drupal forms
- Make save process feel safe/reliable

---

## Current State (What Exists)

### Files in `flowdrop_ai_provider` (contrib)
These were created in Phases 1-5 but may need to move to custom module:

- `FlowDropAgentMapper.php` - Bidirectional workflow ↔ agent conversion
- `ToolDataProvider.php` - Tool information for sidebar
- `AgentsController.php` / `ToolsController.php` - REST API
- `AgentEditorController.php` - Edit pages
- `ai-agent-editor.js` - FlowDrop initialization

### What Works
- Loading agent as FlowDrop workflow ✅
- Tool sidebar with drag/drop ✅
- Tool-agent edge connections ✅
- Config panel with JSON Schema ✅
- API endpoints ✅

### What Needs Work
- Save functionality not fully tested
- Configuration form completeness
- Modeler API integration (proper way)
- Module location (contrib → custom)

---

## Questions for Research

1. **ModelerApiModelOwner Plugin**: What interface/methods does `Agent.php` implement?
2. **FlowDrop + Modeler API**: Can FlowDrop UI be used as a Modeler with Modeler API?
3. **Form Integration**: How to blend FlowDrop config panels with full Drupal forms?
4. **Patch Management**: Best practice for tracking contrib module patches?

---

## Next Agent Instructions

```
Continue Phase 6 for FlowDrop AI Provider.
Branch: feature/flowdrop-phase2-implementation

IMPORTANT: Read user comments above - key direction change:
1. Study ai_agents/src/Plugin/ModelerApiModelOwner/Agent.php (NOT ECA integration)
2. Install bpmn_io module (v3 branch) to see working example
3. Create new custom module: web/modules/custom/flowdrop_ui_agents/
4. Determine what patches are needed to contrib modules

Start with Task 6.1 - study the Agent.php ModelerApiModelOwner plugin.
```

---

## Reference Files

| File | Purpose |
|------|---------|
| `CLAUDE-NOTES.md` | Historical findings, data structures, lessons learned |
| `CLAUDE-PLANNING.md` | Full implementation plan, phase details, open questions |
| `CLAUDE.md` | General project guidance, DDEV commands |
