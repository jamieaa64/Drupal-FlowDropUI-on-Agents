# Passing Context around


## Configuration-time context (design and save)

### User and permissions
Current user ID, roles, and permissions. Used to gate plugin/tool visibility and allowed operations.

### Site and environment
Site UUID, base URL, environment name (dev/stage/prod), and config split/profile. This context informs defaults and safe integrations.

### Installed capabilities
Enabled modules, library versions, available plugins/tools, and their schemas. This context drives the palette and validation.

### Entity model discovery
Content types, bundles, fields (types/cardinality), taxonomies, media types, and views. This context powers selector options and schema binding.

### Integration availability
Presence of API keys/credentials, connection health checks, and service endpoints. Used to enable or disable connectors.

### Language and locale options
Available languages, default language, and translation status. This affects translatable config inputs.

### Feature flags and toggles
Product or site-level flags that enable/disable experimental UI and behaviors.

### Defaults and presets
Site-wide defaults, module presets, and recommended templates. Used to pre-fill node and tool configuration.

### Validation context
Schema versions, deprecation info, compatibility constraints (e.g., plugin A requires module X ≥ version Y).

### Security posture
Key storage backends (e.g., Key module) and secret scopes. These determine which values must be redacted or referenced indirectly.


## Runtime context (execute and observe)

### Event payload
Trigger-specific typed data (e.g., entity, route params, request body), including before/after snapshots when applicable.

### Current user session
Authenticated user, roles, effective permissions at execution time.

### Primary entity
Entity under operation (type, ID, bundle), related entities, and safe references for lazy loading.

### Workflow instance state
Run variables, memory, loop counters, checkpoints, previous node outputs, error states.

### Request/response
HTTP method, headers, query params, IP, and user agent. Applicable only when execution is request-bound.

### Time and scheduling
Timestamps, timezone, cron/queue context, schedule metadata.

### Language and locale
Active interface/content language, translation negotiation result, timezone offsets.

### External systems
Resolved credentials, token scopes, rate limit status, retry/backoff metadata, endpoint health.

### Concurrency and locking
Instance/run ID, locks, deduplication keys, idempotency tokens, transaction boundaries.

### Caching
Cache contexts, tags, max-age directives applicable to computed outputs.

### Security
Secret material is handled via references or handles. Apply redaction rules for logs and telemetry.

### Telemetry and tracing
Correlation IDs, trace/span IDs, metrics counters, structured logs for observability.

### Queues and jobs
Queue name, job ID, attempts, and delays. Used for async and resume semantics.


## Context transport and shape (guidance)

### Typed data and context definitions
Prefer Drupal TypedData and explicit `ContextDefinition` for plugins to ensure strong typing.

### JSON serialization
Context passed between nodes/tools should be JSON-serializable with clear, versioned schemas.


!!! note
    JSON is widely supported and serializes reliably across systems. With [JSON Schema](https://json-schema.org/), validation can run on both the client and the server.

    Prefer JSON over [PHP's serialize](https://www.php.net/manual/en/function.serialize.php) to avoid interoperability issues when exchanging data with JavaScript or external services.

    YAML can be more comfortable for manual editing. However, JSON typically offers better interoperability and broader tooling support.

### Entity references
Use stable references (entity type + ID/UUID) rather than embedding full entities. Lazy load as needed. This approach enables delegating work across processes and services. Instead of passing entities or objects, pass references and resolve them at execution time. While synchronous, in-request workflows may tolerate embedded data, a reference-based strategy is more scalable and resilient for asynchronous or distributed execution.

### Secrets handling
Pass secret references or keys rather than raw secrets. Resolve secrets at the edge. Redact in logs and UI.

### Precedence rules
Use the following precedence order: 

`Explicit node config > runtime inputs > defaults > site config`

Where,

- **Explicit node config** reflects the author's intent at design time. It must win to keep behavior deterministic and reproducible.
- **Runtime inputs** enable data-driven behavior at execution time. They should not override explicit design choices unless explicitly allowed.
- **Defaults** provide safe module or feature fallbacks. They apply only when neither explicit config nor runtime inputs provide values.
- **Site config** represents environment or site-wide baselines. It sits last to avoid environment-specific drift influencing portable workflows.

??? example

    ##### Explicit node config
    The node defines its own parameters.
    ```json
    {
        "nodeId": "http_request_1",
        "plugin": "http_request",
        "config": {
            "method": "POST",
            "timeoutMs": 7000,
            "retry": { "maxAttempts": 2, "backoffMs": 500 }
        }
    }
    ```

    ##### Runtime inputs
    Values supplied at execution time by the trigger or previous nodes.
    ```json
    {
        "runtime": {
            "requestBody": { "orderId": 12345 },
            "headers": { "x-correlation-id": "c-9a12" },
            "timeoutMs": 9000
        }
    }
    ```

    ##### Defaults
    Module or feature level fallbacks used when nothing else supplies a value.
    ```json
    {
        "defaults": {
            "http_request": {
            "timeoutMs": 8000,
            "retry": { "maxAttempts": 1, "backoffMs": 250 }
            }
        }
    }
    ```

    ##### Site config
    Environment or site-wide baseline values.
    ```json
    {
        "site": {
            "env": "production",
            "http": {
            "serviceBaseUrl": "https://api.example.com",
            "timeoutMs": 10000
            }
        }
    }
    ```

    ##### Resolution example
    For the key `timeoutMs` across the four layers above, the resolved value is `7000` because explicit node config takes precedence over runtime inputs, defaults, and site config.
