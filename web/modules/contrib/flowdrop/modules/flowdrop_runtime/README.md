# FlowDrop Runtime Module

The FlowDrop Runtime module provides a comprehensive execution engine for FlowDrop workflows with support for both synchronous and asynchronous execution modes, real-time monitoring, and advanced orchestration capabilities.

## Features

### Execution Modes

1. **Synchronous Orchestrator** (`synchronous`)
   - Direct execution with immediate results
   - Real-time status updates
   - Interactive workflow execution

2. **Asynchronous Orchestrator** (`asynchronous`) 🆕
   - Queue-based background execution
   - Scalable pipeline and job processing
   - Persistent execution state
   - Automatic retry and error handling

### Core Components

- **Node Runtime Service** - Executes individual workflow nodes
- **Execution Context** - Manages execution state and data flow
- **Real-Time Manager** - Provides live status updates and event broadcasting
- **Workflow Compiler** - Optimizes and validates workflow definitions
- **Data Flow Manager** - Handles data transformation between nodes
- **Error Handler** - Comprehensive error management and recovery

### Queue-Based Execution 🆕

The module now includes queue-based execution capabilities migrated from `flowdrop_runner`:

- **Pipeline Execution Queue** (`flowdrop_runtime_pipeline_execution`)
- **Job Execution Queue** (`flowdrop_runtime_job_execution`)
- **Automatic job dependency resolution**
- **Concurrent execution control**
- **Retry mechanisms with exponential backoff**

## Installation

```bash
drush en flowdrop_runtime
```

## Basic Usage

### Synchronous Execution

```php
use Drupal\flowdrop_runtime\DTO\Orchestrator\OrchestrationRequest;
use Drupal\flowdrop\DTO\Input;
use Drupal\flowdrop\DTO\Config;

$orchestrator = \Drupal::service('flowdrop_runtime.synchronous_orchestrator');

$request = new OrchestrationRequest(
  $execution_id,
  $workflow,
  new Input($input_data),
  new Config($config_data)
);

$response = $orchestrator->orchestrate($request);
```

### Asynchronous Execution

```php
$orchestrator = \Drupal::service('flowdrop_runtime.asynchronous_orchestrator');

$request = new OrchestrationRequest(
  $execution_id,
  $workflow,
  new Input($input_data),
  new Config(['max_concurrent_jobs' => 5])
);

$response = $orchestrator->orchestrate($request);
// Returns immediately with pipeline ID for tracking
```

### Pipeline Management

```php
// Create pipeline from workflow
$pipeline = $orchestrator->createPipelineFromWorkflow($workflow, $inputs, $config);

// Start pipeline execution
$orchestrator->startPipeline($pipeline);

// Monitor pipeline status
$status = $pipeline->getStatus();
$job_counts = $pipeline->getJobCounts();
```

## Configuration

### Queue Configuration

The module automatically configures two queues for background processing:

```yaml
# flowdrop_runtime.queue.yml
flowdrop_runtime_pipeline_execution:
  title: "FlowDrop Runtime Pipeline Execution"
  time: 300
  memory: 128

flowdrop_runtime_job_execution:
  title: "FlowDrop Runtime Job Execution"
  time: 300
  memory: 128
```

### Service Configuration

Key services include:

- `flowdrop_runtime.synchronous_orchestrator` - Direct execution
- `flowdrop_runtime.asynchronous_orchestrator` - Queue-based execution
- `flowdrop_runtime.node_runtime` - Node execution service
- `flowdrop_runtime.real_time_manager` - Status monitoring

## Events

The module dispatches various events during execution:

### Pipeline Events
- `flowdrop.pipeline.created` - Pipeline created
- `flowdrop.pipeline.started` - Pipeline execution started
- `flowdrop.pipeline.completed` - Pipeline completed successfully
- `flowdrop.pipeline.failed` - Pipeline execution failed
- `flowdrop.pipeline.paused` - Pipeline paused
- `flowdrop.pipeline.cancelled` - Pipeline cancelled

### Job Events
- `flowdrop.job.created` - Job created
- `flowdrop.job.started` - Job execution started
- `flowdrop.job.completed` - Job completed successfully
- `flowdrop.job.failed` - Job execution failed

## Real-Time Monitoring

### Status Tracking

```php
$status_tracker = \Drupal::service('flowdrop_runtime.status_tracker');

// Get execution status
$status = $status_tracker->getExecutionStatus($execution_id);

// Get node status
$node_status = $status_tracker->getNodeStatus($execution_id, $node_id);
```

### Event Broadcasting

```php
$event_broadcaster = \Drupal::service('flowdrop_runtime.event_broadcaster');

// Listen for real-time updates
$event_broadcaster->subscribe($execution_id, function($event) {
  // Handle real-time status updates
});
```

## Error Handling

### Exception Types

- `OrchestrationException` - Orchestration failures
- `RuntimeException` - Runtime execution errors
- `CompilationException` - Workflow compilation errors
- `DataFlowException` - Data flow validation errors

### Retry Strategies

- `individual` - Retry failed jobs individually
- `stop_on_failure` - Stop pipeline on first job failure

### Example Error Handling

```php
try {
  $response = $orchestrator->orchestrate($request);
} catch (OrchestrationException $e) {
  // Handle orchestration failure
  \Drupal::logger('flowdrop_runtime')->error('Orchestration failed: @message', [
    '@message' => $e->getMessage(),
  ]);
}
```

## Performance Considerations

### Concurrent Execution

Control the number of concurrent jobs per pipeline:

```php
$config = new Config([
  'max_concurrent_jobs' => 10,
  'job_priority_strategy' => 'dependency_order',
]);
```

### Memory Management

Monitor and limit memory usage:

```php
$config = new Config([
  'memory_limit' => 256, // MB
  'timeout' => 600,      // seconds
]);
```

### Queue Processing

Process queues efficiently:

```bash
# Process pipeline execution queue
drush queue:run flowdrop_runtime_pipeline_execution

# Process job execution queue
drush queue:run flowdrop_runtime_job_execution
```

## Migration from flowdrop_runner

If you're migrating from the deprecated `flowdrop_runner` module:

1. Replace service dependencies:
   - `flowdrop_runner.pipeline_executor` → `flowdrop_runtime.asynchronous_orchestrator`
   - `flowdrop_runner.job_executor` → `flowdrop_runtime.node_runtime`

2. Update queue names:
   - `flowdrop_pipeline_execution` → `flowdrop_runtime_pipeline_execution`
   - `flowdrop_job_execution` → `flowdrop_runtime_job_execution`

3. Use DTOs instead of arrays for type safety

See `modules/flowdrop_runner/DEPRECATED.md` for detailed migration instructions.

## Testing

Run integration tests:

```bash
# PHPUnit tests
vendor/bin/phpunit modules/flowdrop_runtime/tests/

# Drupal test runner
drush phpunit modules/flowdrop_runtime/tests/Integration/AsynchronousExecutionTest.php
```

## API Reference

### DTOs

- `OrchestrationRequest` - Request object for orchestration
- `OrchestrationResponse` - Response object with execution results
- `NodeExecutionContext` - Context for node execution
- `NodeExecutionResult` - Result object from node execution
- `ExecutionStatus` - Real-time execution status
- `RealTimeEvent` - Real-time event object

### Interfaces

- `OrchestratorInterface` - Base interface for orchestrators
- `FlowDropPipelineInterface` - Pipeline entity interface
- `FlowDropJobInterface` - Job entity interface

## 🤝 Contributing

Not accepting Contribution until the module stabilizes. Stay tuned.

## Dependencies

- `flowdrop` - Core FlowDrop functionality
- `flowdrop_workflow` - Workflow definitions
- `flowdrop_pipeline` - Pipeline entities
- `flowdrop_job` - Job entities
- `flowdrop_node_type` - Node type definitions
