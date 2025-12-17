# Multi-Agent Visualization Implementation Plan

Based on design: `docs/plans/2024-12-17-multi-agent-visualization-design.md`

## Phase 1: View Toggle UI (JavaScript Only)

### Task 1.1: Add Layout Toggle Dropdown
**File:** `web/modules/custom/flowdrop_ui_agents/js/flowdrop-agents-editor.js`

**Changes:**
1. Add `viewSettings` state object at top of `attach` function:
```javascript
const viewSettings = {
  nodeLayout: 'normal',      // 'compact' | 'normal'
  agentExpansion: 'expanded' // 'expanded' | 'grouped' | 'collapsed'
};
```

2. Add navbar dropdown actions after existing "Save AI Agent" button:
```javascript
navbarActions: [
  {
    label: 'Save AI Agent',
    // ... existing
  },
  {
    type: 'dropdown',
    label: 'Layout',
    icon: 'mdi:view-grid',
    options: [
      { label: 'Normal', value: 'normal' },
      { label: 'Compact', value: 'compact' },
    ],
    onChange: function(value) {
      viewSettings.nodeLayout = value;
      updateAllNodesLayout(value);
    },
  },
  {
    type: 'dropdown',
    label: 'Agents',
    icon: 'mdi:robot',
    options: [
      { label: 'Expanded', value: 'expanded' },
      { label: 'Grouped', value: 'grouped' },
      { label: 'Collapsed', value: 'collapsed' },
    ],
    onChange: function(value) {
      viewSettings.agentExpansion = value;
      reloadWorkflowWithExpansion(value);
    },
  },
  {
    label: 'Back to List',
    // ... existing
  },
],
```

3. Add helper function to update node layouts:
```javascript
function updateAllNodesLayout(layout) {
  const app = editorContainer.flowdropApp;
  if (!app) return;

  const workflow = app.getWorkflow();
  workflow.nodes.forEach(node => {
    node.data.config.layout = layout;
  });

  // Trigger re-render (TBD: check FlowDrop API for proper method)
  app.setWorkflow(workflow);
}
```

**Verification:**
- Toggle appears in navbar
- Clicking "Compact" makes all nodes show simplified view
- Clicking "Normal" restores full port display

---

### Task 1.2: Add Expansion Mode AJAX Reload
**File:** `web/modules/custom/flowdrop_ui_agents/js/flowdrop-agents-editor.js`

**Changes:**
1. Add function to reload workflow with new expansion mode:
```javascript
async function reloadWorkflowWithExpansion(expansionMode) {
  const agentId = config.workflowId;
  const url = `/api/flowdrop-agents/workflow/${agentId}?expansion=${expansionMode}`;

  try {
    const response = await fetch(url, {
      headers: { 'Accept': 'application/json' }
    });
    const result = await response.json();

    if (result.success) {
      const app = editorContainer.flowdropApp;
      app.setWorkflow(result.data);
    }
  } catch (error) {
    console.error('Failed to reload workflow:', error);
  }
}
```

**Verification:**
- Dropdown triggers API call
- Console shows request to new endpoint (will 404 until Phase 2)

---

## Phase 2: Backend - Expanded Mode

### Task 2.1: Add Workflow API Endpoint
**File:** `web/modules/custom/flowdrop_ui_agents/flowdrop_ui_agents.routing.yml`

**Add:**
```yaml
flowdrop_ui_agents.api.workflow:
  path: '/api/flowdrop-agents/workflow/{agent_id}'
  defaults:
    _controller: '\Drupal\flowdrop_ui_agents\Controller\Api\WorkflowController::getWorkflow'
  requirements:
    _permission: 'administer ai agents'
  methods: [GET]
  options:
    parameters:
      agent_id:
        type: string
```

**Verification:**
- Route exists (check via `ddev drush route:list | grep flowdrop`)

---

### Task 2.2: Create WorkflowController
**File:** `web/modules/custom/flowdrop_ui_agents/src/Controller/Api/WorkflowController.php` (NEW)

```php
<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ui_agents\Controller\Api;

use Drupal\Core\Controller\ControllerBase;
use Drupal\flowdrop_ui_agents\Service\AgentWorkflowMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WorkflowController extends ControllerBase {

  protected AgentWorkflowMapper $agentWorkflowMapper;

  public function __construct(AgentWorkflowMapper $agentWorkflowMapper) {
    $this->agentWorkflowMapper = $agentWorkflowMapper;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_ui_agents.agent_workflow_mapper')
    );
  }

  public function getWorkflow(string $agent_id, Request $request): JsonResponse {
    $expansion = $request->query->get('expansion', 'expanded');

    $storage = $this->entityTypeManager()->getStorage('ai_agent');
    $agent = $storage->load($agent_id);

    if (!$agent) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => "Agent '$agent_id' not found",
      ], 404);
    }

    $workflow = $this->agentWorkflowMapper->agentToWorkflow(
      $agent,
      $expansion
    );

    return new JsonResponse([
      'success' => TRUE,
      'data' => $workflow,
    ]);
  }
}
```

**Verification:**
- `GET /api/flowdrop-agents/workflow/agent_bundle_lister` returns workflow JSON
- `?expansion=collapsed` parameter is received (behavior same until Task 2.3)

---

### Task 2.3: Update AgentWorkflowMapper for Expansion Modes
**File:** `web/modules/custom/flowdrop_ui_agents/src/Service/AgentWorkflowMapper.php`

**Changes:**

1. Update `agentToWorkflow` signature:
```php
public function agentToWorkflow(
  ConfigEntityInterface $agent,
  string $expansionMode = 'expanded',
  int $depth = 0,
  int $maxDepth = 3
): array {
```

2. In the tools loop, detect sub-agents and handle by mode:
```php
foreach ($tools as $toolId => $enabled) {
  if (!$enabled) {
    continue;
  }

  // Check if this is a sub-agent
  if (str_starts_with($toolId, 'ai_agents::ai_agent::')) {
    $subAgentId = str_replace('ai_agents::ai_agent::', '', $toolId);

    if ($expansionMode === 'collapsed' || $depth >= $maxDepth) {
      // Create collapsed node with badge
      $toolNode = $this->createCollapsedAgentNode($subAgentId, $toolNodeId, $toolIndex);
    }
    elseif ($expansionMode === 'grouped') {
      // Create grouped container (Phase 3)
      $subAgentData = $this->loadSubAgentGrouped($subAgentId, $agentNodeId, $depth + 1, $maxDepth);
      // Merge nodes/edges with parentId set
    }
    else {
      // Expanded: recursively load sub-agent
      $subAgentData = $this->loadSubAgentExpanded($subAgentId, $agentNodeId, $depth + 1, $maxDepth, $expansionMode);
      $nodes = array_merge($nodes, $subAgentData['nodes']);
      $edges = array_merge($edges, $subAgentData['edges']);
    }
  }
  else {
    // Regular tool - existing logic
    $toolNode = $this->createToolNode(...);
  }
  // ... edge creation
}
```

3. Add helper methods:
```php
protected function loadSubAgentExpanded(
  string $subAgentId,
  string $parentNodeId,
  int $depth,
  int $maxDepth,
  string $expansionMode
): array {
  $storage = $this->entityTypeManager->getStorage('ai_agent');
  $subAgent = $storage->load($subAgentId);

  if (!$subAgent) {
    return ['nodes' => [], 'edges' => []];
  }

  // Recursively get sub-agent workflow
  $subWorkflow = $this->agentToWorkflow($subAgent, $expansionMode, $depth, $maxDepth);

  // Add ownerAgentId to all nodes
  foreach ($subWorkflow['nodes'] as &$node) {
    $node['data']['metadata']['ownerAgentId'] = $subAgentId;
  }

  // Create edge from parent to sub-agent
  $subAgentNodeId = 'agent_' . $subAgentId;
  $edges = $subWorkflow['edges'];
  $edges[] = [
    'id' => "edge_{$parentNodeId}_to_{$subAgentNodeId}",
    'source' => $parentNodeId,
    'target' => $subAgentNodeId,
    'sourceHandle' => "{$parentNodeId}-output-tools",
    'targetHandle' => "{$subAgentNodeId}-input-trigger",
    'type' => 'default',
  ];

  return [
    'nodes' => $subWorkflow['nodes'],
    'edges' => $edges,
  ];
}

protected function createCollapsedAgentNode(
  string $subAgentId,
  string $nodeId,
  int $index
): ?array {
  $storage = $this->entityTypeManager->getStorage('ai_agent');
  $subAgent = $storage->load($subAgentId);

  if (!$subAgent) {
    return NULL;
  }

  // Count tools in sub-agent
  $tools = $subAgent->get('tools') ?? [];
  $toolCount = count(array_filter($tools));

  return [
    'id' => $nodeId,
    'type' => 'universalNode',
    'position' => ['x' => 100, 'y' => 100 + ($index * 100)],
    'data' => [
      'nodeId' => $nodeId,
      'label' => $subAgent->label() . " [{$toolCount} tools]",
      'nodeType' => 'agent-collapsed',
      'config' => [
        'agent_id' => $subAgentId,
        'tool_count' => $toolCount,
      ],
      'metadata' => [
        'id' => 'ai_agents::ai_agent::' . $subAgentId,
        'isCollapsedAgent' => TRUE,
        'ownerAgentId' => $subAgentId,
        'icon' => 'mdi:robot-outline',
        'color' => 'var(--color-ref-purple-300)',
        // ... inputs/outputs
      ],
    ],
  ];
}
```

**Verification:**
- `?expansion=expanded` shows sub-agents with their tools as separate nodes
- `?expansion=collapsed` shows sub-agents as single node with "[X tools]" badge
- Recursive depth stops at 3 levels

---

## Phase 3: Grouped Mode

### Task 3.1: Add Grouped Mode Loading
**File:** `web/modules/custom/flowdrop_ui_agents/src/Service/AgentWorkflowMapper.php`

Add method:
```php
protected function loadSubAgentGrouped(
  string $subAgentId,
  string $parentNodeId,
  int $depth,
  int $maxDepth
): array {
  // Similar to expanded, but add 'parentId' to nodes for visual grouping
  $subWorkflow = $this->loadSubAgentExpanded($subAgentId, $parentNodeId, $depth, $maxDepth, 'grouped');

  foreach ($subWorkflow['nodes'] as &$node) {
    $node['parentId'] = 'group_' . $subAgentId;
    $node['extent'] = 'parent'; // Keep within group bounds
  }

  // Add group container node
  $groupNode = [
    'id' => 'group_' . $subAgentId,
    'type' => 'group',
    'position' => ['x' => 100, 'y' => 100],
    'data' => [
      'label' => $subAgent->label(),
    ],
    'style' => [
      'width' => 400,
      'height' => 300,
    ],
  ];

  array_unshift($subWorkflow['nodes'], $groupNode);

  return $subWorkflow;
}
```

**Verification:**
- `?expansion=grouped` shows sub-agents as visual containers
- Tool nodes appear inside the container box

---

### Task 3.2: Add CSS for Grouped Containers
**File:** `web/modules/custom/flowdrop_ui_agents/css/flowdrop-agents-editor.css`

```css
/* Grouped agent container */
.flowdrop-agents-editor-container .react-flow__node-group {
  background: rgba(139, 92, 246, 0.1);
  border: 2px dashed var(--color-ref-purple-300);
  border-radius: 8px;
  padding: 40px 10px 10px;
}

.flowdrop-agents-editor-container .react-flow__node-group::before {
  content: attr(data-label);
  position: absolute;
  top: 8px;
  left: 12px;
  font-weight: 600;
  color: var(--color-ref-purple-500);
}
```

**Verification:**
- Grouped mode shows purple dashed container around sub-agent tools

---

## Phase 4: Multi-Entity Save

### Task 4.1: Update WorkflowParser to Group by Owner
**File:** `web/modules/custom/flowdrop_ui_agents/src/Service/WorkflowParser.php`

Add method:
```php
protected function groupNodesByOwner(array $nodes): array {
  $grouped = [];

  foreach ($nodes as $node) {
    $ownerId = $node['data']['metadata']['ownerAgentId'] ?? 'root';
    if (!isset($grouped[$ownerId])) {
      $grouped[$ownerId] = [];
    }
    $grouped[$ownerId][] = $node;
  }

  return $grouped;
}
```

**Verification:**
- Nodes correctly grouped by ownerAgentId

---

### Task 4.2: Add Topological Sort for Save Order
**File:** `web/modules/custom/flowdrop_ui_agents/src/Service/WorkflowParser.php`

Add method:
```php
protected function topologicalSortAgents(array $nodesByOwner, array $edges): array {
  // Build dependency graph
  $dependencies = [];
  foreach ($nodesByOwner as $agentId => $nodes) {
    $dependencies[$agentId] = [];
  }

  // Find dependencies from edges (agent A -> sub-agent B means B must save first)
  foreach ($edges as $edge) {
    $sourceOwner = $this->findNodeOwner($edge['source'], $nodesByOwner);
    $targetOwner = $this->findNodeOwner($edge['target'], $nodesByOwner);

    if ($sourceOwner !== $targetOwner && $sourceOwner && $targetOwner) {
      // Target depends on source (but we save target first since it's the sub-agent)
      $dependencies[$sourceOwner][] = $targetOwner;
    }
  }

  // Kahn's algorithm for topological sort
  $sorted = [];
  $noIncoming = array_keys(array_filter($dependencies, fn($d) => empty($d)));

  while (!empty($noIncoming)) {
    $n = array_shift($noIncoming);
    $sorted[] = $n;

    foreach ($dependencies as $agent => &$deps) {
      if (($key = array_search($n, $deps)) !== FALSE) {
        unset($deps[$key]);
        if (empty($deps)) {
          $noIncoming[] = $agent;
        }
      }
    }
  }

  return array_reverse($sorted); // Leaf agents first
}
```

**Verification:**
- Sub-agents sorted before parent agents

---

### Task 4.3: Update toComponents for Multi-Save
**File:** `web/modules/custom/flowdrop_ui_agents/src/Service/WorkflowParser.php`

Update `toComponents`:
```php
public function toComponents(array $workflow): array {
  $components = [];

  $nodesByOwner = $this->groupNodesByOwner($workflow['nodes'] ?? []);
  $sortedAgents = $this->topologicalSortAgents($nodesByOwner, $workflow['edges'] ?? []);

  foreach ($sortedAgents as $agentId) {
    $agentNodes = $nodesByOwner[$agentId] ?? [];
    $agentComponents = $this->createAgentComponents($agentId, $agentNodes, $workflow['edges'] ?? []);
    $components = array_merge($components, $agentComponents);
  }

  return $components;
}
```

**Verification:**
- Saving workflow with multiple agents creates components for each
- Sub-agents saved before parent agents

---

## Phase 5: New Agent Creation

### Task 5.1: Handle New Agents in Parser
**File:** `web/modules/custom/flowdrop_ui_agents/src/Service/WorkflowParser.php`

Detect new agents (no existing entity):
```php
protected function createAgentComponents(string $agentId, array $nodes, array $edges): array {
  $storage = \Drupal::entityTypeManager()->getStorage('ai_agent');
  $existingAgent = $storage->load($agentId);

  $isNew = ($existingAgent === NULL);

  // Find the agent node
  $agentNode = NULL;
  foreach ($nodes as $node) {
    if (($node['data']['nodeType'] ?? '') === 'agent') {
      $agentNode = $node;
      break;
    }
  }

  if (!$agentNode && $isNew) {
    // New agent without agent node - error
    throw new \RuntimeException("New agent '$agentId' must have an agent node");
  }

  // ... create components with isNew flag
}
```

**Verification:**
- Creating new agent node in UI and saving creates new AI Agent entity
- New sub-agents created before parent references them

---

## Phase 6: Update Documentation

### Task 6.1: Update CLAUDE-BRANCH.md
Document Phase 7 completion status.

### Task 6.2: Update CLAUDE-NOTES.md
Add technical findings from implementation.

---

## Testing Checklist

- [ ] Layout toggle switches all nodes between normal/compact
- [ ] Agents dropdown triggers API reload
- [ ] Expanded mode shows full hierarchy
- [ ] Grouped mode shows visual containers
- [ ] Collapsed mode shows badge with tool count
- [ ] Saving expanded workflow updates all agents
- [ ] New agents can be created inline
- [ ] Sub-agents saved before parent agents
- [ ] Recursion stops at depth 3
- [ ] Edge rendering still works after changes
