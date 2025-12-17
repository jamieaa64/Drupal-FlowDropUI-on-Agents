# CLAUDE.md

Project guidance for AI agents working on this codebase.

## Project Overview

**Drupal FlowDrop UI on Agents** - A Drupal 11 project for building AI Agents and Tools using FlowDrop UI.

### Goal
Create a visual flow builder for AI Agents that:
- Provides an easy-to-use visual editor for creating agent flows
- Works directly with the existing AI Agents module (2.0)
- Saves directly to AI Agent Config (editable as normal agents)
- Integrates with Tool API (not function calling)

### Key Modules
| Module | Purpose |
|--------|---------|
| `drupal/ai` | Core AI functionality |
| `drupal/ai_agents` | AI Agents framework |
| `drupal/ai_provider_openai` | OpenAI integration |
| `drupal/tool` | Tool API |
| `drupal/flowdrop` | Original FlowDrop module |
| `drupal/flowdrop_ui` | Visual UI (being separated from core module) |
| `drupal/modeler_api` | Connects visual editors to config entities |
| `flowdrop_ui_agents` | **CUSTOM** - FlowDrop editor for AI Agents via Modeler API |
| `drupal/flowdrop_ai_provider` | OLD - Being replaced by flowdrop_ui_agents |
| `drupal/eca` | Event-Condition-Action framework |
| `drupal/gin` | Admin theme |
| `drupal/key` | API key management |
| `drupal/search_api` | Search functionality |

### Current Implementation Status
**Phase 6 Complete** - FlowDrop visual editor now saves to AI Agent config entities!

**Test URL:** `/admin/config/ai/agents/agent_bundle_lister/edit_with/flowdrop_agents`

**Next Phase (6.5):** Port visual improvements from flowdrop_ai_provider, then remove it.

## Development Environment

### Prerequisites
- DDEV installed locally
- Git

### Local Setup (First Time)
```bash
git clone git@github.com:jamieaa64/Drupal-FlowDropUI-on-Agents.git
cd Drupal-FlowDropUI-on-Agents
ddev start
ddev composer install
ddev drush site:install --existing-config --account-pass=admin -y
ddev launch
```

### After Pulling Changes
```bash
ddev composer install
ddev drush cim -y
ddev drush updb -y
ddev drush cr
```

### Common Commands
| Command | Description |
|---------|-------------|
| `ddev start` | Start local environment |
| `ddev stop` | Stop local environment |
| `ddev launch` | Open site in browser |
| `ddev drush uli` | Get one-time login link |
| `ddev drush cr` | Clear all caches |
| `ddev drush cex -y` | Export configuration |
| `ddev drush cim -y` | Import configuration |
| `ddev drush en <module>` | Enable a module |
| `ddev drush pmu <module>` | Uninstall a module |

### Credentials
- **Username:** admin
- **Password:** admin

### Environment Variables
API keys are configured globally in `~/.ddev/global_config.yaml` and available as:
- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`

In Drupal, use the Key module with "Environment" provider to access these.

## Project Structure
```
├── .ddev/              # DDEV configuration
├── config/sync/        # Drupal configuration (version controlled)
├── web/                # Drupal docroot
│   ├── modules/custom/ # Custom modules
│   │   └── flowdrop_ui_agents/  # FlowDrop AI Agents integration (MAIN DEV)
│   ├── themes/custom/  # Custom themes
│   └── sites/default/  # Site settings
├── composer.json       # PHP dependencies
├── CLAUDE.md           # This file - AI agent guidance
├── CLAUDE-BRANCH.md    # Current branch context and progress
├── CLAUDE-NOTES.md     # Historical findings and technical details
├── CLAUDE-PLANNING.md  # Full implementation plan
└── README.md           # Project documentation
```

### Custom Module: flowdrop_ui_agents
```
web/modules/custom/flowdrop_ui_agents/
├── src/Plugin/ModelerApiModeler/FlowDropAgents.php  # Modeler plugin
├── src/Service/AgentWorkflowMapper.php              # Agent ↔ FlowDrop conversion
├── src/Service/WorkflowParser.php                   # JSON → Components
├── js/flowdrop-agents-editor.js                     # FlowDrop init & save
└── css/flowdrop-agents-editor.css                   # Styling
```

## Development Workflow

### Branch Strategy
- `main` - Stable baseline, production-ready
- `feature/*` - Feature development branches
- Always develop on feature branches, not main

### Workflow
1. Create feature branch from main
2. Make changes
3. Export config: `ddev drush cex -y`
4. **Ask user to test before committing**
5. Commit with descriptive message
6. Code review via Codex (see CODEX-BRANCH.md)
7. Merge to main when approved

### Configuration Management
Drupal config is stored in `config/sync/`. After making changes in the UI:
```bash
ddev drush cex -y           # Export config
git add config/sync/
git commit -m "Description of config changes"
```

## Important Notes for AI Agents

1. **Always ask before committing** - User wants to test before commits
2. **Authentication** - Ask user to handle any SSH/auth operations (git clone, push, etc.)
3. **Feature branches** - Do main development on feature branches
4. **Update CLAUDE-BRANCH.md** - Keep branch context file updated with progress
5. **Config exports** - Always export config after Drupal UI changes
6. **Never commit API keys** - They're in global DDEV config, not project files

## Related Documentation
- [AI Module](https://www.drupal.org/project/ai)
- [AI Agents](https://www.drupal.org/project/ai_agents)
- [FlowDrop](https://www.drupal.org/project/flowdrop)
- [ECA](https://www.drupal.org/project/eca)
- [DDEV Documentation](https://ddev.readthedocs.io/)
