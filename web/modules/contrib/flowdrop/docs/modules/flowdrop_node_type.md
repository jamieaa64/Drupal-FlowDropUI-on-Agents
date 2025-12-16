# FlowDrop Node Type Module

The `flowdrop_node_type` module is a core component of the FlowDrop system that provides configurable node type definitions for workflow nodes. It serves as the foundation for creating, managing, and organizing different types of nodes that can be used in FlowDrop workflows.

## Goals

| Goal ID | Description |
|---------|-------------|
| FNT_G1 | Provide configurable node type definitions for workflow nodes |
| FNT_G2 | Enable site builders to create custom node types without coding |
| FNT_G3 | Provide categorization and organization of node types |

## Overview

The FlowDrop Node Type module provides:

- **Node Type Entity Management**: Config entity for storing node type definitions
- **Dynamic Configuration**: Schema-driven configuration forms for node types
- **Category Organization**: Categorization system for organizing node types
- **Plugin Integration**: Integration with node processor plugins

## Dependencies

The module depends on:

- `flowdrop` (main module)
- `flowdrop_node_category` (for node categorization)

## Entity Structure

### FlowDrop Node Type Entity

The node type entity is a configuration entity that stores node type definitions with the following properties:

#### **Core Properties**

- **`id`**: Unique identifier for the node type
- **`label`**: Human-readable name for the node type
- **`description`**: Detailed description of the node type functionality
- **`enabled`**: Whether the node type is enabled/disabled

#### **Visual Properties**

- **`category`**: Category for organizing node types (e.g., "processing", "input", "output")
- **`icon`**: Icon identifier for visual representation (e.g., "mdi:cog", "mdi:database")
- **`color`**: Color code for visual styling (e.g., "#007cba", "#ff6b6b")

#### **Configuration Properties**

- **`version`**: Version string for the node type
- **`config`**: Configuration schema and default values
- **`tags`**: Array of tags for search and filtering
- **`executor_plugin`**: Plugin ID that handles node execution

## Admin Interface

### **Node Type Management Pages**

The module provides standard Drupal admin pages:

- **List**: `/admin/structure/flowdrop-node-type` - View all node types
- **Add**: `/admin/structure/flowdrop-node-type/add` - Create new node type
- **Edit**: `/admin/structure/flowdrop-node-type/{id}` - Edit node type
- **Delete**: `/admin/structure/flowdrop-node-type/{id}/delete` - Delete node type

### **Dynamic Configuration Forms**

The module provides dynamic configuration forms based on plugin schemas:

- **Schema-driven forms**: Automatically generated based on plugin configuration schemas
- **Validation**: Real-time validation of configuration values
- **AJAX updates**: Dynamic form updates based on configuration changes


## Services

### **FlowDropNodeTypeManager**

The main service for managing node types:
