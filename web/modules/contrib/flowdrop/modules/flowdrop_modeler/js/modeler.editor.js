/**
 * FlowDrop Modeler Editor Integration for Drupal
 * Provides a workflow editor page with BPMN data initialization through drupalSettings
 */

(function (once, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.flowdropModelerEditor = {
    attach: function (context, settings) {
      // Use once to ensure this only runs once per element
      once("flowdrop-modeler-editor", ".flowdrop-editor-container", context).forEach(function (editorContainer) {
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

        // Get workflow data from Drupal settings
        const workflowData = drupalSettings.flowdrop?.workflow;
        const workflowId = workflowData?.id;

        if (!workflowData) {
          editorContainer.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #d32f2f;">
              <h3>No Workflow Data</h3>
              <p>No workflow data was provided. Please ensure the workflow is properly configured.</p>
            </div>
          `;
          return;
        }

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


        // Initialize the editor with workflow data
        async function initializeModelerEditor() {
          try {
            // Validate that we have the required container
            if (!editorContainer) {
              throw new Error("Editor container is required");
            }

            // Workflow data is now pre-transformed on the server side
            const initialWorkflowData = workflowData;

            // Get available node types from server-side service
            const availableNodeTypes = drupalSettings.flowdrop?.availableNodeTypes || [];

            // Mount the full FlowDrop app (includes NodeSidebar + WorkflowEditor)
            const currentApp = await window.FlowDrop.mountFlowDropApp(editorContainer, {
              workflow: initialWorkflowData,
              nodes: availableNodeTypes,
              endpointConfig: finalEndpointConfig,
              height: "100%", // Use 100% to fill the container properly
              width: "100%",
              showNavbar: true, // Enable navbar for Drupal integration
            });


            // Mark as initialized
            editorContainer.dataset.flowdropInitialized = "true";

            // Store app reference for cleanup
            editorContainer.flowdropApp = currentApp;

            // Add keyboard shortcuts
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

            // Add custom event listeners for modeler-specific functionality
            const workflowSaveHandler = (event) => {
              // Handle workflow save
            };

            const workflowExportHandler = (event) => {
              // Handle workflow export
            };

            const nodeSelectedHandler = (event) => {
              // Handle node selection
            };

            editorContainer.addEventListener("workflow-save", workflowSaveHandler);
            editorContainer.addEventListener("workflow-export", workflowExportHandler);
            editorContainer.addEventListener("node-selected", nodeSelectedHandler);

            // Store event handlers for cleanup
            editorContainer.workflowSaveHandler = workflowSaveHandler;
            editorContainer.workflowExportHandler = workflowExportHandler;
            editorContainer.nodeSelectedHandler = nodeSelectedHandler;

            // Expose the app instance for debugging
            if (typeof window.console !== "undefined" && window.console.log) {
              window.flowdropModelerApp = currentApp;
            }

          } catch (error) {
            // Show user-friendly error message
            editorContainer.innerHTML = `
              <div style="padding: 20px; text-align: center; color: #d32f2f;">
                <h3>Failed to load FlowDrop modeler editor</h3>
                <p>Error: ${error.message}</p>
                <p>Please refresh the page or contact support if the problem persists.</p>
              </div>
            `;
          }
        }

        // Initialize the editor
        initializeModelerEditor();
      });
    },

    detach: function (context, settings, trigger) {
      // Cleanup when elements are removed
      const containers = context.querySelectorAll(".flowdrop-editor-container");

      containers.forEach(container => {
        // Cleanup FlowDrop app
        if (container.flowdropApp && typeof container.flowdropApp.destroy === "function") {
          container.flowdropApp.destroy();
          delete container.flowdropApp;
        }

        // Cleanup keyboard handler
        if (container.keydownHandler) {
          document.removeEventListener("keydown", container.keydownHandler);
          delete container.keydownHandler;
        }

        // Cleanup custom event handlers
        if (container.workflowSaveHandler) {
          container.removeEventListener("workflow-save", container.workflowSaveHandler);
          delete container.workflowSaveHandler;
        }

        if (container.workflowExportHandler) {
          container.removeEventListener("workflow-export", container.workflowExportHandler);
          delete container.workflowExportHandler;
        }

        if (container.nodeSelectedHandler) {
          container.removeEventListener("node-selected", container.nodeSelectedHandler);
          delete container.nodeSelectedHandler;
        }

        delete container.dataset.flowdropInitialized;
      });
    }
  };

  // Add utility functions for the modeler
  Drupal.flowdropModeler = {
    /**
     * Get the current workflow data
     */
    getWorkflowData: function() {
      return drupalSettings.flowdrop?.workflow;
    },

    /**
     * Get BPMN metadata from the workflow
     */
    getBpmnMetadata: function() {
      const workflowData = this.getWorkflowData();
      return workflowData?.metadata?.bpmn || {};
    },

    /**
     * Export workflow as BPMN data
     */
    exportAsBpmn: function() {
      const workflowData = this.getWorkflowData();
      const bpmnData = {
        events: {},
        conditions: {},
        gateways: {},
        actions: {},
      };

      // Convert nodes back to BPMN format
      if (workflowData?.nodes) {
        workflowData.nodes.forEach(node => {
          const originalId = node.data?.originalId;
          if (originalId) {
            const nodeType = node.data?.metadata?.nodeType;
            if (nodeType && bpmnData[nodeType + "s"]) {
              bpmnData[nodeType + "s"][originalId] = {
                label: node.label,
                configuration: node.data?.configuration || {},
              };
            }
          }
        });
      }

      return bpmnData;
    },

    /**
     * Validate workflow data
     */
    validateWorkflow: function() {
      const workflowData = this.getWorkflowData();
      const errors = [];

      if (!workflowData?.nodes || workflowData.nodes.length === 0) {
        errors.push("No nodes found in workflow");
      }

      if (!workflowData?.edges || workflowData.edges.length === 0) {
        errors.push("No edges found in workflow");
      }

      return {
        valid: errors.length === 0,
        errors: errors,
      };
    },

    /**
     * Get the current FlowDrop app instance
     */
    getAppInstance: function() {
      return window.flowdropModelerApp || null;
    }
  };

})(once, Drupal, drupalSettings);