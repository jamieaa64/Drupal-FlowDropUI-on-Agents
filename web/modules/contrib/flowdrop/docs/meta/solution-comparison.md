# Solution Comparison

## Overview

This section summarizes each project's positioning from Site Builder and Developer perspectives.

!!! warning "Disclaimer"
    This document has been created by the maintaier of FlowDrop module. Expect some biased viewpoints.

### User Experience

| Module              | Setup Requirements                 | Site Builder Experience       | UI Type                     | Primary Focus                                      |
|---------------------|------------------------------------|-------------------------------|-----------------------------|----------------------------------------------------|
| FlowDrop            | Drupal                              | LangFlow‑style graph editor   | Decoupled application       | End‑to‑end workflow authoring and site‑builder UX  |
| BPMN.io             | Drupal + Modeler API                | BPMN.io editor (bpmn‑js)      | BPMN.io widget with AJAX forms | BPMN diagram authoring and viewing                 |
| ComfyUI Integration | Drupal + external ComfyUI server    | Drag‑and‑drop blocks          | Drupal Layout Builder UI    | Dynamic field mapping to ComfyUI parameters        |
| React Flow          | Drupal + Modeler API                | LangFlow‑style graph editor   | Decoupled application       | Front‑end graph UI for custom back‑end execution   |
| Mini Kanban          | Drupal                              | Drag‑and‑drop Kanban UI       | Integrated Drupal UI        | Editorial/task workflow visualization              |

### Developer Experience

| Module              | UI Technology           | Execution Model                                                                 | Persistence                                                     |
|---------------------|-------------------------|----------------------------------------------------------------------------------|-----------------------------------------------------------------|
| FlowDrop            | Svelte + SvelteFlow     | API‑driven with session context; supports synchronous, queued, and scheduled runs | Graph JSON/config; content entities for runs, logs, artifacts   |
| BPMN.io             | BPMN.io (bpmn‑js)       | Non‑prescriptive; execution delegated to adopter (e.g., ECA/AI agents)          | BPMN XML per implementation; adopter owns configuration         |
| ComfyUI Integration | Drupal (blocks/forms)   | Form triggers and headless/API; queue/batch support                             | Workflow entities; image proxy; optional local storage          |
| React Flow          | React + ReactFlow       | Non‑prescriptive; execution delegated to adopter (e.g., ECA/AI agents)          | Graph JSON/config per implementation                            |
| Mini Kanban          | JavaScript + Twig       | State transitions for task flows (not a general graph executor)                 | Entities/fields for cards/items                                 |

## Scored comparison

Scored 1–3 is a subjective snapshot which considers full Potential of the modules analysed.

| Description                                           | FlowDrop           | BPMN IO            | ComfyUI            | React Flow           | Mini Kanban        |
|-------------------------------------------------------|--------------------|--------------------|--------------------|----------------------|--------------------|
| Quality of the graph Editor                           | :star::star::star: | :star::star:       | :star::star::star: | :star::star::star:   | N/A                |
| Quality of the user interactions                      | :star::star::star: | :star::star:       | :star::star::star: | :star::star::star:   | :star::star::star: |
| Ability to modify Editor UI                           | :star::star::star: | :star:             | :star::star::star: | :star::star::star:   | :star::star::star: |
| Built-in execution control                            | :star::star:       | N/A                | :star::star::star: | N/A                  | N/A                |
| Depth of integration with Drupal entities/config/APIs | :star::star::star: | :star::star::star: | :star:             | :star::star::star:   | :star::star::star: |
| Ease of adding nodes, processors, and integrations    | :star::star::star: | :star::star:       | :star:             | N/A                  | N/A                |
| Independent of external systems                       | :star::star::star: | :star::star::star: | :star:             | :star::star::star:   | :star::star::star: |
| Stability, docs, and production usage                 | :star:             | :star::star::star: | :star::star:       | :star:               | :star::star:       |
| Overall Score                                         | :heart:            | :heart:            | :heart:            | :heart:              | :heart:            |


!!! note
    - Scores are directional to aid selection; they will evolve as modules mature.
    - more :star: indicates better integrated.
    - “Overall” is subjective and each project have its own benefits.
    - Extensibility and Orchestration are lacking for React FLow / Mini kanban doesn't mean its bad. It means it is not opinionated about that part.


## References

- [Glossary](./glossary.md)
- [ComfyUI Integration](https://www.drupal.org/project/comfyui)
- [React Flow](https://www.drupal.org/project/react_flow)
- [Mini Kanban](https://www.drupal.org/project/minikanban)
- [BPMN IO](https://www.drupal.org/project/bpmn_io)
