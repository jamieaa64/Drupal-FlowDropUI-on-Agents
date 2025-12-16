<h2>Phase 1: Backend Foundations & Entity Architecture</h2>

<p>
The focus of this phase is to establish the foundational backend architecture for FlowDrop.
This includes defining workflow-related Drupal entities, setting up plugin discovery for node processors,
and creating the execution engine that will eventually power the visual workflow editor.
</p>

<h3>Phase 1.1: Workflow Entity Architecture</h3>
<p>
Create core Drupal entities for managing workflows, nodes, and edges.
These entities form the backbone for storing and versioning workflows.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>1.1.1: Workflow Entity</strong>
    <ul>
      <li>Define a <code>workflow</code> content entity for storing workflow metadata.</li>
      <li>Include fields for title, status (draft, published, archived), and description.</li>
      <li>Enable revision support and default revision handling.</li>
      <li>Implement workflow-specific permissions for create, edit, and execute.</li>
    </ul>
  </li>
  <li><strong>1.1.2: Node Entity</strong>
    <ul>
      <li>Define a <code>workflow_node</code> entity for individual workflow nodes.</li>
      <li>Store references to the parent workflow and node type definition.</li>
      <li>Include a JSON field for node configuration parameters.</li>
      <li>Enable positional metadata for eventual visual rendering.</li>
    </ul>
  </li>
  <li><strong>1.1.3: Edge Entity</strong>
    <ul>
      <li>Define a <code>workflow_edge</code> entity to store connections between nodes.</li>
      <li>Store source and target node references.</li>
      <li>Optionally include edge conditions or labels in a JSON field.</li>
    </ul>
  </li>
  <li><strong>1.1.4: Revision & Draft Handling</strong>
    <ul>
      <li>Ensure workflows are revisionable and support draft states by default.</li>
      <li>Edges and nodes inherit revision support from their parent workflow.</li>
      <li>Leverage Drupal’s <code>EntityStorageInterface</code> for automatic revisioning.</li>
    </ul>
  </li>
</ul>

<h3>Phase 1.2: Plugin System for Node Processors</h3>
<p>
Implement the plugin discovery system for node processors, which will serve as the functional units of workflows.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>1.2.1: Plugin Type Definition</strong>
    <ul>
      <li>Create a <code>FlowDropNodeProcessor</code> plugin type using Drupal's annotated class system.</li>
      <li>Define plugin metadata including label, category, description, and input/output definitions.</li>
    </ul>
  </li>
  <li><strong>1.2.2: Attribute-Based Discovery</strong>
    <ul>
      <li>Implement PHP 8 Attributes for plugin discovery to allow concise definitions.</li>
      <li>Scan <code>src/Plugin/FlowDropNodeProcessor</code> for available node processors.</li>
    </ul>
  </li>
  <li><strong>1.2.3: Node Execution Interface</strong>
    <ul>
      <li>Define a <code>NodeProcessorInterface</code> for standardized execution of nodes.</li>
      <li>Support input/output data passing between nodes in a workflow run.</li>
    </ul>
  </li>
  <li><strong>1.2.4: Example Node Plugins</strong>
    <ul>
      <li>Implement simple example processors (e.g., LoggerNode, DelayNode, MergeNode) to test the system.</li>
      <li>Validate that plugins can be discovered and executed dynamically.</li>
    </ul>
  </li>
</ul>

<h3>Phase 1.3: Node Runtime & Workflow Execution Engine</h3>
<p>
Create the backend runtime that can sequentially execute nodes in a workflow, respecting edges and conditions.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>1.3.1: Execution Engine Skeleton</strong>
    <ul>
      <li>Develop a <code>NodeRuntime</code> service to handle workflow execution.</li>
      <li>Execute nodes in topological order, respecting edge connections.</li>
      <li>Provide hooks for preprocessing and postprocessing execution.</li>
    </ul>
  </li>
  <li><strong>1.3.2: Data Context Handling</strong>
    <ul>
      <li>Maintain a workflow context object that carries input/output data between nodes.</li>
      <li>Support both synchronous and queued (asynchronous) execution.</li>
    </ul>
  </li>
  <li><strong>1.3.3: Error Handling & Logging</strong>
    <ul>
      <li>Implement granular exception handling for node execution failures.</li>
      <li>Log execution events and errors to the Drupal watchdog system.</li>
      <li>Prepare for future integration with a UI-based execution monitor.</li>
    </ul>
  </li>
</ul>

<h3>Phase 1.4: Workflow Job Management</h3>
<p>
Introduce the job execution concept to manage queued workflow runs and provide isolation for long-running processes.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>1.4.1: Workflow Job Entity</strong>
    <ul>
      <li>Create a <code>workflow_job</code> entity to track executions.</li>
      <li>Store references to the workflow, execution status, start/end timestamps, and logs.</li>
    </ul>
  </li>
  <li><strong>1.4.2: Queue & Batch Integration</strong>
    <ul>
      <li>Integrate Drupal Queue API for asynchronous job processing.</li>
      <li>Provide batch execution options for large or multi-branch workflows.</li>
    </ul>
  </li>
  <li><strong>1.4.3: Execution Reporting</strong>
    <ul>
      <li>Store execution logs per node for troubleshooting and audit purposes.</li>
      <li>Expose logs via a simple admin UI and prepare for REST/JSON:API exposure.</li>
    </ul>
  </li>
</ul>

<h3>Phase 1.5: Initial Integration & Smoke Tests</h3>
<p>
Conduct foundational testing to ensure that workflows can be created, stored, and executed in a minimal setup.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>1.5.1: Entity CRUD Tests</strong>
    <ul>
      <li>Verify that workflow, node, and edge entities can be created, updated, and deleted via standard Drupal APIs.</li>
      <li>Ensure revisioning and draft handling work as expected.</li>
    </ul>
  </li>
  <li><strong>1.5.2: Plugin Discovery Tests</strong>
    <ul>
      <li>Test discovery and instantiation of node processor plugins.</li>
      <li>Validate that example plugins execute within the NodeRuntime service.</li>
    </ul>
  </li>
  <li><strong>1.5.3: Execution Engine Test Run</strong>
    <ul>
      <li>Execute a simple workflow end-to-end with multiple nodes.</li>
      <li>Validate log entries and output data correctness.</li>
    </ul>
  </li>
</ul>
