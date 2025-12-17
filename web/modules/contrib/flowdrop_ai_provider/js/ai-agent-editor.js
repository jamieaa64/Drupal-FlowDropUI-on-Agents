/**
 * FlowDrop AI Agent Editor Integration for Drupal
 * Provides a workflow editor that saves to AI Agent configurations
 */

(function (once, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.flowdropAiAgentEditor = {
    attach: function (context, settings) {
      // Use once to ensure this only runs once per element
      once("flowdrop-ai-agent-editor", ".flowdrop-ai-editor-container", context).forEach(function (editorContainer) {
        // Skip if already initialized
        if (editorContainer.dataset.flowdropInitialized) {
          return;
        }

        // Check if FlowDrop is available
        if (typeof window.FlowDrop === "undefined") {
          editorContainer.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #d32f2f;">
              <h3>FlowDrop Editor Not Available</h3>
              <p>The FlowDrop library could not be loaded. Please ensure the flowdrop_ui module is enabled.</p>
            </div>
          `;
          return;
        }

        // Get current workflow data from Drupal settings
        const currentWorkflow = drupalSettings.flowdropAi?.workflow;
        const agentId = editorContainer.dataset.agentId;
        const listUrl = drupalSettings.flowdropAi?.listUrl || '/admin/config/ai/flowdrop-ai/agents';

        // Get endpoint configuration from Drupal settings
        let finalEndpointConfig = drupalSettings.flowdropAi?.endpointConfig;

        // If no endpointConfig is provided, create a default one
        if (!finalEndpointConfig) {
          console.warn('No endpointConfig provided in drupalSettings.flowdropAi. Using default configuration.');

          // Create default endpoint configuration for AI Agent API
          finalEndpointConfig = {
            baseUrl: drupalSettings.flowdropAi?.apiBaseUrl || "/api/flowdrop-ai",
            endpoints: {
              nodes: {
                list: "/tools",
                get: "/tools/{id}",
                byCategory: "/tools/by-category",
                metadata: "/tools/{id}/schema"
              },
              workflows: {
                list: "/agents",
                get: "/agents/{id}/workflow",
                create: "/workflow/save",
                update: "/workflow/save",
                delete: "/agents/{id}",
                validate: "/workflow/validate",
                export: "/agents/{id}/workflow",
                import: "/workflow/save"
              }
            },
            timeout: 30000,
            retry: {
              enabled: true,
              maxAttempts: 3,
              delay: 1000,
              backoff: "exponential"
            },
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json"
            }
          };

          // Add CSRF token if available
          if (drupalSettings.flowdropAi?.csrfToken) {
            finalEndpointConfig.headers["X-CSRF-Token"] = drupalSettings.flowdropAi.csrfToken;
          }
        }

        // Prepare initial workflow data
        let initialWorkflowData = null;

        if (currentWorkflow) {
          // Use the existing workflow data from Drupal
          initialWorkflowData = {
            id: currentWorkflow.id,
            name: currentWorkflow.name || currentWorkflow.label || `AI Agent ${currentWorkflow.id}`,
            description: currentWorkflow.description || "",
            nodes: currentWorkflow.nodes || [],
            edges: currentWorkflow.edges || [],
            metadata: {
              ...currentWorkflow.metadata,
              version: "1.0.0",
              aiAgentMode: true,
              agentId: agentId,
            },
          };
        } else {
          // Create a new workflow structure for new agent
          initialWorkflowData = {
            id: agentId || `new_agent_${Date.now()}`,
            name: "New AI Agent",
            description: "A new AI Agent created with FlowDrop",
            nodes: [],
            edges: [],
            metadata: {
              version: "1.0.0",
              created: new Date().toISOString(),
              changed: new Date().toISOString(),
              aiAgentMode: true,
              isNew: true,
            },
          };
        }

        // Custom save function for AI Agent
        async function saveAiAgent() {
          try {
            // Get current workflow state from the app
            const workflowData = window.currentFlowDropApp?.getWorkflow?.() || initialWorkflowData;

            const baseUrl = finalEndpointConfig.baseUrl;
            const saveUrl = `${baseUrl}/workflow/save`;

            const response = await fetch(saveUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              },
              body: JSON.stringify(workflowData),
            });

            const result = await response.json();

            if (result.success) {
              // Show success message
              if (typeof Drupal.announce === 'function') {
                Drupal.announce(Drupal.t('AI Agent saved successfully'));
              }

              // Update metadata if this was a new agent
              if (initialWorkflowData.metadata.isNew && result.data?.agents?.[0]?.id) {
                initialWorkflowData.metadata.isNew = false;
                initialWorkflowData.metadata.agentId = result.data.agents[0].id;

                // Redirect to edit page for the new agent
                const newEditUrl = listUrl.replace('/agents', `/agents/${result.data.agents[0].id}/edit`);
                window.location.href = newEditUrl;
              }

              return result;
            } else {
              console.error('Save failed:', result.error || result.message);
              alert('Failed to save AI Agent: ' + (result.message || 'Unknown error'));
              throw new Error(result.error || result.message || 'Save failed');
            }
          } catch (error) {
            console.error('Error saving AI Agent:', error);
            alert('Error saving AI Agent: ' + error.message);
            throw error;
          }
        }

        // Make save function available globally
        window.flowdropSave = saveAiAgent;

        // Initialize the editor
        async function initializeEditor() {
          try {
            // Validate that we have the required container
            if (!editorContainer) {
              throw new Error("Editor container is required");
            }

            // Validate endpoint configuration
            if (!finalEndpointConfig.baseUrl) {
              throw new Error("API base URL is required");
            }

            // Mount the full FlowDrop app (includes NodeSidebar + WorkflowEditor)
            const currentApp = await window.FlowDrop.mountFlowDropApp(editorContainer, {
              workflow: initialWorkflowData,
              endpointConfig: finalEndpointConfig,
              height: "100%",
              width: "100%",
              showNavbar: true,
              navbarTitle: initialWorkflowData.name,
              navbarActions: [
                {
                  label: "Save AI Agent",
                  href: "#",
                  variant: "primary",
                  icon: "mdi:floppy-disk",
                  onclick: (Event) => {
                    const clickHandler = async function () {
                      await saveAiAgent();
                    }
                    clickHandler();
                  }
                },
                {
                  label: "Back to List",
                  href: listUrl,
                  variant: "secondary",
                  icon: "mdi:arrow-back"
                }
              ],
            });

            // Store app reference globally for save function
            window.currentFlowDropApp = currentApp;

            // Mark as initialized
            editorContainer.dataset.flowdropInitialized = "true";

            // Store app reference for cleanup
            editorContainer.flowdropApp = currentApp;

            // Add keyboard shortcut for save (Ctrl+S)
            const keydownHandler = (event) => {
              if ((event.ctrlKey || event.metaKey) && event.key === "s") {
                event.preventDefault();
                saveAiAgent().catch(error => {
                  console.error('Save failed:', error);
                });
              }
            };

            document.addEventListener("keydown", keydownHandler);

            // Store the handler for cleanup
            editorContainer.keydownHandler = keydownHandler;

          } catch (error) {
            console.error('Failed to initialize FlowDrop AI editor:', error);
            // Show user-friendly error message
            editorContainer.innerHTML = `
              <div style="padding: 20px; text-align: center; color: #d32f2f;">
                <h3>Failed to load FlowDrop AI Agent Editor</h3>
                <p>Error: ${error.message}</p>
                <p>Please refresh the page or contact support if the problem persists.</p>
              </div>
            `;
          }
        }

        // Initialize the editor
        initializeEditor();
      });
    },

    detach: function (context, settings, trigger) {
      // Cleanup when elements are removed
      const containers = context.querySelectorAll(".flowdrop-ai-editor-container");

      containers.forEach(container => {
        if (container.flowdropApp && typeof container.flowdropApp.destroy === "function") {
          container.flowdropApp.destroy();
          delete container.flowdropApp;
        }

        if (container.keydownHandler) {
          document.removeEventListener("keydown", container.keydownHandler);
          delete container.keydownHandler;
        }

        delete container.dataset.flowdropInitialized;
      });

      // Clean up global references
      if (window.currentFlowDropApp) {
        delete window.currentFlowDropApp;
      }
      if (window.flowdropSave) {
        delete window.flowdropSave;
      }
    }
  };

})(once, Drupal, drupalSettings);
