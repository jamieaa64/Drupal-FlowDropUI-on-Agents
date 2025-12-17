# Patches for Contrib Modules

This directory tracks patches needed for contrib modules to support FlowDrop UI Agents.

## Current Patches

None yet.

## How to Add Patches

1. Create the patch file and save it here
2. Add to composer.json using cweagans/composer-patches:

```json
{
  "extra": {
    "patches": {
      "drupal/module_name": {
        "Description of patch": "web/modules/custom/flowdrop_ui_agents/patches/patch-file.patch"
      }
    }
  }
}
```

## Potential Patches Needed

### flowdrop_ui
- May need hooks or events for custom node type rendering
- May need API changes for sidebar customization

### modeler_api
- Currently no patches identified

### flowdrop_modeler
- The existing module is ECA-specific and NOT used by this module
- We created a separate Modeler plugin (flowdrop_agents) instead

## Notes

The flowdrop_ui_agents module creates its own Modeler API plugin (`flowdrop_agents`)
rather than patching the existing `flowdrop_modeler` module because:

1. `flowdrop_modeler` is specifically designed for ECA (events, conditions, actions)
2. AI Agents have a completely different data structure (agent, tools, system_prompt)
3. A custom plugin allows full control without affecting ECA functionality
