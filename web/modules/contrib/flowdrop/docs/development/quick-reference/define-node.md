# Defining Node

## Define a Plugin

Create a `FlowDropNodeProcessor` plugin in your module.

```php
<?php

namespace Drupal\my_module\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;

#[FlowDropNodeProcessor(
  id: "your_plugin_id",
  label: new TranslatableMarkup("Your Plugin Label"),
  type: "default",
  category: "your_category",
  description: "Brief description of what this plugin does",
  version: "1.0.0",
  tags: ["tag1", "tag2", "tag3"]
)]
class YourPlugin extends AbstractFlowDropNodeProcessor {
  // Implementation...
}
```

## Required Parameters

| Parameter | Example | Description |
|-----------|---------|-------------|
| `id` | `"custom_processor"` | Unique identifier (lowercase, underscores) |
| `label` | `new TranslatableMarkup("Custom Processor")` | Human-readable name |
| `type` | `"default"` | Node type for frontend (usually "default") |
| `category` | `"processing"` | Component category |


??? example

    ### Input Component
    ```php
    #[FlowDropNodeProcessor(
      id: "custom_input",
      label: new TranslatableMarkup("Custom Input"),
      type: "default",
      category: "inputs",
      description: "Custom data input component",
      tags: ["input", "custom", "data"]
    )]
    ```

    ### AI Model Component
    ```php
    #[FlowDropNodeProcessor(
      id: "custom_ai_model",
      label: new TranslatableMarkup("Custom AI Model"),
      type: "default",
      category: "models",
      description: "Custom AI model integration",
      version: "1.2.0",
      tags: ["ai", "model", "custom", "integration"]
    )]
    ```

    ### Processing Component
    ```php
    #[FlowDropNodeProcessor(
      id: "data_transformer",
      label: new TranslatableMarkup("Data Transformer"),
      type: "default",
      category: "processing",
      description: "Transforms data between formats",
      tags: ["data", "transform", "processing"]
    )]
    ```

    ### Tool Component
    ```php
    #[FlowDropNodeProcessor(
      id: "custom_api",
      label: new TranslatableMarkup("Custom API"),
      type: "default",
      category: "tools",
      description: "Custom API integration",
      tags: ["api", "http", "integration", "custom"]
    )]
    ```

## Creating Plugin Instance

Use the `flowdrop.node_processor_plugin_manager` service to create plugin instances:

```php
<?php
// Get service and create instance
$plugin_manager = \Drupal::service('plugin.manager.flowdrop_node_processor');
$plugin = $plugin_manager->createInstance('text_input', ['defaultValue' => 'Hello']);

// Available methods
$definitions = $plugin_manager->getDefinitions();           // Get all plugins
$definition = $plugin_manager->getDefinition('plugin_id');  // Get specific plugin
$exists = $plugin_manager->hasDefinition('plugin_id');      // Check if exists
```

??? example

    ```php
    // In a service or controller
    $plugin = $this->pluginManager->createInstance('chat_model', [
      'model' => 'gpt-4',
      'temperature' => 0.7,
    ]);
    $output = $plugin->execute($inputs, $config);
    ```

!!! note
    For more detailed usage you can check out [Flowdrop Node Processor](../flowdrop-node-processor.md).
