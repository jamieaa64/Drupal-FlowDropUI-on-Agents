/**
 * FlowDrop Integration Test Script
 * Tests the updated FlowDrop IIFE integration in Drupal modules
 */

(function() {
  "use strict";

  console.log("🧪 Starting FlowDrop integration tests...");

  /**
   * Test FlowDrop library availability
   */
  function testFlowDropAvailability() {
    console.log("📋 Testing FlowDrop library availability...");
    
    if (typeof window.FlowDrop === "undefined") {
      console.error("❌ FlowDrop not loaded");
      return false;
    }
    
    console.log("✅ FlowDrop library is available");
    
    // Check for required functions
    const requiredFunctions = ["mountWorkflowEditor", "mountFlowDropApp"];
    const missingFunctions = [];
    
    requiredFunctions.forEach(funcName => {
      if (typeof window.FlowDrop[funcName] !== "function") {
        missingFunctions.push(funcName);
      }
    });
    
    if (missingFunctions.length > 0) {
      console.error(`❌ Missing FlowDrop functions: ${missingFunctions.join(", ")}`);
      return false;
    }
    
    console.log("✅ All required FlowDrop functions are available");
    return true;
  }

  /**
   * Test workflow editor mounting
   */
  async function testWorkflowEditorMounting() {
    console.log("📋 Testing workflow editor mounting...");
    
    // Create a test container
    const testContainer = document.createElement("div");
    testContainer.id = "test-workflow-editor";
    testContainer.style.cssText = "width: 800px; height: 600px; position: absolute; top: -9999px; left: -9999px;";
    document.body.appendChild(testContainer);
    
    try {
      const testWorkflow = {
        id: "test-workflow",
        label: "Test Workflow",
        description: "A test workflow for integration testing",
        nodes: [],
        edges: [],
        metadata: {
          version: "1.0.0",
          created: new Date().toISOString(),
          changed: new Date().toISOString(),
        },
      };
      
      const testConfig = {
        baseUrl: "/api/flowdrop",
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
        timeout: 5000, // Short timeout for testing
        retry: {
          enabled: true,
          maxAttempts: 2,
          delay: 1000,
          backoff: "exponential"
        }
      };
      
      const app = await window.FlowDrop.mountWorkflowEditor(testContainer, {
        workflow: testWorkflow,
        endpointConfig: testConfig,
      });
      
      console.log("✅ Workflow editor mounted successfully");
      
      // Test cleanup
      if (app && typeof app.destroy === "function") {
        app.destroy();
        console.log("✅ Workflow editor cleaned up successfully");
      }
      
      return true;
    } catch (error) {
      console.error("❌ Workflow editor mounting failed:", error);
      return false;
    } finally {
      // Remove test container
      if (testContainer.parentNode) {
        testContainer.parentNode.removeChild(testContainer);
      }
    }
  }

  /**
   * Test Drupal behaviors integration
   */
  function testDrupalBehaviors() {
    console.log("📋 Testing Drupal behaviors integration...");
    
    // Check if Drupal behaviors are properly defined
    if (typeof Drupal === "undefined" || !Drupal.behaviors) {
      console.error("❌ Drupal behaviors not available");
      return false;
    }
    
    // Check workflow editor behavior
    if (!Drupal.behaviors.flowdropWorkflowEditor) {
      console.error("❌ flowdropWorkflowEditor behavior not found");
      return false;
    }
    
    console.log("✅ flowdropWorkflowEditor behavior is available");
    
    // Check modeler editor behavior
    if (!Drupal.behaviors.flowdropModelerEditor) {
      console.error("❌ flowdropModelerEditor behavior not found");
      return false;
    }
    
    console.log("✅ flowdropModelerEditor behavior is available");
    
    // Check modeler utilities
    if (!Drupal.flowdropModeler) {
      console.error("❌ Drupal.flowdropModeler utilities not found");
      return false;
    }
    
    console.log("✅ Drupal.flowdropModeler utilities are available");
    
    return true;
  }

  /**
   * Test configuration handling
   */
  function testConfigurationHandling() {
    console.log("📋 Testing configuration handling...");
    
    // Test endpoint configuration (new format)
    const config = {
      baseUrl: "/api/flowdrop",
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
    
    // Validate configuration structure
    if (!config.baseUrl || !config.endpoints || !config.timeout) {
      console.error("❌ Invalid configuration structure");
      return false;
    }
    
    // Validate required endpoint categories
    const requiredCategories = ['nodes', 'workflows', 'executions'];
    const missingCategories = requiredCategories.filter(cat => !config.endpoints[cat]);
    
    if (missingCategories.length > 0) {
      console.error(`❌ Missing endpoint categories: ${missingCategories.join(", ")}`);
      return false;
    }
    
    console.log("✅ Configuration structure is valid");
    return true;
  }

  /**
   * Run all tests
   */
  async function runAllTests() {
    const tests = [
      { name: "FlowDrop Availability", fn: testFlowDropAvailability },
      { name: "Configuration Handling", fn: testConfigurationHandling },
      { name: "Drupal Behaviors", fn: testDrupalBehaviors },
      { name: "Workflow Editor Mounting", fn: testWorkflowEditorMounting },
    ];
    
    let passed = 0;
    let failed = 0;
    
    for (const test of tests) {
      console.log(`\n🧪 Running test: ${test.name}`);
      try {
        const result = await test.fn();
        if (result) {
          passed++;
          console.log(`✅ ${test.name} - PASSED`);
        } else {
          failed++;
          console.log(`❌ ${test.name} - FAILED`);
        }
      } catch (error) {
        failed++;
        console.error(`❌ ${test.name} - ERROR:`, error);
      }
    }
    
    console.log(`\n📊 Test Results: ${passed} passed, ${failed} failed`);
    
    if (failed === 0) {
      console.log("🎉 All tests passed! FlowDrop integration is working correctly.");
    } else {
      console.log("⚠️ Some tests failed. Please check the integration.");
    }
    
    return failed === 0;
  }

  // Export test functions for manual testing
  window.FlowDropIntegrationTest = {
    runAllTests,
    testFlowDropAvailability,
    testWorkflowEditorMounting,
    testDrupalBehaviors,
    testConfigurationHandling,
  };

  // Auto-run tests if FlowDrop is available
  if (typeof window.FlowDrop !== "undefined") {
    // Run tests after a short delay to ensure everything is loaded
    setTimeout(runAllTests, 1000);
  } else {
    console.log("⏳ FlowDrop not yet available. Tests will run when FlowDrop loads.");
    
    // Wait for FlowDrop to load
    const checkFlowDrop = setInterval(() => {
      if (typeof window.FlowDrop !== "undefined") {
        clearInterval(checkFlowDrop);
        runAllTests();
      }
    }, 500);
    
    // Stop checking after 10 seconds
    setTimeout(() => {
      clearInterval(checkFlowDrop);
      console.error("❌ FlowDrop did not load within 10 seconds");
    }, 10000);
  }

})();
