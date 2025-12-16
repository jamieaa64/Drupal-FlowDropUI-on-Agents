# CLAUDE-BRANCH.md

## PURPOSE OF THIS FILE

**IMPORTANT FOR FUTURE AGENTS**: This file documents the current branch's purpose, progress, and implementation plans.

- **DO NOT REMOVE THIS SECTION** - it helps orient future agents to the branch context
- **Current Branch**: `main`
- **Branch Goal**: Initial project setup with all required modules
- **Update the branch name above** when working on a different branch

---

## Project Summary

**Short Title**: Visual Flow Builder for AI Agents
**Short Description**: A powerful and easy-to-use visual editor for creating Agent flows using FlowDrop UI that works directly with AI Agents 2.0.

## Problem/Motivation

The current AI Agents forms are complex to use. It's even more complex if you have a flow of AI Agents across the site. They are buried in various forms, making it difficult to visualise what is happening.

## Proposed Resolution

1. Create issues and new projects for FlowDrop UI and FlowDrop UI Agents
2. Figure out if FlowDrop UI can use Modeller API's integrations with Agents
3. FlowDrop UI's Save function saves to AI Agent Config (editable as normal agent)
4. FlowDrop UI can open existing Agents
5. FlowDrop UI can open existing Flows (Orchestration Agents?)
6. FlowDrop UI Tool API Integration (not function calling)
7. Clean up FlowDrop UI's AI Agent UI Elements (remove workflow-related elements)
8. Clean up FlowDrop UI's Tool Drawer (remove unusable tools)
9. UI to connect FlowDrop UI Agents to Assistants, ChatInput and Chatbots
10. Explore industry's approach to Agent Flows

## Module Relationships

- **flowdrop** - Original module
- **flowdrop_ui** - Attempt to separate UI from module (not done yet)
- **flowdrop_ai_provider** - Connects FlowDrop to AI Agents
- **modeler_api** - One way to connect FlowDrop UI to AI Agents
- **ai_integration_eca** - Reference for how ECA's BPMN front-end connects to AI Agents
- tool - This is the way AI Agents will connect to Drupal, there is something called function calling in the AI Agents module now, we want to use Tool instead (there is a sub-module that turns all Tools into function calls) but the future is tool and so tools should appear in flowdrop.
---

## Current Status

### Main Branch (Initial Setup)
- [x] Drupal 11 installed with DDEV
- [x] Core modules downloaded (not yet enabled):
  - AI, AI Agents, AI Provider OpenAI
  - FlowDrop, FlowDrop UI, FlowDrop AI Provider
  - Tool, Modeler API
  - ECA, AI Integration ECA
  - Gin theme suite
  - Key, Token, Search API
- [x] Environment variables configured (OpenAI, Anthropic keys)
- [x] Configuration sync directory set up
- [x] Documentation created (README.md, CLAUDE.md)
- [ ] Modules enabled and configured
- [ ] Initial config exported

### Next Steps
1. Enable and configure modules
2. Set up API keys in Key module
3. Configure Gin as admin theme
4. Export initial configuration
5. Begin FlowDrop UI integration work on feature branch

---

## AI Planning

*Future development plans and notes will go here*
