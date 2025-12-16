# Port Configuration System

FlowDrop uses a configurable port system that allows administrators to define available data types, their visual appearance, and compatibility rules from the Drupal backend.

## Overview

The port configuration system consists of:

1. **Data Types**: Define the available port data types with colors and metadata
2. **Compatibility Rules**: Define which data types can connect to each other
3. **API Integration**: Serve configuration from Drupal to the frontend
4. **Runtime Application**: Apply configuration in the workflow editor

## Data Type Configuration

Each data type is defined with the following properties:

```typescript
interface PortDataTypeConfig {
  id: string;           // Unique identifier (e.g., "string")
  name: string;         // Display name (e.g., "String")
  description?: string; // Optional description
  color: string;        // CSS color value (e.g., "var(--color-ref-emerald-500)")
  category?: string;    // Grouping category (e.g., "basic", "numeric")
  aliases?: string[];   // Alternative names (e.g., ["text"] for "string")
  enabled?: boolean;    // Whether the type is available (default: true)
}
```

### Example Data Types

```typescript
const dataTypes = [
  // Basic types
  {
    id: "string",
    name: "String",
    description: "Text data",
    color: "var(--color-ref-emerald-500)",
    category: "basic",
    aliases: ["text"],
    enabled: true
  },
  {
    id: "number",
    name: "Number", 
    description: "Numeric data",
    color: "var(--color-ref-blue-600)",
    category: "numeric",
    aliases: ["integer", "float"],
    enabled: true
  },
  
  // Typed arrays - show exactly what they contain!
  {
    id: "string[]",
    name: "String Array",
    description: "Array of strings",
    color: "var(--color-ref-emerald-400)",
    category: "collection",
    aliases: ["text[]"],
    enabled: true
  },
  {
    id: "number[]",
    name: "Number Array",
    description: "Array of numbers", 
    color: "var(--color-ref-blue-400)",
    category: "collection",
    aliases: ["integer[]", "float[]"],
    enabled: true
  },
  {
    id: "object[]",
    name: "Object Array",
    description: "Array of objects",
    color: "var(--color-ref-orange-400)",
    category: "collection", 
    aliases: ["json[]"],
    enabled: true
  }
];
```

### Typed Array Benefits

- **✅ Precise**: `string[]` is clearer than generic `array`
- **✅ Self-Documenting**: Users know exactly what the array contains
- **✅ Better Compatibility**: Smart connections between `string` → `string[]`
- **✅ Visual Clarity**: Different colors for different array types

## Compatibility Rules

**Simple Rule: Same Type Connects to Same Type**

By default, only identical data types can connect to each other. This eliminates confusion and makes the system predictable.

```typescript
interface PortCompatibilityRule {
  from: string;            // Source data type ID (what you're connecting FROM)
  to: string;              // Target data type ID (what you're connecting TO)  
  description?: string;    // Optional description of why this connection is allowed
}
```

### Default Behavior

- `string` can connect to `string`
- `number` can connect to `number`
- `string[]` can connect to `string[]`
- `object` can connect to `object`
- etc.

### Zero Additional Rules

```typescript
const compatibilityRules = [
  // No additional rules needed!
  // The system automatically allows same-type connections
];
```

**Perfect Simplicity**: `string` connects to `string`, `number[]` connects to `number[]`, etc.

### Why This Simple Approach is Better

- **✅ Predictable**: Users know exactly what can connect to what
- **✅ No Confusion**: Same type connects to same type - easy to understand
- **✅ Clear Errors**: When connections fail, the reason is obvious
- **✅ Type Safety**: Prevents accidental incompatible connections
- **✅ Easy to Extend**: Add new types without complex compatibility matrices

### Guidelines for Ultra-Simple Rules

**✅ No Rules Needed:**
```typescript
// The system automatically handles:
// string → string ✅
// number → number ✅  
// json → json ✅
// string[] → string[] ✅
```

**✅ Use Workflow Nodes for Processing:**
```typescript
// Don't create compatibility rules - create nodes instead!
// string → number    =  Use "Parse Number" node
// json → string      =  Use "JSON Stringify" node  
// string → string[]  =  Use "Split" or "Wrap Array" node
// text → embedding   =  Use "Embedding Generator" node
```

**🔄 Data Processing vs Port Compatibility**

- **Port Rules**: Only for identical data in different formats
- **Workflow Nodes**: Handle all data processing and conversion
- **Result**: Simple, predictable connections that always make sense

## Drupal Configuration

### API Endpoint

The port configuration is served at `/api/flowdrop/port-config` and returns:

```json
{
  "version": "1.0.0",
  "defaultDataType": "string",
  "dataTypes": [
    {
      "id": "string",
      "name": "String",
      "color": "var(--color-ref-emerald-500)",
      "category": "basic"
    }
  ],
  "compatibilityRules": [
    {
      "from": "string",
      "to": "number",
      "description": "Parse string as number"
    }
  ]
}
```

### Configuration Storage

Port configuration can be stored in Drupal configuration:

1. **Config Key**: `flowdrop_node_type.port_config`
2. **Default Fallback**: If no configuration exists, the API returns a comprehensive default configuration
3. **Admin Interface**: *(Future Enhancement)* Admin UI to manage port types and rules

## Frontend Integration

### Initialization

The port configuration is loaded during application initialization:

```typescript
// Automatic loading when mounting the workflow editor
const app = await FlowDrop.mountWorkflowEditor(container, {
  workflow: myWorkflow,
  endpointConfig: myEndpoints,
  // portConfig: customConfig (optional override)
});
```

### Runtime Usage

Once initialized, the port system is used throughout the application:

```typescript
// Check compatibility
const checker = getPortCompatibilityChecker();
const isCompatible = checker.areDataTypesCompatible("string", "number");

// Get port colors
const color = getDataTypeColorToken("string");

// Get available types
const availableTypes = getAvailableDataTypes();
```

## Customization Examples

### Adding a Custom Data Type

```typescript
const customPortConfig = {
  version: "1.0.0",
  defaultDataType: "string",
  dataTypes: [
    // ... existing types
    {
      id: "embedding",
      name: "Embedding Vector",
      description: "AI embedding vector data (numerical array)",
      color: "var(--color-ref-purple-500)",
      category: "ai",
      enabled: true
    }
  ],
  compatibilityRules: [
    // ... existing rules
    // Embeddings are numerical vectors, so they can be treated as arrays
    { from: "embedding", to: "number[]", description: "Embedding is a vector of numbers" },
    { from: "embedding", to: "array", description: "Embedding can be represented as array" },
    { from: "number[]", to: "embedding", description: "Number array can be used as embedding" }
  ]
};
```

### Disabling Data Types

```typescript
const restrictedConfig = {
  // ... other config
  dataTypes: [
    {
      id: "file",
      name: "File",
      color: "var(--color-ref-red-500)",
      enabled: false  // Disable file uploads
    }
  ]
};
```

### Custom Color Scheme

```typescript
const darkThemeConfig = {
  // ... other config
  dataTypes: [
    {
      id: "string",
      name: "String",
      color: "#22c55e",  // Custom green
      category: "basic"
    },
    {
      id: "number", 
      name: "Number",
      color: "#3b82f6",  // Custom blue
      category: "numeric"
    }
  ]
};
```

## Migration Guide

To migrate from the old static port system to the new configurable system:

1. **Existing installations**: The system uses the default configuration automatically
2. **Custom port types**: Add them via the API endpoint or Drupal configuration
3. **Custom colors**: Override the color values in the data type configuration
4. **Custom rules**: Define new compatibility rules in the configuration

## Best Practices

1. **Consistent Colors**: Use CSS variables for colors to support theming
2. **Logical Categories**: Group related data types together
3. **Bidirectional Rules**: Use sparingly, prefer explicit directional rules
4. **Aliases**: Use aliases for backward compatibility with existing workflows
5. **Validation**: Always validate configuration before applying
6. **Versioning**: Increment version when making breaking changes

## Troubleshooting

### Common Issues

1. **Colors not appearing**: Check CSS variables are defined
2. **Connections not working**: Verify compatibility rules exist
3. **Types not showing**: Ensure `enabled: true` and valid configuration
4. **API errors**: Check endpoint URL and network connectivity

### Debug Tools

```typescript
// Check current configuration
const checker = getPortCompatibilityChecker();
console.log(checker.getEnabledDataTypes());

// Test compatibility
console.log(checker.areDataTypesCompatible("string", "number"));

// Get compatible types
console.log(checker.getCompatibleTypes("string"));
```

## Future Enhancements

- **Admin UI**: Drupal admin interface for managing port configuration
- **Import/Export**: Configuration import/export functionality  
- **Validation**: Enhanced validation and error reporting
- **Performance**: Caching and optimization for large configurations
- **Versioning**: Support for configuration versioning and migration
