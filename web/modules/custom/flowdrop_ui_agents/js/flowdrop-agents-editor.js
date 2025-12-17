/**
 * @file
 * FlowDrop Agents Editor initialization for Modeler API.
 *
 * Initializes the FlowDrop UI for editing AI Agents via Modeler API.
 */

(function (once, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.flowdropAgentsEditor = {
    attach: function (context, settings) {
      once('flowdrop-agents-init', '#flowdrop-agents-editor', context).forEach(function (editorContainer) {
        // Skip if already initialized.
        if (editorContainer.dataset.flowdropInitialized) {
          return;
        }

        const config = drupalSettings.flowdrop_agents || {};

        // Check if FlowDrop is available.
        if (typeof window.FlowDrop === 'undefined') {
          editorContainer.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #d32f2f;">
              <h3>FlowDrop Editor Not Available</h3>
              <p>The FlowDrop library could not be loaded. Please ensure the flowdrop_ui module is enabled.</p>
            </div>
          `;
          console.error('FlowDrop Agents: FlowDrop library not loaded');
          return;
        }

        // Prepare workflow data.
        const workflowData = config.workflow || {
          id: config.workflowId || 'new_agent',
          name: 'New AI Agent',
          description: '',
          nodes: [],
          edges: [],
          metadata: {
            version: '1.0.0',
            aiAgentMode: true,
          },
        };

        // Create endpoint configuration for FlowDrop Agents API.
        // Uses our custom endpoints that return AI tools, agents, and assistants.
        const endpointConfig = {
          baseUrl: '/api/flowdrop-agents',
          endpoints: {
            nodes: {
              list: '/nodes',
              get: '/nodes/{id}/metadata',
              byCategory: '/nodes/by-category',
              metadata: '/nodes/{id}/metadata',
            },
            workflows: {
              list: '/workflows',
              get: '/workflows/{id}',
              create: '/workflows',
              update: '/workflows/{id}',
              delete: '/workflows/{id}',
              validate: '/workflows/{id}/validate',
              export: '/workflows/{id}',
              import: '/workflows',
            },
            portConfig: '/port-config',
          },
          timeout: 30000,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        };

        // Get the modeler save URL from Modeler API settings.
        const modelerApiSettings = drupalSettings.modeler_api || {};
        const saveUrl = modelerApiSettings.save_url || `/admin/modeler_api/ai_agent/${config.modeler || 'flowdrop_agents'}/save`;
        const isNew = config.isNew || false;

        // Save function using Modeler API.
        async function saveAgent() {
          try {
            // Get workflow from FlowDrop app.
            const app = editorContainer.flowdropApp || window.currentFlowDropApp;
            let currentWorkflow;

            if (app && typeof app.getWorkflow === 'function') {
              currentWorkflow = app.getWorkflow();
            } else {
              console.warn('FlowDrop Agents: No app reference, using initial workflowData');
              currentWorkflow = workflowData;
            }

            // Get CSRF token.
            const tokenUrl = modelerApiSettings.token_url || '/session/token';
            const tokenResponse = await fetch(tokenUrl);
            const csrfToken = await tokenResponse.text();

            // Save via Modeler API.
            const response = await fetch(saveUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Modeler-API-isNew': isNew ? 'true' : 'false',
              },
              body: JSON.stringify(currentWorkflow),
            });

            if (!response.ok) {
              throw new Error('Save failed: ' + response.statusText);
            }

            const result = await response.json();

            if (result.success !== false) {
              if (typeof Drupal.announce === 'function') {
                Drupal.announce(Drupal.t('AI Agent saved successfully'));
              }
              // Mark the app as saved to clear dirty state.
              if (app && typeof app.markAsSaved === 'function') {
                app.markAsSaved();
              }
            } else {
              throw new Error(result.message || 'Save failed');
            }

            return result;
          } catch (error) {
            console.error('FlowDrop Agents: Save error', error);
            alert('Failed to save AI Agent: ' + error.message);
            throw error;
          }
        }

        // Make save function available globally.
        window.flowdropSave = saveAgent;

        // Initialize the FlowDrop editor.
        async function initializeEditor() {
          try {
            const listUrl = '/admin/config/ai/agents';

            const app = await window.FlowDrop.mountFlowDropApp(editorContainer, {
              workflow: workflowData,
              endpointConfig: endpointConfig,
              height: '100%',
              width: '100%',
              showNavbar: true,
              navbarTitle: workflowData.name || workflowData.label || 'AI Agent',
              navbarActions: [
                {
                  label: 'Save AI Agent',
                  href: '#',
                  variant: 'primary',
                  icon: 'mdi:floppy-disk',
                  onclick: function () {
                    saveAgent().catch(function (error) {
                      console.error('Save failed:', error);
                    });
                  },
                },
                {
                  label: 'Back to List',
                  href: listUrl,
                  variant: 'secondary',
                  icon: 'mdi:arrow-back',
                },
              ],
            });

            // Store app reference.
            window.currentFlowDropApp = app;
            editorContainer.flowdropApp = app;
            editorContainer.dataset.flowdropInitialized = 'true';

            // Add Ctrl+S shortcut.
            const keydownHandler = function (event) {
              if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                event.preventDefault();
                saveAgent().catch(function (error) {
                  console.error('Save failed:', error);
                });
              }
            };
            document.addEventListener('keydown', keydownHandler);
            editorContainer.keydownHandler = keydownHandler;

            console.log('FlowDrop Agents: Editor initialized for', config.workflowId);
          } catch (error) {
            console.error('FlowDrop Agents: Failed to initialize editor', error);
            editorContainer.innerHTML = `
              <div style="padding: 20px; text-align: center; color: #d32f2f;">
                <h3>Failed to Initialize Editor</h3>
                <p>Error: ${error.message}</p>
              </div>
            `;
          }
        }

        initializeEditor();
      });
    },

    detach: function (context, settings, trigger) {
      const containers = context.querySelectorAll('#flowdrop-agents-editor');
      containers.forEach(function (container) {
        if (container.flowdropApp && typeof container.flowdropApp.destroy === 'function') {
          container.flowdropApp.destroy();
          delete container.flowdropApp;
        }
        if (container.keydownHandler) {
          document.removeEventListener('keydown', container.keydownHandler);
          delete container.keydownHandler;
        }
        delete container.dataset.flowdropInitialized;
      });

      if (window.currentFlowDropApp) {
        delete window.currentFlowDropApp;
      }
      if (window.flowdropSave) {
        delete window.flowdropSave;
      }
    },
  };

})(once, Drupal, drupalSettings);
