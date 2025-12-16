/**
 * FlowDrop Editor Integration for Drupal
 * Provides a workflow editor page with configurable endpoint support
 */

(function (once, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.flowdropWorkflowEditor = {
    attach: function (context, settings) {
      // Use once to ensure this only runs once per element
      once("flowdrop-workflow-editor", ".flowdrop-editor-container", context).forEach(function (editorContainer) {
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
        const currentWorkflow = drupalSettings.flowdrop?.workflow;
        const workflowId = currentWorkflow?.id;

        // Get endpoint configuration from Drupal settings
        let finalEndpointConfig = drupalSettings.flowdrop?.endpointConfig;

        // If no endpointConfig is provided, create a default one
        if (!finalEndpointConfig) {
          console.warn('⚠️ No endpointConfig provided in drupalSettings.flowdrop. Using default configuration.');

          // Create default endpoint configuration
          finalEndpointConfig = {
            baseUrl: drupalSettings.flowdrop?.apiBaseUrl || "/api/flowdrop",
            endpoints: {
              nodes: {
                list: "/nodes",
                get: "/nodes/{id}",
                byCategory: "/nodes?category={category}",
                metadata: "/nodes/{id}/metadata"
              },
              workflows: {
                list: "/workflows",
                get: "/workflows/{id}",
                create: "/workflows",
                update: "/workflows/{id}",
                delete: "/workflows/{id}",
                validate: "/workflows/validate",
                export: "/workflows/{id}/export",
                import: "/workflows/import"
              },
              executions: {
                execute: "/workflows/{id}/execute",
                status: "/executions/{id}",
                cancel: "/executions/{id}/cancel",
                logs: "/executions/{id}/logs",
                history: "/executions"
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
          if (drupalSettings.flowdrop?.csrfToken) {
            finalEndpointConfig.headers["X-CSRF-Token"] = drupalSettings.flowdrop.csrfToken;
          }
        }


        // Prepare initial workflow data
        let initialWorkflowData = null;

        if (currentWorkflow) {
          // Use the existing workflow data from Drupal
          initialWorkflowData = {
            id: currentWorkflow.id,
            name: currentWorkflow.label || `Workflow ${currentWorkflow.id}`,
            description: currentWorkflow.description || "",
            nodes: currentWorkflow.nodes || [],
            edges: currentWorkflow.edges || [],
            metadata: {
              ...currentWorkflow.metadata,
              version: "1.0.0",
              created: currentWorkflow.created,
              changed: currentWorkflow.changed,
            },
          };

        } else {
          // Create a new workflow structure
          initialWorkflowData = {
            id: workflowId || `workflow_${Date.now()}`,
            name: `New Workflow ${Date.now()}`,
            description: "A new FlowDrop workflow",
            nodes: [],
            edges: [],
            metadata: {
              version: "1.0.0",
              created: new Date().toISOString(),
              changed: new Date().toISOString(),
            },
          };
        }

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
              height: "100%", // Use 100% to fill the container properly
              width: "100%",
              showNavbar: true, // Enable navbar for Drupal integration
              navbarTitle: initialWorkflowData.name,
              navbarActions: [
                {
                  label: "Save",
                  href: "#",
                  variant: "primary",
                  icon: "mdi:floppy-disk",
                  onclick: (Event) => {
                    const clickHandler = async function () {
                      if (typeof window !== 'undefined' && window.flowdropSave) {
                        return await window.flowdropSave();
                      } else {
                        console.warn('⚠️ Save functionality not available');
                      }
                    }
                    clickHandler();
                  }
                },
                {
                  label: "Back",
                  href: "/admin/structure/flowdrop-workflow",
                  variant: "primary",
                  icon: "mdi:arrow-back"
                }
              ],
            });

            // Mark as initialized
            editorContainer.dataset.flowdropInitialized = "true";

            // Store app reference for cleanup
            editorContainer.flowdropApp = currentApp;

            // Add keyboard shortcut for save (Ctrl+S)
            const keydownHandler = (event) => {
              if ((event.ctrlKey || event.metaKey) && event.key === "s") {
                event.preventDefault();
                // Use the exposed save functionality from the FlowDrop app
                if (currentApp && typeof currentApp.save === 'function') {
                  currentApp.save().catch(error => {
                    // Save failed
                  });
                }
              }
            };

            document.addEventListener("keydown", keydownHandler);

            // Store the handler for cleanup
            editorContainer.keydownHandler = keydownHandler;

          } catch (error) {
            // Show user-friendly error message
            editorContainer.innerHTML = `
              <div style="padding: 20px; text-align: center; color: #d32f2f;">
                <h3>Failed to load FlowDrop editor</h3>
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
      const containers = context.querySelectorAll(".flowdrop-editor-container");

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
    }
  };

})(once, Drupal, drupalSettings);
