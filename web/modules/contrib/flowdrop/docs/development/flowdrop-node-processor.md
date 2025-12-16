# FlowDropNodeProcessor Attribute Plugin

## Overview

The `FlowDropNodeProcessor` attribute is a PHP 8+ attribute that provides metadata for FlowDrop node processor plugins. It serves as the primary mechanism for defining node processors in the FlowDrop workflow system, providing LangflowComponent-equivalent metadata for visual workflow nodes.

## Class Definition

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FlowDropNodeProcessor extends AttributeBase
```

**Location**: `src/Attribute/FlowDropNodeProcessor.php`

## Constructor Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$id` | `string` | ✅ | Unique plugin identifier |
| `$label` | `TranslatableMarkup` | ✅ | Human-readable label for the UI |
| `$type` | `string` | ✅ | Node type for frontend rendering |
| `$category` | `string` | ✅ | Component category (e.g., "inputs", "models", "tools") |
| `$description` | `string` | ❌ | Component description (default: "") |
| `$version` | `string` | ❌ | Component version (default: "1.0.0") |
| `$inputs` | `array` | ❌ | Input ports configuration (default: []) |
| `$outputs` | `array` | ❌ | Output ports configuration (default: []) |
| `$config` | `array` | ❌ | Component configuration schema (default: []) |
| `$tags` | `array` | ❌ | Component tags for categorization (default: []) |

## Usage Examples

### Basic Node Processor

```php
<?php

namespace Drupal\my_module\Plugin\FlowDropNodeProcessor;

use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[FlowDropNodeProcessor(
  id: "my_processor",
  label: new TranslatableMarkup("My Processor"),
  type: "default",
  category: "processing",
  description: "A custom node processor for data transformation",
  version: "1.0.0",
  tags: ["custom", "data", "transformation"]
)]
class MyProcessor extends AbstractFlowDropNodeProcessor {
  // Implementation...
}
```

### AI Model Node Processor

```php
#[FlowDropNodeProcessor(
  id: "chat_model",
  label: new TranslatableMarkup("Chat Model"),
  type: "default",
  category: "ai",
  description: "AI chat model integration for conversational AI",
  version: "1.0.0",
  tags: ["ai", "chat", "model", "conversation"]
)]
class ChatModel extends AbstractFlowDropNodeProcessor {
  // Implementation...
}
```

### Input Node Processor

```php
#[FlowDropNodeProcessor(
  id: "text_input",
  label: new TranslatableMarkup("Text Input"),
  type: "default",
  category: "inputs",
  description: "Simple text input field for user data entry",
  version: "1.0.0",
  tags: ["input", "text", "user", "form"]
)]
class TextInput extends AbstractFlowDropNodeProcessor {
  // Implementation...
}
```

## Category Guidelines

Use these standardized categories for consistent organization:

### Core Categories
- **`inputs`** - Data input components (TextInput, FileUpload, etc.)
- **`outputs`** - Data output components (TextOutput, ChatOutput, etc.)
- **`models`** - AI model components (ChatModel, OpenAiChat, etc.)
- **`tools`** - Utility and tool components (HttpRequest, Webhook, etc.)
- **`processing`** - Data processing components (DataOperations, Calculator, etc.)
- **`logic`** - Logic and control flow components (Conditional, Loop, etc.)
- **`ai`** - AI-specific components (embeddings, vector stores, etc.)
- **`eca`** - ECA (Event-Condition-Action) components
- **`helpers`** - Helper and utility components
- **`memories`** - Memory and state management components
- **`prompts`** - Prompt-related components
- **`vector_store`** - Vector database components

### Custom Categories
You can create custom categories for specialized components:
- **`custom`** - Custom business logic components
- **`integration`** - Third-party service integrations
- **`analytics`** - Data analytics components

## Tag Guidelines

Tags help with categorization and search functionality. Use descriptive, lowercase tags:

### Common Tag Patterns
- **Functionality**: `ai`, `chat`, `data`, `file`, `http`, `webhook`
- **Data Types**: `text`, `json`, `csv`, `image`, `audio`
- **Operations**: `input`, `output`, `transform`, `filter`, `aggregate`
- **Providers**: `openai`, `huggingface`, `anthropic`, `google`
- **Use Cases**: `conversation`, `automation`, `analytics`, `reporting`

## Plugin Discovery

The attribute is discovered by the `FlowDropNodeProcessorPluginManager` using attribute-based discovery:

```php
$this->discovery = new AttributeClassDiscovery(
  'Plugin/FlowDropNodeProcessor',
  $namespaces,
  FlowDropNodeProcessor::class
);
```

### Discovery Process
1. **Scan Directories**: Searches `Plugin/FlowDropNodeProcessor` directories in all modules
2. **Attribute Detection**: Identifies classes with `#[FlowDropNodeProcessor]` attribute
3. **Metadata Extraction**: Extracts all attribute parameters as plugin metadata
4. **Caching**: Caches discovered plugins for performance

## Integration with Workflow Editor

The attribute metadata is used by the FlowDrop workflow editor to:

### Frontend Rendering
- **Component Library**: Displays available components organized by category
- **Node Configuration**: Provides configuration forms based on `$config` schema
- **Input/Output Ports**: Renders connection points based on `$inputs` and `$outputs`
- **Search & Filtering**: Enables component discovery using `$tags`

### API Integration
- **Node Metadata**: Exposes component information via REST API
- **Configuration Validation**: Validates node configurations against schemas
- **Execution Context**: Provides metadata for workflow execution

## Best Practices

### 1. Unique Plugin IDs
```php
// ✅ Good - Descriptive and unique
id: "custom_data_transformer"

// ❌ Bad - Generic and may conflict
id: "processor"
```

### 2. Descriptive Labels
```php
// ✅ Good - Clear and descriptive
label: new TranslatableMarkup("Customer Data Transformer")

// ❌ Bad - Too generic
label: new TranslatableMarkup("Processor")
```

### 3. Appropriate Categories
```php
// ✅ Good - Specific category
category: "processing"

// ❌ Bad - Generic category
category: "other"
```

### 4. Meaningful Tags
```php
// ✅ Good - Descriptive tags
tags: ["data", "transformation", "customer", "business-logic"]

// ❌ Bad - Too generic
tags: ["custom"]
```

### 5. Version Management
```php
// ✅ Good - Semantic versioning
version: "1.2.0"

// ❌ Bad - No version tracking
version: "1.0.0" // Always default
```

## Error Handling

### Common Issues

1. **Missing Required Parameters**
```php
// ❌ Error - Missing required parameters
#[FlowDropNodeProcessor(
  id: "my_processor",
  label: new TranslatableMarkup("My Processor")
  // Missing type and category
)]
```

2. **Invalid Category**
```php
// ❌ Error - Invalid category
category: "invalid_category"
```

3. **Duplicate Plugin IDs**
```php
// ❌ Error - Duplicate ID
id: "text_input" // Already exists in core
```

### Validation
The plugin manager validates:
- Required parameters are present
- Plugin IDs are unique
- Categories are valid
- Attribute syntax is correct

## Migration from Annotations

If migrating from Drupal annotations, replace:

```php
// Old annotation style
/**
 * @FlowDropNodeProcessor(
 *   id = "my_processor",
 *   label = @Translation("My Processor"),
 *   type = "default",
 *   category = "processing"
 * )
 */

// New attribute style
#[FlowDropNodeProcessor(
  id: "my_processor",
  label: new TranslatableMarkup("My Processor"),
  type: "default",
  category: "processing"
)]
```

## Performance Considerations

### Caching
- Plugin discovery results are cached
- Cache is invalidated when modules are enabled/disabled
- Cache key: `flowdrop_node_processor_plugins`

### Memory Usage
- All plugin metadata is loaded into memory
- Consider lazy loading for large plugin sets
- Use appropriate cache backends for production

## Testing

### Unit Testing
```php
use Drupal\Tests\UnitTestCase;

class FlowDropNodeProcessorTest extends UnitTestCase {

  public function testPluginDiscovery() {
    $plugin_manager = $this->createMock(FlowDropNodeProcessorPluginManager::class);
    $plugins = $plugin_manager->getDefinitions();

    $this->assertArrayHasKey('my_processor', $plugins);
    $this->assertEquals('processing', $plugins['my_processor']['category']);
  }
}
```

### Integration Testing
```php
use Drupal\Tests\BrowserTestBase;

class FlowDropNodeProcessorIntegrationTest extends BrowserTestBase {

  public function testPluginInWorkflowEditor() {
    $this->drupalGet('/admin/structure/flowdrop-workflow/foo/flowdrop-editor');
    $this->assertSession()->elementExists('css', '[data-component-id="my_processor"]');
  }
}
```
