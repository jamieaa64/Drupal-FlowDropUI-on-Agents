<h3>Phase 4: Execution Engine & Monitoring</h3>
<p>
Phase 4 focuses on implementing a robust workflow execution engine within FlowDrop, along with real-time monitoring and logging capabilities. This phase ensures that workflows created via the visual editor can be reliably executed, monitored, and debugged from within Drupal.
</p>

<h4>Goals</h4>
<ul>
  <li>Enable execution of workflows created in the visual editor.</li>
  <li>Provide real-time job monitoring and log inspection.</li>
  <li>Support error handling, retries, and execution status tracking.</li>
  <li>Integrate execution insights into Drupal entities for auditing and reporting.</li>
</ul>

<h3>Phase 4.1: Workflow Execution Engine</h3>
<p>
Implement the core engine responsible for executing FlowDrop workflows based on the defined nodes and edges.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>4.1.1: Node Execution Runtime</strong>
    <ul>
      <li>Build a PHP-based runtime that sequentially and/or concurrently executes nodes based on dependencies.</li>
      <li>Introduce a Node Execution Context to pass data between nodes during execution.</li>
      <li>Support execution hooks for pre-node and post-node processing.</li>
    </ul>
  </li>
  <li><strong>4.1.2: Job & Queue Management</strong>
    <ul>
      <li>Create a <code>flowdrop_job</code> entity to track workflow executions.</li>
      <li>Leverage Drupal Queue API for asynchronous execution of long-running jobs.</li>
      <li>Allow manual and scheduled execution triggers via cron or custom events.</li>
    </ul>
  </li>
  <li><strong>4.1.3: Error Handling & Recovery</strong>
    <ul>
      <li>Log node-level errors and prevent downstream execution failures when possible.</li>
      <li>Implement automatic retries for transient failures with configurable limits.</li>
      <li>Provide a workflow-level “Failed” and “Completed with Warnings” status.</li>
    </ul>
  </li>
</ul>

<h3>Phase 4.2: Real-Time Monitoring & Logging</h3>
<p>
Add live monitoring tools and detailed logging to improve transparency and facilitate debugging of workflow executions.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>4.2.1: Job Status Dashboard</strong>
    <ul>
      <li>Build a Drupal admin page to list all running, completed, and failed jobs.</li>
      <li>Provide filtering by workflow, execution date, and status.</li>
      <li>Include real-time updates using WebSockets or AJAX polling.</li>
    </ul>
  </li>
  <li><strong>4.2.2: Node-Level Logging</strong>
    <ul>
      <li>Record input/output payloads for each node during execution.</li>
      <li>Store execution logs as a separate log entity or JSON field in <code>flowdrop_job</code>.</li>
      <li>Expose logs through both the Drupal UI and an optional API endpoint.</li>
    </ul>
  </li>
  <li><strong>4.2.3: Visual Execution Trace</strong>
    <ul>
      <li>Highlight executed nodes directly on the visual editor canvas.</li>
      <li>Provide visual indicators for success, warning, or failure states.</li>
      <li>Enable download/export of execution traces for offline analysis.</li>
    </ul>
  </li>
</ul>

<h3>Phase 4.3: Metrics, Auditing & Notifications</h3>
<p>
Extend the monitoring system with historical insights and alerting capabilities to ensure enterprise-grade reliability.
</p>

<h4>Tasks</h4>
<ul>
  <li><strong>4.3.1: Metrics & Reporting</strong>
    <ul>
      <li>Track average execution times, node bottlenecks, and failure rates.</li>
      <li>Provide a summary dashboard of workflow performance trends.</li>
      <li>Integrate with Drupal Views for flexible reporting.</li>
    </ul>
  </li>
  <li><strong>4.3.2: Audit Trail</strong>
    <ul>
      <li>Maintain a complete execution history per workflow revision.</li>
      <li>Store who triggered the execution and from which environment (manual, cron, API).</li>
      <li>Ensure compliance with enterprise logging standards.</li>
    </ul>
  </li>
  <li><strong>4.3.3: Notifications & Alerts</strong>
    <ul>
      <li>Send email or webhook notifications on job failure or success.</li>
      <li>Allow configurable thresholds for “slow execution” or “high error rate” warnings.</li>
      <li>Provide integration points for external observability tools like Prometheus or ELK.</li>
    </ul>
  </li>
</ul>

<h3>Phase 4 Outcome</h3>
<p>
Upon completing Phase 4, FlowDrop will provide a production-ready execution engine with robust logging, monitoring, and auditing. Administrators and developers will have full visibility into workflow performance, errors, and historical trends, enabling confident deployment in enterprise environments.
</p>
