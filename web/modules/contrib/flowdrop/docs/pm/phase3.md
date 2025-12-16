<h2>Phase 3: Frontend & Visual Workflow Editor</h2>

<p>
The objective of this phase is to build the Svelte-based frontend for FlowDrop that provides a visual drag-and-drop workflow editor, 
node configuration panels, and integration with the backend API for workflow management. 
This will enable non-technical users to design and manage workflows without interacting with the underlying entities or database.
</p>

<h3>Phase 3.1: Svelte-Based Visual Editor</h3>
<p>
Create a modern Svelte application embedded in a Drupal module to serve as the primary UI for FlowDrop.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.1.1: Svelte App Scaffolding</strong>
    <ul>
      <li>Initialize a SvelteKit-based frontend inside <code>flowdrop_ui/</code>.</li>
      <li>Configure Vite build and ensure proper asset integration with Drupal’s library system.</li>
      <li>Establish a communication layer for authenticated REST requests to Drupal.</li>
    </ul>
  </li>
  <li><strong>3.1.2: Canvas & Drag-and-Drop</strong>
    <ul>
      <li>Implement a visual canvas for workflows with pan and zoom functionality.</li>
      <li>Allow drag-and-drop of node types from a sidebar to the canvas.</li>
      <li>Enable edge creation between nodes with click-and-drag gestures.</li>
    </ul>
  </li>
  <li><strong>3.1.3: Node Representation</strong>
    <ul>
      <li>Design a visual node component with title, type, and icon placeholders.</li>
      <li>Support color-coded categories for better node type recognition.</li>
      <li>Show execution status badges in future phases (planned for Phase 4).</li>
    </ul>
  </li>
  <li><strong>3.1.4: Auto-Layout & Grid System</strong>
    <ul>
      <li>Introduce a background grid for alignment and snapping nodes to grid.</li>
      <li>Integrate a basic auto-layout engine for organizing complex workflows.</li>
    </ul>
  </li>
</ul>

<h3>Phase 3.2: Node Configuration & Inspector Panel</h3>
<p>
Provide an inspector panel for editing node properties, including input parameters, labels, and metadata.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.2.1: Dynamic Node Form Rendering</strong>
    <ul>
      <li>Fetch node configuration schemas from the backend via REST.</li>
      <li>Render node-specific forms in the side panel using dynamic field components.</li>
      <li>Store configuration in the <code>workflow_node</code> entity’s JSON field.</li>
    </ul>
  </li>
  <li><strong>3.2.2: Validation & Autosave</strong>
    <ul>
      <li>Implement client-side validation based on schema requirements.</li>
      <li>Enable auto-save to persist node changes via AJAX without page reload.</li>
      <li>Provide feedback messages for success, validation errors, or failed saves.</li>
    </ul>
  </li>
  <li><strong>3.2.3: Edge Configuration</strong>
    <ul>
      <li>Allow optional configuration for edges (labels, conditions, or filters).</li>
      <li>Support inline editing directly on the canvas or in the inspector panel.</li>
    </ul>
  </li>
</ul>

<h3>Phase 3.3: Workflow Management UI</h3>
<p>
Develop frontend features for managing workflows including creation, listing, and basic revision handling.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.3.1: Workflow Listing Page</strong>
    <ul>
      <li>List all workflows with title, status, last modified date, and actions.</li>
      <li>Provide filters for status (draft, published, archived).</li>
      <li>Include bulk actions for delete or archive.</li>
    </ul>
  </li>
  <li><strong>3.3.2: Workflow Creation & Editing</strong>
    <ul>
      <li>Enable creating new workflows directly from the UI.</li>
      <li>Provide a workflow properties modal for title, description, and status updates.</li>
      <li>Implement auto-save for canvas edits to prevent data loss.</li>
    </ul>
  </li>
  <li><strong>3.3.3: Revision Awareness</strong>
    <ul>
      <li>Indicate if a workflow is in draft or published mode.</li>
      <li>Notify users when unsaved changes exist.</li>
      <li>Prepare backend endpoints for future revision comparison (Phase 4).</li>
    </ul>
  </li>
</ul>

<h3>Phase 3.4: Drupal Integration Layer</h3>
<p>
Ensure the Svelte application is fully integrated with Drupal’s security, permissions, and routing systems.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.4.1: Route & Access Control</strong>
    <ul>
      <li>Register a custom admin route for the visual editor under <code>/admin/flowdrop/workflows</code>.</li>
      <li>Enforce entity-level access checks for workflow editing and execution.</li>
    </ul>
  </li>
  <li><strong>3.4.2: REST & JSON:API Endpoints</strong>
    <ul>
      <li>Expose CRUD endpoints for workflows, nodes, and edges via REST resources.</li>
      <li>Ensure CSRF token usage for secure write operations.</li>
      <li>Filter queries by user permissions to avoid data leaks.</li>
    </ul>
  </li>
  <li><strong>3.4.3: Library Integration & Build</strong>
    <ul>
      <li>Configure Drupal library entries for Svelte build artifacts (JS and CSS).</li>
      <li>Use <code>drupalSettings</code> for passing configuration to the frontend app.</li>
      <li>Implement cache-busting for assets during deployments.</li>
    </ul>
  </li>
</ul>

<h3>Phase 3.5: UX & Interactivity Enhancements</h3>
<p>
Introduce a smooth and responsive user experience for the editor to increase usability and reduce learning curve.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.5.1: Keyboard Shortcuts</strong>
    <ul>
      <li>Support shortcuts for delete, undo, redo, and copy-paste of nodes.</li>
      <li>Enable quick pan/zoom reset to center the workflow.</li>
    </ul>
  </li>
  <li><strong>3.5.2: Real-Time Feedback</strong>
    <ul>
      <li>Provide live edge previews when connecting nodes.</li>
      <li>Show visual indicators for invalid or incomplete configurations.</li>
    </ul>
  </li>
  <li><strong>3.5.3: Initial Testing & QA</strong>
    <ul>
      <li>Perform functional tests for node creation, editing, and deletion.</li>
      <li>Validate that the UI reflects backend data accurately.</li>
      <li>Log critical frontend events to Drupal for future analytics.</li>
    </ul>
  </li>
</ul>


<h3>Phase 3.6: Deployment & CI/CD Integration</h3>
<p>
Integrate the new visual editor with automated build and deployment processes to ensure smooth delivery and updates.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.6.1: Automated Build Pipeline</strong>
    <ul>
      <li>Configure GitLab or GitHub Actions to build the Svelte application automatically.</li>
      <li>Ensure build artifacts are committed to the appropriate <code>flowdrop_ui</code> library directories.</li>
      <li>Run automated tests on every pull request to maintain quality.</li>
    </ul>
  </li>
  <li><strong>3.6.2: Drupal Cache & Asset Management</strong>
    <ul>
      <li>Trigger <code>drush cr</code> and asset library rebuilds post-deployment.</li>
      <li>Implement asset versioning for cache-busting without manual intervention.</li>
      <li>Validate that newly deployed workflows reflect changes immediately.</li>
    </ul>
  </li>
  <li><strong>3.6.3: Environment-Specific Configuration</strong>
    <ul>
      <li>Allow environment-based API URLs and authentication keys via <code>drupalSettings</code>.</li>
      <li>Verify that dev, staging, and production workflows remain isolated.</li>
      <li>Document deployment steps for non-technical admins to manage upgrades safely.</li>
    </ul>
  </li>
</ul>

<h3>Phase 3 Outcome</h3>
<p>
After completing Phase 3, FlowDrop will provide a fully functional drag-and-drop workflow editor tightly integrated with Drupal. Users will be able to create, configure, and save workflows visually, with full REST-powered synchronization to Drupal entities. This phase lays the foundation for Phase 4, where execution monitoring, real-time logs, and advanced revisioning will be introduced.
</p>




<h3>Phase 3.7: Testing & QA for Visual Editor</h3>
<p>
Establish a rigorous testing and QA process to ensure the frontend workflow editor is reliable, performant, and integrated correctly with Drupal.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>3.7.1: Functional Testing</strong>
    <ul>
      <li>Create Cypress or Playwright test scripts to simulate workflow creation, node configuration, and edge linking.</li>
      <li>Ensure drag-and-drop interactions, auto-save, and inspector updates work in all supported browsers.</li>
      <li>Verify entity CRUD operations are accurately reflected in the UI.</li>
    </ul>
  </li>
  <li><strong>3.7.2: Performance Validation</strong>
    <ul>
      <li>Test rendering performance for workflows with 100+ nodes.</li>
      <li>Optimize Svelte store updates to minimize unnecessary re-renders.</li>
      <li>Measure memory and CPU usage for long sessions using browser DevTools.</li>
    </ul>
  </li>
  <li><strong>3.7.3: Security & Permission Testing</strong>
    <ul>
      <li>Confirm that unauthorized users cannot access or modify workflows.</li>
      <li>Simulate CSRF and access bypass scenarios to validate backend protections.</li>
      <li>Review endpoint responses to ensure sensitive data is never leaked.</li>
    </ul>
  </li>
  <li><strong>3.7.4: UX & Accessibility</strong>
    <ul>
      <li>Validate color contrast and keyboard navigation on the canvas and inspector panel.</li>
      <li>Provide accessible labels for all node and edge elements for screen readers.</li>
      <li>Conduct a usability review with example workflows to refine the UI layout.</li>
    </ul>
  </li>
</ul>