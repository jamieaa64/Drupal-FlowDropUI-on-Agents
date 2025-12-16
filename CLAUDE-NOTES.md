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
