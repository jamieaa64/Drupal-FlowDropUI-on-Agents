# FlowDrop Development Guide

This document provides comprehensive information for developers working on the FlowDrop project.

## 🏗️ Project Architecture

### Overview

FlowDrop is a Drupal-based workflow editor with the following architecture:

```
flowdrop/
├── src/                          # Core PHP classes
│   ├── Service/NodeRuntime/      # Node execution engine
│   ├── Plugin/FlowDropNodeProcessor/  # 25+ node processors
│   ├── Attribute/                # Plugin discovery attributes
│   └── Exception/                # Custom exception classes
├── modules/                      # Drupal sub-modules
│   ├── flowdrop_workflow/        # Workflow entity management
│   ├── flowdrop_ui/             # Svelte frontend application
│   ├── flowdrop_node_type/      # Node type definitions
│   ├── flowdrop_node_category/  # Node categorization
│   ├── flowdrop_pipeline/       # Pipeline management
│   ├── flowdrop_job/           # Job execution system
│   ├── flowdrop_runner/        # Workflow execution engine
│   └── flowdrop_ai/            # AI integration features
├── js/                         # Drupal JavaScript integration
└── config/                     # Drupal configuration
```

### Core Components

1. **Plugin System**: Uses PHP 8 attributes for automatic discovery of node processors
2. **Node Runtime**: Execution engine that processes workflow nodes
3. **Workflow Engine**: Drupal entity-based workflow management
4. **Frontend**: Svelte-based visual editor with drag-and-drop interface
5. **API Layer**: RESTful API for workflow operations

## 🔧 Development Setup

### Prerequisites

- Drupal 10 or 11
- PHP 8.1+
- Node.js 18+
- Composer
- DDEV (recommended)

### Local Development Environment

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd flowdrop
   ```

2. **Set up DDEV** (if using DDEV):
   ```bash
   ddev start
   ddev composer install
   ```

3. **Build the frontend**:
   ```bash
   cd modules/flowdrop_ui/app/flowdrop
   npm install
   npm run build:iife
   ```

4. **Enable modules**:
   ```bash
   ddev drush en flowdrop flowdrop_workflow flowdrop_ui
   ddev drush cr
   ```

## 📝 Code Structure

### Backend (PHP)

#### Plugin System

The plugin system uses PHP 8 attributes for automatic discovery:

```php
<?php

namespace Drupal\flowdrop\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;

#[FlowDropNodeProcessor(
  id: "my_custom_node",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("My Custom Node")
)]
class MyCustomNode extends AbstractFlowDropNodeProcessor {

  public function execute(array $inputs, array $config): array {
    // Node logic here
    return ['result' => 'processed data'];
  }

  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'mySetting' => ['type' => 'string'],
      ],
    ];
  }
}
```

#### Node Processor Interface

All node processors must implement:

- `execute(array $inputs, array $config): array` - Main node logic
- `getConfigSchema(): array` - Configuration schema
- `getInputSchema(): array` - Input validation schema
- `getOutputSchema(): array` - Output schema
- `validateInputs(array $inputs): bool` - Input validation

#### Service Architecture

- **FlowDropNodeProcessorPluginManager**: Manages node processor discovery and instantiation
- **NodeExecutorInterface**: Defines the contract for node execution
- **AbstractFlowDropNodeProcessor**: Base class with common functionality

### Frontend (Svelte)

The frontend is a Svelte application located in `modules/flowdrop_ui/app/flowdrop/`.

#### Key Components

- **WorkflowEditor**: Main editor component
- **NodeSidebar**: Node palette and categories
- **WorkflowNode**: Individual node components
- **FlowCanvas**: Drag-and-drop canvas

#### Development Commands

```bash
cd modules/flowdrop_ui/app/flowdrop

# Development server
npm run dev

# Build for production
npm run build:iife

# Run tests [IMPLEMENATION PENDING]
npm run test:unit
npm run test:e2e

# Storybook [IMPLEMENATION PENDING]
npm run storybook
```

## 🧪 Testing

### Backend Testing

```bash
# Run all FlowDrop tests [IMPLEMENATION PENDING]
ddev drush test:run flowdrop

# Run specific test class [IMPLEMENATION PENDING]
ddev drush test:run --class="Drupal\Tests\flowdrop\Unit\MyTestClass"
```

### Frontend Testing

```bash
cd modules/flowdrop_ui/app/flowdrop

# Unit tests [IMPLEMENATION PENDING]
npm run test:unit

# E2E tests [IMPLEMENATION PENDING]
npm run test:e2e

# Coverage report [IMPLEMENATION PENDING]
npm run test:coverage
```

### Test Structure

#### Backend Tests

- **Unit Tests**: Test individual classes and methods
- **Integration Tests**: Test module integration
- **Functional Tests**: Test complete workflows

#### Frontend Tests

- **Unit Tests**: Test individual components
- **Integration Tests**: Test component interactions
- **E2E Tests**: Test complete user workflows

## 📦 Adding New Node Types

### 1. Create Node Processor

```php
<?php

namespace Drupal\flowdrop\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[FlowDropNodeProcessor(
  id: "my_custom_node",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("My Custom Node")
)]
class MyCustomNode extends AbstractFlowDropNodeProcessor {

  public function __construct(
    protected LoggerChannelFactoryInterface $loggerFactory
  ) {}

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $container->get('logger.factory')
    );
  }

  public function execute(array $inputs, array $config): array {
    // Your node logic here
    $setting = $this->getConfigValue($config, 'mySetting', 'default');

    // Process inputs
    $inputData = $inputs['data'] ?? '';

    // Your processing logic
    $result = $this->processData($inputData, $setting);

    // Log execution
    $this->loggerFactory->get('flowdrop')->info('My custom node executed', [
      'setting' => $setting,
      'input_size' => strlen($inputData),
    ]);

    return [
      'result' => $result,
      'processed_at' => time(),
    ];
  }

  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'mySetting' => [
          'type' => 'string',
          'title' => 'My Setting',
          'description' => 'Description of the setting',
          'default' => 'default_value',
        ],
      ],
    ];
  }

  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'data' => [
          'type' => 'mixed',
          'title' => 'Input Data',
          'description' => 'Input data for processing',
          'required' => false,
        ],
      ],
    ];
  }

  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'result' => [
          'type' => 'string',
          'description' => 'Processed result',
        ],
        'processed_at' => [
          'type' => 'integer',
          'description' => 'Processing timestamp',
        ],
      ],
    ];
  }

  public function validateInputs(array $inputs): bool {
    // Validate inputs if needed
    return true;
  }

  private function processData(string $data, string $setting): string {
    // Your processing logic here
    return "Processed: $data with setting: $setting";
  }
}
```

### 2. Add Frontend Support

If your node requires custom frontend components, add them to the Svelte application:

```svelte
<!-- modules/flowdrop_ui/app/flowdrop/src/lib/components/nodes/MyCustomNode.svelte -->
<script lang="ts">
  import type { NodeProps } from 'flowdrop';

  export let data: NodeProps;

  let mySetting = data.config?.mySetting || 'default';

  function updateConfig() {
    data.onConfigChange({
      ...data.config,
      mySetting
    });
  }
</script>

<div class="my-custom-node">
  <label>
    My Setting:
    <input
      type="text"
      bind:value={mySetting}
      on:input={updateConfig}
    />
  </label>
</div>
```

### 3. Register Node Type

Add your node to the node type registry:

```php
// In your module's install file or service
$nodeType = NodeType::create([
  'id' => 'my_custom_node',
  'label' => 'My Custom Node',
  'category' => 'utility',
  'description' => 'Description of my custom node',
]);
$nodeType->save();
```

## 🔍 Debugging

### Backend Debugging

1. **Enable Debug Logging**:
   ```php
   \Drupal::logger('flowdrop')->debug('Debug message', ['context' => $data]);
   ```

2. **Use Drupal's Debug Bar**:
   ```bash
   ddev drush en devel
   ```

3. **Check Plugin Discovery**:
   ```bash
   ddev drush cr
   ddev drush ev "print_r(\Drupal::service('flowdrop.node_processor_plugin_manager')->getDefinitions());"
   ```


## 🤝 Contributing

Not accepting Contribution until the module stabilizes. Stay tuned.
