# Drupal FlowDrop UI on Agents

A Drupal 11 project for building AI Agents and Tools using FlowDrop UI.

## About

This project enables the creation and management of AI Agents and Tools through a visual interface powered by FlowDrop UI within Drupal.

## Requirements

- [DDEV](https://ddev.com/) (local development environment)
- Git
- Composer (handled by DDEV)

## Local Development Setup

### First Time Setup

1. **Clone the repository**
   ```bash
   git clone git@github.com:jamieaa64/Drupal-FlowDropUI-on-Agents.git
   cd Drupal-FlowDropUI-on-Agents
   ```

2. **Start DDEV**
   ```bash
   ddev start
   ```

3. **Install dependencies**
   ```bash
   ddev composer install
   ```

4. **Install Drupal from existing config**
   ```bash
   ddev drush site:install --existing-config --account-pass=admin -y
   ```

5. **Access the site**
   ```bash
   ddev launch
   # Or get a one-time login link:
   ddev drush uli
   ```

### After Pulling Changes

```bash
ddev composer install
ddev drush config:import -y
ddev drush updb -y
ddev drush cache:rebuild
```

## Development Workflow

1. Make changes in the Drupal UI
2. Export configuration: `ddev drush cex -y`
3. Commit changes: `git add -A && git commit -m "Description of changes"`
4. Push: `git push`

## Common Commands

| Command | Description |
|---------|-------------|
| `ddev start` | Start the local environment |
| `ddev stop` | Stop the local environment |
| `ddev launch` | Open site in browser |
| `ddev drush uli` | Get one-time login link |
| `ddev drush cr` | Clear all caches |
| `ddev drush cex -y` | Export configuration |
| `ddev drush cim -y` | Import configuration |
| `ddev drush status` | Check Drupal status |

## Credentials

- **Username:** admin
- **Password:** admin

## Project Structure

```
├── .ddev/              # DDEV configuration
├── config/sync/        # Drupal configuration (version controlled)
├── web/                # Drupal docroot
│   ├── modules/custom/ # Custom modules
│   ├── themes/custom/  # Custom themes
│   └── sites/default/  # Site settings
├── composer.json       # PHP dependencies
└── README.md           # This file
```
