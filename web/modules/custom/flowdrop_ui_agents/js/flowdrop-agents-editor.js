/**
 * @file
 * FlowDrop Agents Editor initialization for Modeler API.
 *
 * Initializes the FlowDrop UI for editing AI Agents via Modeler API.
 */

(function (once, Drupal, drupalSettings) {
  'use strict';

  /**
   * View settings for the editor (not persisted).
   */
  const viewSettings = {
    nodeLayout: 'default',     // 'default' | 'simple'
    agentExpansion: 'expanded' // 'expanded' | 'grouped' | 'collapsed'
  };

  /**
   * Tracks whether there are unsaved changes.
   */
  let hasUnsavedChanges = false;

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

            // CRITICAL: Ensure the workflow ID matches the entity being edited.
            // This prevents saving to the wrong entity when sub-agents are expanded.
            // The config.workflowId is the authoritative ID of the entity being edited.
            const originalId = config.workflowId;
            if (originalId && currentWorkflow.id !== originalId) {
              console.warn('FlowDrop Agents: Correcting workflow ID from', currentWorkflow.id, 'to', originalId);
              currentWorkflow = {
                ...currentWorkflow,
                id: originalId
              };
            }

            // Also ensure we preserve the original label if workflow label was corrupted
            // (e.g., by FlowDrop using first node's label)
            const originalLabel = workflowData.label || workflowData.name;
            if (originalLabel && !currentWorkflow.label) {
              currentWorkflow.label = originalLabel;
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
              // Show visible success message.
              const entityType = config.isAssistantMode ? 'Assistant' : 'Agent';
              showNotification(Drupal.t('@type saved successfully', { '@type': entityType }), 'success');

              // Announce for screen readers.
              if (typeof Drupal.announce === 'function') {
                Drupal.announce(Drupal.t('@type saved successfully', { '@type': entityType }));
              }

              // Mark as saved to clear unsaved indicator.
              markAsSaved();

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
            showNotification(Drupal.t('Failed to save: @error', { '@error': error.message }), 'error');
            throw error;
          }
        }

        /**
         * Marks the workflow as having unsaved changes.
         */
        function markAsUnsaved() {
          if (!hasUnsavedChanges) {
            hasUnsavedChanges = true;
            updateSaveButtonState();
          }
        }

        /**
         * Marks the workflow as saved (no unsaved changes).
         */
        function markAsSaved() {
          hasUnsavedChanges = false;
          updateSaveButtonState();
        }

        /**
         * Updates the save button to show unsaved state.
         */
        function updateSaveButtonState() {
          const saveBtn = editorContainer.querySelector('.flowdrop-navbar__actions .btn-primary, .flowdrop-navbar__actions [data-action="save"]');
          const indicator = document.getElementById('flowdrop-unsaved-indicator');

          if (hasUnsavedChanges) {
            // Add/update unsaved indicator
            if (!indicator) {
              const newIndicator = document.createElement('div');
              newIndicator.id = 'flowdrop-unsaved-indicator';
              newIndicator.className = 'flowdrop-unsaved-indicator';
              newIndicator.innerHTML = '<span class="flowdrop-unsaved-dot"></span> Unsaved changes';
              // Insert at the start of the navbar actions
              const navbar = editorContainer.querySelector('.flowdrop-navbar__actions');
              if (navbar) {
                navbar.insertBefore(newIndicator, navbar.firstChild);
              }
            }
            if (saveBtn) {
              saveBtn.classList.add('flowdrop-btn-unsaved');
            }
          } else {
            // Remove indicator
            if (indicator) {
              indicator.remove();
            }
            if (saveBtn) {
              saveBtn.classList.remove('flowdrop-btn-unsaved');
            }
          }
        }

        /**
         * Sets up change detection listeners.
         */
        function setupChangeDetection() {
          // Listen for FlowDrop workflow changes
          if (window.FlowDrop && window.FlowDrop.events) {
            window.FlowDrop.events.on('workflow:changed', markAsUnsaved);
            window.FlowDrop.events.on('node:changed', markAsUnsaved);
            window.FlowDrop.events.on('edge:changed', markAsUnsaved);
          }

          // Fallback: Listen for any changes in the editor container
          const observer = new MutationObserver(function(mutations) {
            // Ignore mutations from our own indicator
            const isOurMutation = mutations.some(function(m) {
              return m.target.id === 'flowdrop-unsaved-indicator' ||
                     m.target.classList?.contains('flowdrop-notification');
            });
            if (!isOurMutation && !hasUnsavedChanges) {
              // Only mark as unsaved for significant mutations
              const hasSignificantChange = mutations.some(function(m) {
                return m.type === 'childList' && m.addedNodes.length > 0;
              });
              if (hasSignificantChange) {
                markAsUnsaved();
              }
            }
          });

          // Observe the React Flow container for changes
          const flowContainer = editorContainer.querySelector('.react-flow');
          if (flowContainer) {
            observer.observe(flowContainer, {
              childList: true,
              subtree: true,
              attributes: true,
              attributeFilter: ['data-id', 'transform']
            });
          }

          // Store observer for cleanup
          editorContainer.changeObserver = observer;
        }

        /**
         * Shows a toast notification message.
         *
         * @param {string} message - The message to display.
         * @param {string} type - The type: 'success', 'error', 'warning', 'info'.
         */
        function showNotification(message, type) {
          // Remove any existing notifications first.
          const existing = document.querySelectorAll('.flowdrop-notification');
          existing.forEach(function (el) { el.remove(); });

          // Create notification element.
          const notification = document.createElement('div');
          notification.className = 'flowdrop-notification flowdrop-notification--' + type;
          notification.setAttribute('role', 'alert');
          notification.innerHTML = `
            <span class="flowdrop-notification__icon"></span>
            <span class="flowdrop-notification__message">${message}</span>
            <button class="flowdrop-notification__close" aria-label="Close">&times;</button>
          `;

          // Style the notification.
          Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '12px 40px 12px 16px',
            borderRadius: '6px',
            boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
            zIndex: '10000',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            fontSize: '14px',
            fontWeight: '500',
            maxWidth: '400px',
            animation: 'flowdrop-slide-in 0.3s ease-out',
          });

          // Set colors based on type.
          const colors = {
            success: { bg: '#d4edda', border: '#28a745', text: '#155724' },
            error: { bg: '#f8d7da', border: '#dc3545', text: '#721c24' },
            warning: { bg: '#fff3cd', border: '#ffc107', text: '#856404' },
            info: { bg: '#d1ecf1', border: '#17a2b8', text: '#0c5460' },
          };
          const color = colors[type] || colors.info;

          notification.style.backgroundColor = color.bg;
          notification.style.borderLeft = '4px solid ' + color.border;
          notification.style.color = color.text;

          // Add close button styling.
          const closeBtn = notification.querySelector('.flowdrop-notification__close');
          Object.assign(closeBtn.style, {
            position: 'absolute',
            right: '8px',
            top: '50%',
            transform: 'translateY(-50%)',
            background: 'none',
            border: 'none',
            fontSize: '20px',
            cursor: 'pointer',
            color: color.text,
            opacity: '0.7',
          });

          // Close on click.
          closeBtn.addEventListener('click', function () {
            notification.remove();
          });

          // Add to page.
          document.body.appendChild(notification);

          // Auto-remove after 4 seconds.
          setTimeout(function () {
            if (notification.parentNode) {
              notification.style.animation = 'flowdrop-slide-out 0.3s ease-in forwards';
              setTimeout(function () { notification.remove(); }, 300);
            }
          }, 4000);
        }

        // Make save function available globally.
        window.flowdropSave = saveAgent;

        /**
         * Updates all nodes' nodeType to switch between visual styles.
         * 'default' = WorkflowNode, 'simple' = SimpleNode (compact)
         */
        function updateAllNodesLayout(layout) {
          const app = editorContainer.flowdropApp;
          if (!app || typeof app.getWorkflow !== 'function') {
            console.warn('FlowDrop Agents: Cannot update layout - no app reference');
            return;
          }

          const workflow = app.getWorkflow();
          if (!workflow || !workflow.nodes) {
            return;
          }

          // Create new node objects with updated nodeType
          const updatedNodes = workflow.nodes.map(function (node) {
            const newConfig = { ...(node.data?.config || {}) };
            // Set nodeType to 'simple' for compact, or remove for default
            if (layout === 'simple') {
              newConfig.nodeType = 'simple';
            } else {
              delete newConfig.nodeType;
            }
            return {
              ...node,
              data: {
                ...node.data,
                config: newConfig
              }
            };
          });

          // Create new workflow object with updated nodes
          const updatedWorkflow = {
            ...workflow,
            nodes: updatedNodes
          };

          // Use FlowDrop's workflowActions.initialize to fully reset with new data
          if (window.FlowDrop && window.FlowDrop.workflowActions) {
            window.FlowDrop.workflowActions.initialize(updatedWorkflow);
          }

          viewSettings.nodeLayout = layout;
          console.log('FlowDrop Agents: Layout changed to', layout);
        }

        /**
         * Reloads workflow with new expansion mode via AJAX.
         */
        async function reloadWorkflowWithExpansion(expansionMode) {
          const agentId = config.workflowId;
          if (!agentId) {
            console.warn('FlowDrop Agents: No agent ID for expansion reload');
            return;
          }

          const url = '/api/flowdrop-agents/workflow/' + agentId + '?expansion=' + expansionMode;

          try {
            const response = await fetch(url, {
              headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (result.success && result.data) {
              // Use FlowDrop's workflowActions.initialize to load the new workflow
              if (window.FlowDrop && window.FlowDrop.workflowActions) {
                window.FlowDrop.workflowActions.initialize(result.data);
                viewSettings.agentExpansion = expansionMode;
                console.log('FlowDrop Agents: Expansion mode changed to', expansionMode);
              } else {
                console.error('FlowDrop Agents: workflowActions not available');
              }
            } else {
              console.error('FlowDrop Agents: Failed to load workflow with expansion', result.error);
            }
          } catch (error) {
            console.error('FlowDrop Agents: Error reloading workflow:', error);
          }
        }

        /**
         * Creates the view controls toolbar.
         */
        function createViewControls() {
          const toolbar = document.createElement('div');
          toolbar.className = 'flowdrop-agents-view-controls';
          toolbar.innerHTML = `
            <div class="flowdrop-agents-control">
              <label for="layout-select">Layout:</label>
              <select id="layout-select" class="flowdrop-agents-select">
                <option value="default">Default</option>
                <option value="simple">Compact</option>
              </select>
            </div>
            <div class="flowdrop-agents-control">
              <label for="expansion-select">Agents:</label>
              <select id="expansion-select" class="flowdrop-agents-select">
                <option value="expanded">Expanded</option>
                <option value="grouped">Grouped</option>
                <option value="collapsed">Collapsed</option>
              </select>
            </div>
          `;

          // Add event listener for layout mode
          const layoutSelect = toolbar.querySelector('#layout-select');
          layoutSelect.value = viewSettings.nodeLayout;
          layoutSelect.addEventListener('change', function (e) {
            updateAllNodesLayout(e.target.value);
          });

          // Add event listener for expansion mode
          const expansionSelect = toolbar.querySelector('#expansion-select');
          expansionSelect.value = viewSettings.agentExpansion;
          expansionSelect.addEventListener('change', function (e) {
            reloadWorkflowWithExpansion(e.target.value);
          });

          return toolbar;
        }

        /**
         * Injects view controls into the FlowDrop navbar.
         */
        function injectViewControls() {
          // Find the navbar actions area
          const navbar = editorContainer.querySelector('.flowdrop-navbar__actions');
          if (navbar) {
            const controls = createViewControls();
            // Insert at the beginning of actions
            navbar.insertBefore(controls, navbar.firstChild);
          } else {
            // Fallback: add controls above the editor
            const controls = createViewControls();
            editorContainer.parentNode.insertBefore(controls, editorContainer);
          }
        }

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

            // Inject view controls after a short delay to ensure navbar is rendered.
            setTimeout(function () {
              injectViewControls();
              setupChangeDetection();
            }, 100);

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
        if (container.changeObserver) {
          container.changeObserver.disconnect();
          delete container.changeObserver;
        }
        delete container.dataset.flowdropInitialized;
      });

      // Reset unsaved state.
      hasUnsavedChanges = false;

      if (window.currentFlowDropApp) {
        delete window.currentFlowDropApp;
      }
      if (window.flowdropSave) {
        delete window.flowdropSave;
      }
    },
  };

})(once, Drupal, drupalSettings);
