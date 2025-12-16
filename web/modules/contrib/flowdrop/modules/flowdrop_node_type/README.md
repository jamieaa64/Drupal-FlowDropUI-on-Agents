# FlowDrop Node Type Module

This module provides configurable node types for the FlowDrop workflow system, allowing administrators to define and manage different types of nodes that can be used in workflows.

## Overview

The `flowdrop_node_type` module defines a configurable entity type that allows administrators to create, edit, and manage node types with rich metadata including inputs, outputs, configuration schemas, and categories. It integrates with the `flowdrop_node_category` module to provide organized categorization of node types.

## Features

- **Configurable Node Types**: Create and manage node types with custom inputs, outputs, and configuration schemas
- **Category Integration**: Seamless integration with `flowdrop_node_category` module for organized categorization
- **Admin Interface**: Full CRUD operations through Drupal's admin interface
- **JSON Configuration**: Rich JSON-based configuration for inputs, outputs, and schemas
- **Service Integration**: Programmatic access through the `FlowDropNodeTypeManager` service
- **Drush Commands**: Command-line tools for managing node types

## Installation

1. Enable the module:
   ```bash
   ddev drush en flowdrop_node_type
   ```

2. Optionally enable the category module for better organization:
   ```bash
   ddev drush en flowdrop_node_category
   ```

3. Clear the cache:
   ```bash
   ddev drush cr
   ```

## Usage

### Admin Interface

1. Navigate to **Structure > FlowDrop Node Types** (`/admin/structure/flowdrop-node`)
2. Click **Add FlowDrop Node Type** to create a new node type
3. Fill in the required fields:
   - **Label**: Human-readable name for the node type
   - **ID**: Machine-readable identifier (auto-generated from label)
   - **Description**: Brief description of what this node type does
   - **Category**: Select from available categories (from `flowdrop_node_category` module)
   - **Icon**: Icon identifier (e.g., `mdi:cog`, `mdi:text`)
   - **Color**: Hex color code for the node type
   - **Version**: Semantic version (e.g., `1.0.0`)
   - **Enabled**: Whether the node type is active
   - **Executor Plugin ID**: ID of the executor plugin that handles this node type
   - **Inputs JSON**: JSON configuration for input ports
   - **Outputs JSON**: JSON configuration for output ports
   - **Configuration Schema JSON**: JSON schema for node configuration
   - **Tags JSON**: JSON array of tags for search and filtering

### Category Integration

The module automatically integrates with the `flowdrop_node_category` module:

- **Dynamic Category Loading**: Categories are loaded from the category module if available
- **Fallback Support**: If the category module is not available, it falls back to hardcoded categories
- **Service Integration**: The `FlowDropNodeTypeManager` service uses categories from the category module

### Programmatic Usage

```php
// Get all node types
$node_types = \Drupal::service('flowdrop_node_type.manager')
  ->getAllNodeTypes();

// Get node types by category
$processing_nodes = \Drupal::service('flowdrop_node_type.manager')
  ->getNodeTypesByCategory('processing');

// Get a specific node type
$node_type = \Drupal::service('flowdrop_node_type.manager')
  ->getNodeType('text_input');

// Get available categories
$categories = \Drupal::service('flowdrop_node_type.manager')
  ->getAvailableCategories();
```

## Configuration Examples

### Input Node Type Example

```json
{
  "id": "text_input",
  "label": "Text Input",
  "description": "A simple text input node",
  "category": "input",
  "icon": "mdi:text",
  "color": "#4CAF50",
  "version": "1.0.0",
  "enabled": true,
  "executor_plugin": "text_input_executor",
  "inputs": [],
  "outputs": [
    {
      "id": "text",
      "name": "Text",
      "type": "output",
      "dataType": "text",
      "description": "The input text"
    }
  ],
  "config_schema": {
    "placeholder": "Enter text...",
    "defaultValue": "",
    "multiline": false
  },
  "tags": ["input", "text", "user-input"]
}
```

### Processing Node Type Example

```json
{
  "id": "text_processor",
  "label": "Text Processor",
  "description": "Processes and transforms text",
  "category": "processing",
  "icon": "mdi:cog",
  "color": "#2196F3",
  "version": "1.0.0",
  "enabled": true,
  "executor_plugin": "text_processor_executor",
  "inputs": [
    {
      "id": "text",
      "name": "Text",
      "type": "input",
      "dataType": "text",
      "required": true,
      "description": "Input text to process"
    }
  ],
  "outputs": [
    {
      "id": "processed_text",
      "name": "Processed Text",
      "type": "output",
      "dataType": "text",
      "description": "The processed text"
    }
  ],
  "config_schema": {
    "operation": "uppercase",
    "trim": true
  },
  "tags": ["processing", "text", "transform"]
}
```

## Drush Commands

The module provides several Drush commands for managing node types:

```bash
# List all node types
ddev drush flowdrop:node-type:list

# Search node types by tags
ddev drush flowdrop:node-type:search --tags=input,text

# Import node types from mapping
ddev drush flowdrop:node-type:import

# Get node types by category
ddev drush flowdrop:node-type:list --category=processing
```

## API

### Entity Interface

```php
interface FlowDropNodeTypeInterface extends ConfigEntityInterface {
  public function getDescription(): string;
  public function setDescription(string $description): static;
  public function getCategory(): string;
  public function setCategory(string $category): static;
  public function getIcon(): string;
  public function setIcon(string $icon): static;
  public function getColor(): string;
  public function setColor(string $color): static;
  public function getVersion(): string;
  public function setVersion(string $version): static;
  public function isEnabled(): bool;
  public function setEnabled(bool $enabled): static;
  public function getInputs(): array;
  public function setInputs(array $inputs): static;
  public function getOutputs(): array;
  public function setOutputs(array $outputs): static;
  public function getConfigSchema(): array;
  public function setConfigSchema(array $config_schema): static;
  public function getTags(): array;
  public function setTags(array $tags): static;
  public function getExecutorPlugin(): string;
  public function setExecutorPlugin(string $executor_plugin): static;
  public function toNodeDefinition(): array;
}
```

### Service Usage

```php
// Get the node type manager service
$manager = \Drupal::service('flowdrop_node_type.manager');

// Create a node type
$node_type = $manager->createNodeType([
  'id' => 'my_node_type',
  'name' => 'My Node Type',
  'description' => 'A custom node type',
  'category' => 'processing',
  // ... other properties
]);

// Update a node type
$updated = $manager->updateNodeType('my_node_type', [
  'description' => 'Updated description',
  // ... other properties
]);

// Delete a node type
$deleted = $manager->deleteNodeType('my_node_type');
```

## Configuration Schema

The module defines the following configuration schema:

```yaml
flowdrop_node_type.flowdrop_node_type.*:
  type: config_entity
  label: 'FlowDrop Node Type'
  mapping:
    id:
      type: string
      label: 'ID'
    label:
      type: label
      label: 'Label'
    uuid:
      type: string
      label: 'UUID'
    description:
      type: string
      label: 'Description'
    category:
      type: string
      label: 'Category'
    icon:
      type: string
      label: 'Icon'
    color:
      type: string
      label: 'Color'
    version:
      type: string
      label: 'Version'
    enabled:
      type: boolean
      label: 'Enabled'
    inputs:
      type: sequence
      label: 'Inputs'
    outputs:
      type: sequence
      label: 'Outputs'
    config_schema:
      type: mapping
      label: 'Configuration Schema'
    tags:
      type: sequence
      label: 'Tags'
    executor_plugin:
      type: string
      label: 'Executor Plugin ID'
```

## Permissions

The module defines the following permission:

- `administer flowdrop_node`: Full administrative access to node types

## Dependencies

- **Core Dependencies**: Drupal Core
- **Optional Dependencies**: `flowdrop_node_category` (for category integration)

## Integration with Category Module

The module provides seamless integration with the `flowdrop_node_category` module:

1. **Dynamic Category Loading**: Categories are automatically loaded from the category module
2. **Fallback Support**: If the category module is not available, it falls back to hardcoded categories
3. **Service Integration**: The `FlowDropNodeTypeManager` service uses categories from the category module
4. **Admin Interface**: The node type form shows available categories from the category module

### Category Selection

When creating or editing a node type, the category field will:

- Show all available categories from the `flowdrop_node_category` module
- Display category labels as options
- Use category IDs as values
- Only show enabled categories
- Provide a fallback to hardcoded categories if the category module is not available

## Troubleshooting

### Categories Not Showing

1. Ensure the `flowdrop_node_category` module is enabled
2. Check that categories are created and enabled
3. Clear the Drupal cache: `ddev drush cr`

### JSON Validation Errors

1. Ensure JSON fields are properly formatted
2. Check that required fields are present
3. Validate JSON syntax using online tools

### Node Types Not Appearing

1. Check that node types are enabled
2. Verify that the executor plugin exists
3. Clear the Drupal cache: `ddev drush cr`

## 🤝 Contributing

Not accepting Contribution until the module stabilizes. Stay tuned.