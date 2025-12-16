# FlowDrop Workflow Module

The `flowdrop_workflow` module is a core component of the FlowDrop system that provides workflow entity management and workflow definition capabilities. It serves as the foundation for creating, storing, and managing workflow definitions that can be executed by the FlowDrop pipeline system.

## Goals

| Goal ID | Description |
|---------|-------------|
| FW_G1 | Provide implementation agnostic storage for saving workflows |
| FW_G2 | Provide Sitebuilder friendly interface for managing workflows |

## Overview

The FlowDrop Workflow module provides:

- **Workflow Entity Management**: Config entity for storing workflow definitions
- **Editor Integration**: Integration with the FlowDrop visual editor


## Dependencies

The module depends on:

- `flowdrop` (main module)
- `flowdrop_node_type` (for node type definitions)
- `flowdrop_node_category` (for node categorization)
- `flowdrop_ui` (for user interface components)

## Entity Structure

### FlowDrop Workflow Entity

The FlowDrop Workflow (`flowdrop_workflow`) entity is a configuration entity that stores workflow definitions with the following properties:

#### **Core Properties**

- **`id`**: Unique identifier for the workflow
- **`label`**: Human-readable name for the workflow
- **`description`**: Detailed description of the workflow
- **`status`**: Whether the workflow is enabled/disabled

#### **Workflow Data**

- **`nodes`**: Array of workflow nodes with their configurations
- **`edges`**: Array of connections between nodes
- **`metadata`**: Additional workflow metadata and settings

#### **Audit Information**

- **`created`**: Timestamp when the workflow was created
- **`changed`**: Timestamp when the workflow was last modified
- **`uid`**: User ID who created the workflow

### Entity Interface

The `FlowDropWorkflowInterface` provides the following methods:

```php
// Core entity methods
public function getLabel(): string;
public function setLabel(string $label): static;
public function getDescription(): string;
public function setDescription(string $description): static;

// Workflow data methods
public function getNodes(): array;
public function setNodes(array $nodes): static;
public function getEdges(): array;
public function setEdges(array $edges): static;
public function getMetadata(): array;
public function setMetadata(array $metadata): static;

// Audit methods
public function getCreated(): int;
public function setCreated(int $created): static;
public function getChanged(): int;
public function setChanged(int $changed): static;
public function getUid(): int;
public function setUid(int $uid): static;
```

## Admin Interface

### **Workflow Management Pages**

The module provides standard Drupal admin pages:

- **List**: `/admin/structure/flowdrop-workflow` - View all workflows
- **Add**: `/admin/structure/flowdrop_workflow/add` - Create new workflow
- **Edit**: `/admin/structure/flowdrop-workflow/{id}` - Edit workflow
- **Delete**: `/admin/structure/flowdrop-workflow/{id}/delete` - Delete workflow

### **Workflow Editor Integration**

#### **Editor Page**: `/admin/structure/flowdrop-workflow/{id}/flowdrop-editor`

Opens the workflow in the FlowDrop visual editor.
