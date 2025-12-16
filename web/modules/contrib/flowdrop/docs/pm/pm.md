# FlowDrop: Phased Roadmap (Comprehensive)

This consolidated roadmap captures all phases of FlowDrop development from inception to enterprise hardening, including detailed breakdowns of sub-phases. It combines all previously discussed phases (1 to 6) with their tasks, sub-phases, and deliverables, ensuring nothing from the prior documents is missed.

---

## Phase 1: Core Module & Entity Architecture

**Objective:**  
Establish the foundational Drupal module architecture, entity schema, and service definitions for FlowDrop.

### Deliverables
- **Module structure:**  
  - `flowdrop` (base module)
  - `flowdrop_workflow` (workflow entity management)
  - `flowdrop_node_type` (node type definitions)
  - `flowdrop_node_category` (categorization)
  - `flowdrop_pipeline` (pipeline management)
- **Entity definitions:**  
  - Workflow (config entity)  
  - Node (content entity or revisionable entity)  
  - Pipeline (config entity)  
  - Job (content entity for execution tracking)
- **Basic UI & CRUD:** Admin listing for workflows and node types
- **Service container integration:**  
  - Node runtime service  
  - Workflow manager service

### Tasks
1. Define all entity types using `EntityTypeInterface` and configuration schema.
2. Create storage and base tables using Drupal’s entity schema.
3. Add plugin discovery stubs for node processors.
4. Implement CRUD and minimal admin UI for workflows.
5. Export schema and configs for contrib readiness.

---

## Phase 2: Visual Workflow Editor & Node System

**Objective:**  
Provide a drag-and-drop interface for workflow creation and a plugin-based node execution framework.

### Phase 2.1: Svelte Frontend & JSON Sync
- Embed a Svelte-based visual editor in `flowdrop_ui`.
- Define endpoints to fetch workflows, nodes, and edges.
- Enable drag-and-drop creation and saving of nodes.

### Phase 2.2: Node Processor Plugin System
- Create `FlowDropNodeProcessor` plugins with attribute-based discovery.
- Support multiple categories: AI, data transformation, API.
- Implement input/output data schema for nodes.

### Phase 2.3: API Layer & JS Event System
- Primary communication: browser-based JS Events for real-time editor updates.
- Optional REST/JSON:API support for workflows and nodes.
- Design system to broadcast events: node_added, node_deleted, workflow_saved.

### Deliverables
- Visual editor integrated with Drupal backend.
- Workflow save and load round-trip.
- Node processor discovery system.
- Edge and connection data persisted.

---

## Phase 3: Job Execution & Queue Management

**Objective:**  
Enable reliable asynchronous workflow execution with a queue-based job system.

### Deliverables
- `flowdrop_job` module for execution tracking.
- Queue-based job dispatch using Drupal Queue API.
- Execution lifecycle:
  - Pending
  - Running
  - Completed
  - Failed
- Administrative UI for job and workflow run inspection.

### Tasks
1. Define Job entity with fields for status, start/end timestamps, logs.
2. Implement queue workers for node execution.
3. Add retry, timeout, and failure recording.
4. Integrate basic watchdog logging.
5. Provide job dashboard with filters and bulk operations.

---

## Phase 4: Execution Engine & Monitoring

**Objective:**  
Introduce DAG-based execution with robust monitoring, parallel processing, and real-time tracking.

### Deliverables
- `flowdrop_runner` module as the execution engine.
- DAG (Directed Acyclic Graph) node execution with dependencies.
- Monitoring dashboard with execution timelines and node-level logs.
- Parallel job execution and retry policies.

### Tasks
1. Implement DAG traversal and dependency resolution.
2. Allow nodes to execute in parallel where dependencies permit.
3. Provide visual monitoring of workflow runs.
4. Persist execution logs and support export for debugging.
5. Integrate with Drupal logging (watchdog) and optional syslog.

---

## Phase 5: AI & External Integrations

**Objective:**  
Enable AI-driven workflows and external API interactions.

### Deliverables
- `flowdrop_ai` module for AI node processors.
- External service integrations:
  - LLM call (OpenAI, Hugging Face)
  - Embedding generation
  - Sentiment analysis
- API credential storage with encryption.

### Tasks
1. Create AI node types with configurable prompts and model selection.
2. Implement credential storage with `key` module or Drupal config override.
3. Add caching and rate limiting for external API calls.
4. Introduce nodes for file ingestion and dataset processing.
5. Show node input/output previews in the visual editor.

---

## Phase 6: Security, Governance & Enterprise Hardening

**Objective:**  
Make FlowDrop enterprise-grade with access control, auditing, and high availability.

### Deliverables
- Fine-grained access control and SSO integration.
- Workflow change and execution auditing with SIEM export.
- Multi-server job execution with failover.
- Observability and monitoring integration.

### Tasks

#### 6.1 Security Framework
- Role- and workflow-level permissions.
- Encrypted API keys and credentials.
- Sandbox execution with CPU/memory limits.

#### 6.2 Governance & Compliance
- Workflow versioning with immutable audit logs.
- Staged promotion from dev to prod workflows.
- Compliance with GDPR/HIPAA, retention policies.

#### 6.3 Enterprise Hardening
- Multi-worker queue execution with HA failover.
- Prometheus/Grafana monitoring and alerting.
- Backup and disaster recovery documentation.

---

## Final Outcome

After Phase 6, FlowDrop will offer:

- A robust **visual workflow editor** with AI and automation nodes
- A **DAG-based execution engine** with queue-backed reliability
- **Enterprise security, auditing, and compliance** capabilities
- Extensible **plugin architecture** for new node processors and integrations

---

This document is a **living blueprint** for FlowDrop development and is suitable for inclusion in Drupal.org project documentation or internal planning artifacts.
