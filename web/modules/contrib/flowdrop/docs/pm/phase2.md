<h2>Phase 2: Visual Workflow Editor (Primary UI Focus)</h2>

<p>
The goal of this phase is to deliver the first iteration of a drag-and-drop visual editor using Svelte with Drupal integration. 
This editor will allow users to visually create workflows by placing nodes, connecting them, and configuring their parameters.
</p>

<h3>Phase 2.1: Svelte-Based Workflow Editor</h3>
<p>
Implement the Svelte-based drag-and-drop editor integrated with the <code>flowdrop_ui</code> module. 
This forms the core user interface for creating workflows visually.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>2.1.1: Svelte Project Setup</strong>
    <ul>
      <li>Initialize a SvelteKit project within <code>flowdrop_ui</code>.</li>
      <li>Configure Vite build to integrate with Drupal library system.</li>
      <li>Ensure hot-reload and efficient dev workflow via npm/yarn commands.</li>
    </ul>
  </li>
  <li><strong>2.1.2: Canvas & Node Rendering</strong>
    <ul>
      <li>Create the main workflow canvas component.</li>
      <li>Implement node rendering with support for multiple node types.</li>
      <li>Add drag-and-drop placement of nodes with position persistence.</li>
      <li>Introduce snapping grid and auto-layout options.</li>
    </ul>
  </li>
  <li><strong>2.1.3: Edge Connections</strong>
    <ul>
      <li>Enable directional edges between nodes.</li>
      <li>Implement visual cues for valid/invalid connections.</li>
      <li>Support node input/output port definitions.</li>
    </ul>
  </li>
  <li><strong>2.1.4: Inline Node Editing</strong>
    <ul>
      <li>Allow inline editing of node labels and parameters.</li>
      <li>Auto-save configuration changes to the workflow draft.</li>
    </ul>
  </li>
</ul>

<h3>Phase 2.2: Real-Time UX Enhancements</h3>
<p>
Improve the interactivity of the visual editor with real-time feedback, validation, and layout adjustments.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>2.2.1: Interactive Edge Validation</strong>
    <ul>
      <li>Validate connections dynamically to ensure compatible port types.</li>
      <li>Display real-time errors for invalid or incomplete nodes.</li>
    </ul>
  </li>
  <li><strong>2.2.2: Node Status Indicators</strong>
    <ul>
      <li>Highlight incomplete or invalid nodes with color-coded borders.</li>
      <li>Add hover tooltips for validation errors or missing configuration.</li>
    </ul>
  </li>
  <li><strong>2.2.3: Auto-Layout and Snapping Grid</strong>
    <ul>
      <li>Provide optional auto-layout for cleaner workflow visualization.</li>
      <li>Introduce manual grid snapping for user-managed layouts.</li>
    </ul>
  </li>
</ul>

<h3>Phase 2.3: Workflow API & Event System</h3>
<p>
Focus on defining a flexible communication mechanism between the Svelte editor and Drupal. 
Primary communication will be JavaScript events, with optional REST/JSON:API support.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>2.3.1: JS Event Bus</strong>
    <ul>
      <li>Define a custom event system for workflow interactions:
        <ul>
          <li><code>workflow:node:add</code>, <code>workflow:node:delete</code></li>
          <li><code>workflow:edge:add</code>, <code>workflow:edge:delete</code></li>
          <li><code>workflow:save</code>, <code>workflow:execute</code></li>
        </ul>
      </li>
      <li>Emit and listen to these events for UI-state synchronization.</li>
    </ul>
  </li>
  <li><strong>2.3.2: Optional REST API</strong>
    <ul>
      <li>Expose endpoints for:
        <ul>
          <li>Loading workflows</li>
          <li>Saving workflows as draft/revision</li>
          <li>Triggering workflow execution and returning job status</li>
        </ul>
      </li>
    </ul>
  </li>
  <li><strong>2.3.3: Auto-Persistence</strong>
    <ul>
      <li>Automatically persist workflow changes via events or REST calls.</li>
      <li>Ensure draft revisions are created without manual user action.</li>
    </ul>
  </li>
</ul>

<h3>Phase 2.4: Draft & Revision Management</h3>
<p>
Implement auto-save and revisioning capabilities for visual workflows using Drupal entities.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>2.4.1: Draft Handling</strong>
    <ul>
      <li>Enable automatic draft creation when the user makes changes.</li>
      <li>Provide a visual indicator in the UI for unsaved or draft states.</li>
    </ul>
  </li>
  <li><strong>2.4.2: Revision Management</strong>
    <ul>
      <li>Each workflow save generates a new revision.</li>
      <li>Provide an API to retrieve revision history for a workflow entity.</li>
    </ul>
  </li>
  <li><strong>2.4.3: Conflict Detection</strong>
    <ul>
      <li>Detect and handle concurrent edits with server-side validation.</li>
      <li>Prompt users with merge or overwrite options if conflicts occur.</li>
    </ul>
  </li>
</ul>

<h3>Phase 2.5: Integration with Node Definition Plugins</h3>
<p>
Integrate the visual editor with <code>FlowDropNodeProcessor</code> plugins to provide a dynamic, category-based node library.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>2.5.1: Node Type Discovery</strong>
    <ul>
      <li>Scan for <code>FlowDropNodeProcessor</code> plugins via attribute discovery.</li>
      <li>Expose metadata including ID, label, category, ports, and config schema.</li>
    </ul>
  </li>
  <li><strong>2.5.2: Node Library in UI</strong>
    <ul>
      <li>Render categorized node library with drag-and-drop support.</li>
      <li>Provide search and filter for faster node discovery.</li>
    </ul>
  </li>
  <li><strong>2.5.3: Metadata Caching & Refresh</strong>
    <ul>
      <li>Cache plugin discovery results and refresh via Drush or admin route.</li>
      <li>Notify the UI via <code>workflow:nodes:refresh</code> event.</li>
    </ul>
  </li>
  <li><strong>2.5.4: Category & Icon Support</strong>
    <ul>
      <li>Use color-coded categories and icons for better UX.</li>
      <li>Support collapsible node library sections.</li>
    </ul>
  </li>
  <li><strong>2.5.5: Schema-Driven Node Configuration</strong>
    <ul>
      <li>Auto-generate inline configuration forms based on plugin schema.</li>
      <li>Validate changes in real-time using <code>workflow:validation</code> events.</li>
    </ul>
  </li>
</ul>

<h4>Phase 2 Deliverables</h4>
<ul>
  <li>Functional Svelte-based visual workflow editor embedded in Drupal.</li>
  <li>Interactive drag-and-drop nodes with edges and inline parameter editing.</li>
  <li>JS event-driven workflow persistence and optional REST API integration.</li>
  <li>Auto-save and revision management for workflow entities.</li>
  <li>Dynamic, category-based node library powered by <code>FlowDropNodeProcessor</code> plugins.</li>
</ul>
