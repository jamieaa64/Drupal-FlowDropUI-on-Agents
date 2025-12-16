# FlowDrop ECA Integration

This module provides ECA (Events, Conditions, Actions) integration for FlowDrop workflows, allowing seamless integration between Drupal's ECA module and FlowDrop's workflow automation system.

## Overview

The FlowDrop ECA module extends FlowDrop with three specialized node processors:

1. **ECA Trigger** - Starts workflows based on ECA events
2. **ECA Action** - Executes ECA actions within FlowDrop workflows
3. **ECA Condition** - Evaluates ECA conditions within FlowDrop workflows

## Requirements

- Drupal 10 or 11
- FlowDrop module
- ECA module

## Installation

1. Ensure the FlowDrop and ECA modules are installed and enabled
2. Enable this module: `drush en flowdrop_eca`
3. Clear the cache: `drush cr`

## Usage

### ECA Trigger

The ECA Trigger node allows you to start FlowDrop workflows based on ECA events.

**Configuration Options:**
- `ecaEventTypes`: Array of ECA event types to listen for
- `ecaEntityTypes`: Array of entity types to filter by
- `ecaUserRoles`: Array of user roles to filter by
- `ecaBundles`: Array of content type bundles to filter by
- `ecaWorkflowId`: The ECA workflow ID to integrate with
- `ecaIntegrationMode`: How to integrate with ECA workflows (trigger, action, condition)

**Example Configuration:**
```php
$config = [
  'ecaEventTypes' => ['user.created', 'node.insert'],
  'ecaEntityTypes' => ['user', 'node'],
  'ecaUserRoles' => ['authenticated', 'administrator'],
  'ecaBundles' => ['article', 'page'],
  'ecaWorkflowId' => 'my_eca_workflow',
];
```

### ECA Action

The ECA Action node executes ECA actions within FlowDrop workflows.

**Supported Action Types:**
- `create_entity`: Create new Drupal entities
- `update_entity`: Update existing entities
- `delete_entity`: Delete entities
- `send_email`: Send email notifications
- `redirect_user`: Redirect users
- `set_message`: Set Drupal messages
- `log_action`: Log actions to Drupal's logging system

**Example Configuration:**
```php
$config = [
  'actionType' => 'create_entity',
  'actionConfig' => [
    'entity_type' => 'node',
    'bundle' => 'article',
    'values' => ['title' => 'New Article'],
  ],
];
```

### ECA Condition

The ECA Condition node evaluates ECA conditions within FlowDrop workflows.

**Supported Condition Types:**
- **Entity Conditions**: `entity_has_field`, `entity_field_value`, `entity_is_published`, `entity_is_new`, `entity_has_bundle`, `entity_has_entity_type`
- **User Conditions**: `user_has_role`, `user_is_authenticated`
- **String Conditions**: `string_equals`, `string_contains`
- **Number Conditions**: `number_equals`, `number_greater_than`, `number_less_than`
- **List Conditions**: `list_contains`, `list_is_empty`
- **Data Conditions**: `data_is_empty`, `data_is_not_empty`

**Example Configuration:**
```php
$config = [
  'conditionType' => 'user_has_role',
  'conditionConfig' => [
    'role' => 'administrator',
  ],
];
```

## Integration Benefits

### Seamless Drupal Integration
- **Event Handling**: Respond to any Drupal event through ECA
- **Entity Operations**: Full CRUD operations on Drupal entities
- **User Management**: User-related actions and conditions
- **Content Workflows**: Content creation, modification, and deletion

### Workflow Automation
- **Trigger Workflows**: Start FlowDrop workflows from ECA events
- **Conditional Execution**: Execute workflows based on ECA conditions
- **Action Chaining**: Chain ECA actions within FlowDrop workflows
- **Data Flow**: Seamless data passing between systems

### Developer Experience
- **Familiar Patterns**: Uses familiar ECA patterns
- **Extensible**: Easy to add custom actions and conditions
- **Well-Documented**: Comprehensive configuration schemas
- **Type-Safe**: Strong typing and validation

## Architecture

The module follows Drupal's plugin architecture and integrates with FlowDrop's node processor system:

```
flowdrop_eca/
├── flowdrop_eca.info.yml          # Module definition
├── README.md                      # This file
└── src/
    └── Plugins/
        └── FlowDropNodeProcessor/
            ├── EcaTrigger.php     # ECA Trigger processor
            ├── EcaAction.php      # ECA Action processor
            └── EcaCondition.php   # ECA Condition processor
```

## Extending

### Adding Custom Actions

To add custom ECA actions, extend the `EcaAction` class and implement your action logic:

```php
protected function executeCustomAction(string $action_type, array $config, array $context): array {
  // Your custom action logic here
  return [
    'success' => TRUE,
    'output' => ['custom_result' => 'value'],
    'errors' => [],
  ];
}
```

### Adding Custom Conditions

To add custom ECA conditions, extend the `EcaCondition` class and implement your condition logic:

```php
protected function evaluateCustomCondition(string $condition_type, array $config, array $context): array {
  // Your custom condition logic here
  return [
    'result' => TRUE,
    'output' => ['custom_evaluation' => 'value'],
    'errors' => [],
  ];
}
```

## Troubleshooting

### Common Issues

1. **Module not found**: Ensure both FlowDrop and ECA modules are enabled
2. **Plugin not discovered**: Clear the cache after enabling the module
3. **Configuration errors**: Check that all required configuration fields are provided

### Debugging

Enable debug logging to see detailed information about ECA operations:

```php
// In your settings.php
$config['system.logging']['error_level'] = 'verbose';
```
## 🤝 Contributing

Not accepting Contribution until the module stabilizes. Stay tuned.