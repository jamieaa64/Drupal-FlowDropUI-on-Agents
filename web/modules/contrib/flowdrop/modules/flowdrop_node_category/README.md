# FlowDrop Node Category Module

This module provides configurable categories for FlowDrop node types, allowing administrators to organize and categorize different types of nodes in the workflow system.

## Overview

The `flowdrop_node_category` module defines a configurable entity type that allows administrators to create, edit, and manage categories for node types. These categories are used by the `flowdrop_node_type` module to organize node types in the admin interface and provide better user experience.

## Features

- **Configurable Categories**: Create and manage node type categories with custom labels, descriptions, icons, and colors
- **Admin Interface**: Full CRUD operations through Drupal's admin interface
- **Integration**: Seamless integration with `flowdrop_node_type` module
- **Fallback Support**: Graceful fallback when category module is not available

## Installation

1. Enable the module:
   ```bash
   ddev drush en flowdrop_node_category
   ```

2. Clear the cache:
   ```bash
   ddev drush cr
   ```

## Usage

### Admin Interface

1. Navigate to **Structure > FlowDrop Node Categories** (`/admin/structure/flowdrop-node-category`)
2. Click **Add FlowDrop Node Category** to create a new category
3. Fill in the required fields:
   - **Label**: Human-readable name for the category
   - **ID**: Machine-readable identifier (auto-generated from label)
   - **Description**: Brief description of what this category contains
   - **Icon**: Icon identifier (e.g., `mdi:cog`, `mdi:input`)
   - **Color**: Hex color code for the category
   - **Enabled**: Whether the category is active

### Programmatic Usage

```php
// Load a category
$category = \Drupal::entityTypeManager()
  ->getStorage('flowdrop_node_category')
  ->load('inputs');

// Get category properties
$label = $category->label();
$description = $category->getDescription();
$icon = $category->getIcon();
$color = $category->getColor();

// Check if category is enabled
$enabled = $category->status();
```

## Integration with flowdrop_node_type

The `flowdrop_node_type` module automatically integrates with this module:

1. **Dynamic Category Loading**: Node type forms automatically load available categories from this module
2. **Fallback Support**: If this module is not available, node types fall back to hardcoded categories
3. **Service Integration**: The `FlowDropNodeTypeManager` service uses categories from this module

### Category Selection in Node Types

When creating or editing a node type, the category field will show all available categories from this module. The selection includes:

- Category ID as the value
- Category label as the display text
- Only enabled categories are shown

## Default Categories

The module includes several default categories:

- **Input** (`input`): Node types that provide input data
- **Output** (`output`): Node types that produce output data
- **Processing** (`processing`): Node types that process and transform data
- **Model** (`model`): Node types for AI and machine learning models
- **Logic** (`logic`): Node types for conditional logic and control flow
- **Prompt** (`prompt`): Node types for prompt engineering and template management
- **Data** (`data`): Node types for data storage and manipulation
- **Tool** (`tool`): Node types for utility tools and helper functions
- **Embedding** (`embedding`): Node types for text embedding and vector operations
- **Memory** (`memory`): Node types for conversation memory and context management
- **Agent** (`agent`): Node types for AI agents and autonomous systems
- **Vector Store** (`vector_store`): Node types for vector database operations and similarity search

## API

### Entity Interface

```php
interface FlowDropNodeCategoryInterface extends ConfigEntityInterface {
  public function getDescription(): string;
  public function setDescription(string $description): static;
  public function getIcon(): string;
  public function setIcon(string $icon): static;
  public function getColor(): string;
  public function setColor(string $color): static;
}
```

### Service Usage

```php
// Get all available categories
$categories = \Drupal::service('flowdrop_node_type.manager')
  ->getAvailableCategories();

// Categories are returned as [id => label] array
foreach ($categories as $id => $label) {
  // Use category ID and label
}
```

## Configuration Schema

The module defines the following configuration schema:

```yaml
flowdrop_node_category.flowdrop_node_category.*:
  type: config_entity
  label: FlowDrop Node Category
  mapping:
    id:
      type: string
      label: ID
    label:
      type: label
      label: Label
    uuid:
      type: string
    description:
      type: string
    icon:
      type: string
      label: 'Icon'
    color:
      type: string
      label: 'Color'
```

## Permissions

The module defines the following permission:

- `administer flowdrop_node_category`: Full administrative access to node categories

## Dependencies

- **Core Dependencies**: Drupal Core
- **Optional Dependencies**: `flowdrop_node_type` (for integration)

## Development

### Adding New Categories

To add new categories programmatically:

```php
$category = \Drupal::entityTypeManager()
  ->getStorage('flowdrop_node_category')
  ->create([
    'id' => 'my_category',
    'label' => 'My Category',
    'description' => 'Description of my category',
    'icon' => 'mdi:custom-icon',
    'color' => '#FF0000',
  ]);
$category->save();
```

### Customizing Category Display

To customize how categories are displayed in the node type form:

1. Override the `FlowDropNodeTypeForm::form()` method
2. Modify the category field options
3. Add custom validation or processing

## Troubleshooting

### Categories Not Showing in Node Type Form

1. Ensure the `flowdrop_node_category` module is enabled
2. Check that categories are created and enabled
3. Clear the Drupal cache: `ddev drush cr`

### Fallback to Hardcoded Categories

If the category module is not available, the node type module will automatically fall back to hardcoded categories. This ensures backward compatibility.

## 🤝 Contributing

Not accepting Contribution until the module stabilizes. Stay tuned.