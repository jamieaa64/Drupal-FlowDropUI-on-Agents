/**
 * FlowDrop IIFE Wrapper
 * This module re-exports the @d34dman/flowdrop library for IIFE consumption
 * 
 * @description
 * This wrapper allows the FlowDrop library to be consumed as an IIFE (Immediately Invoked Function Expression)
 * for use in browser environments, Drupal integrations, and other contexts where ES modules are not ideal.
 * 
 * All exports from @d34dman/flowdrop are re-exported here without modification,
 * preserving the full API surface and functionality.
 * 
 * @example
 * // Include in HTML
 * <link rel="stylesheet" href="flowdrop.css">
 * <script src="flowdrop.iife.js"></script>
 * <script>
 *   const { WorkflowEditor, mountWorkflowEditor } = window.FlowDrop;
 *   mountWorkflowEditor('#app', { apiEndpoint: '/api' });
 * </script>
 */

/**
 * Import styles
 * Import base CSS styles from the FlowDrop package using the proper export
 */
import "@d34dman/flowdrop/styles/base.css";

/**
 * Explicit imports to ensure bundling
 * Import all components, utilities, and functions from @d34dman/flowdrop
 */
import {
	FlowDropApiClient,
	EnhancedFlowDropApiClient,
	WorkflowEditor,
	NodeSidebar,
	WorkflowNodeComponent,
	SimpleNodeComponent,
	ToolNodeComponent,
	NotesNodeComponent,
	CanvasBanner,
	sampleNodes,
	sampleWorkflow,
	mountWorkflowEditor,
	unmountWorkflowEditor,
	mountFlowDropApp,
	unmountFlowDropApp
} from "@d34dman/flowdrop";

// Import all utilities with wildcard
import * as FlowDropUtils from "@d34dman/flowdrop";

// Re-export all types
export type {
	NodeCategory,
	NodeDataType,
	NodePort,
	NodeMetadata,
	NodeConfig,
	WorkflowNode,
	WorkflowEdge,
	Workflow,
	ApiResponse,
	NodesResponse,
	WorkflowResponse,
	WorkflowsResponse,
	ExecutionStatus,
	ExecutionResult,
	FlowDropConfig,
	WorkflowEvents,
	WorkflowEditorConfig,
	EditorFeatures,
	UIConfig,
	APIConfig,
	ExecutionConfig,
	StorageConfig,
	NodeType,
	WorkflowData,
	EditorExecutionResult,
	EditorState
} from "@d34dman/flowdrop";

// Re-export API clients
export { FlowDropApiClient, EnhancedFlowDropApiClient };

// Re-export components
export {
	WorkflowEditor,
	NodeSidebar,
	WorkflowNodeComponent,
	SimpleNodeComponent,
	ToolNodeComponent,
	NotesNodeComponent,
	CanvasBanner
};

// Re-export sample data
export { sampleNodes, sampleWorkflow };

// Re-export mount functions
export {
	mountWorkflowEditor,
	unmountWorkflowEditor,
	mountFlowDropApp,
	unmountFlowDropApp
};

// Re-export all utilities
export * from "@d34dman/flowdrop";

// Export the full library for convenience
export { FlowDropUtils };
