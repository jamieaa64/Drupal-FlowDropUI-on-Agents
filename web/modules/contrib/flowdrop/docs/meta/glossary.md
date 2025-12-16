# Glossary

- **Batch Processing**: Executing multiple workflow runs over varying inputs, typically via queues or Drupal Batch API.
- **Deterministic Execution**: Given the same inputs and seed, produces reproducible outputs; randomness is controlled via a seed.
- **Execution Session**: A single run of a workflow carrying transient context (inputs, intermediates, outputs) across nodes.
- **Field Mapping**: Configuration that binds Drupal fields or form inputs to workflow‑node parameters.
- **Headless Mode**: Running workflows via HTTP APIs without Drupal‑rendered UIs.
- **Idempotency**: Re‑running a node with identical inputs/config yields the same state and side‑effects.
- **Image/Asset Proxy**: A Drupal endpoint that serves or caches artifacts produced by external runtimes.
- **Layout Builder Block**: A Drupal block placed with Layout Builder to assemble inputs, run controls, and results.
- **Node Processor**: The backend component that executes a node; should be idempotent and deterministic.
- **Output Handling**: Normalizing, storing, and delivering images, video, text, or other artifacts from workflows.
- **Queue Processing**: Offloading long‑running or asynchronous execution to Drupal’s Queue API or cron workers.
- **Session Context**: Strongly‑typed key–value data accessible to nodes within an execution session.
- **Validation Rules**: Constraints applied to inputs or mappings to ensure safe, correct execution.
- **Workflow**: A directed graph of steps that transforms inputs into outputs within a defined context.
- **Workflow Node**: A discrete, reusable step with typed inputs, typed outputs, and configuration.
- **Workflow UI**: The editor used by site builders/admins to author and configure workflows.
