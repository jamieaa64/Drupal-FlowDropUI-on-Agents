<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_workflow\Kernel;

use Drupal\flowdrop_workflow\Entity\FlowDropWorkflow;
use Drupal\flowdrop_workflow\FlowDropWorkflowInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the FlowDropWorkflow entity creation and storage.
 *
 * @group flowdrop_workflow
 * @coversDefaultClass \Drupal\flowdrop_workflow\Entity\FlowDropWorkflow
 */
class FlowDropWorkflowKernelTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    "system",
    "user",
    "entity_test",
    "flowdrop_node_category",
    "flowdrop_node_type",
    "flowdrop_workflow",
    "flowdrop",
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(["system"]);

    $this->installEntitySchema('flowdrop_node_category');
    $this->installEntitySchema('flowdrop_node_type');
    $this->installEntitySchema('flowdrop_workflow');

    // Disable schema checking for tests to avoid validation errors.
    $this->config('system.logging')->set('error_level', 'hide')->save();
  }

  /**
   * Tests entity property setters and getters.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setNodes
   * @covers ::setEdges
   * @covers ::setMetadata
   * @covers ::setCreated
   * @covers ::setChanged
   * @covers ::setUid
   */
  public function testEntityPropertySettersAndGetters(): void {
    // Create a real entity using the entity storage system.
    $entity = $this->createTestEntity();

    // Test all setters and getters.
    $entity->setLabel("Updated Workflow Label");
    $this->assertEquals("Updated Workflow Label", $entity->getLabel());

    $entity->setDescription("Updated workflow description");
    $this->assertEquals("Updated workflow description", $entity->getDescription());

    $nodes = [
      "node1" => [
        "id" => "node1",
        "type" => "input",
        "position" => ["x" => 100, "y" => 100],
      ],
      "node2" => [
        "id" => "node2",
        "type" => "output",
        "position" => ["x" => 200, "y" => 200],
      ],
    ];
    $entity->setNodes($nodes);
    $this->assertEquals($nodes, $entity->getNodes());

    $edges = [
      "edge1" => [
        "id" => "edge1",
        "source" => "node1",
        "target" => "node2",
        "type" => "default",
      ],
    ];
    $entity->setEdges($edges);
    $this->assertEquals($edges, $entity->getEdges());

    $metadata = [
      "version" => "1.0.0",
      "author" => "Test User",
      "tags" => ["test", "workflow"],
    ];
    $entity->setMetadata($metadata);
    $this->assertEquals($metadata, $entity->getMetadata());

    $created = time();
    $entity->setCreated($created);
    $this->assertEquals($created, $entity->getCreated());

    $changed = time() + 3600;
    $entity->setChanged($changed);
    $this->assertEquals($changed, $entity->getChanged());

    $uid = 1;
    $entity->setUid($uid);
    $this->assertEquals($uid, $entity->getUid());
  }

  /**
   * Tests entity property type handling.
   *
   * @covers ::setNodes
   * @covers ::setEdges
   * @covers ::setMetadata
   */
  public function testEntityPropertyTypeHandling(): void {
    $entity = $this->createTestEntity();

    // Test with different data types.
    $complex_nodes = [
      "string" => "value",
      "number" => 42,
      "boolean" => TRUE,
      "null" => NULL,
      "array" => [1, 2, 3],
      "nested" => [
        "key" => "value",
        "numbers" => [1, 2, 3],
      ],
    ];

    $entity->setNodes($complex_nodes);
    $this->assertEquals($complex_nodes, $entity->getNodes());

    $complex_edges = [
      "edge1" => [
        "source" => "node1",
        "target" => "node2",
        "data" => ["weight" => 1.5, "enabled" => TRUE],
      ],
    ];

    $entity->setEdges($complex_edges);
    $this->assertEquals($complex_edges, $entity->getEdges());

    $complex_metadata = [
      "string" => "value",
      "number" => 42,
      "boolean" => TRUE,
      "null" => NULL,
      "array" => [1, 2, 3],
      "nested" => [
        "key" => "value",
        "numbers" => [1, 2, 3],
      ],
    ];

    $entity->setMetadata($complex_metadata);
    $this->assertEquals($complex_metadata, $entity->getMetadata());
  }

  /**
   * Tests default values are set correctly.
   *
   * @covers ::__construct
   */
  public function testDefaultValues(): void {
    $entity = $this->createTestEntity();

    // Test default values.
    $this->assertEquals("", $entity->getDescription());
    $this->assertEquals([], $entity->getNodes());
    $this->assertEquals([], $entity->getEdges());
    $this->assertEquals([], $entity->getMetadata());
    $this->assertEquals(0, $entity->getCreated());
    $this->assertEquals(0, $entity->getChanged());
    $this->assertEquals(0, $entity->getUid());
  }

  /**
   * Tests entity property validation.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setNodes
   * @covers ::setEdges
   * @covers ::setMetadata
   * @covers ::setCreated
   * @covers ::setChanged
   * @covers ::setUid
   */
  public function testEntityPropertyValidation(): void {
    $entity = $this->createTestEntity();

    // Test that properties can handle various input types.
    $entity->setLabel("Test Workflow Label");
    $this->assertEquals("Test Workflow Label", $entity->getLabel());

    $entity->setDescription("Test workflow description");
    $this->assertEquals("Test workflow description", $entity->getDescription());

    $entity->setNodes(["test" => "node"]);
    $this->assertEquals(["test" => "node"], $entity->getNodes());

    $entity->setEdges(["test" => "edge"]);
    $this->assertEquals(["test" => "edge"], $entity->getEdges());

    $entity->setMetadata(["test" => "metadata"]);
    $this->assertEquals(["test" => "metadata"], $entity->getMetadata());

    $entity->setCreated(1234567890);
    $this->assertEquals(1234567890, $entity->getCreated());

    $entity->setChanged(1234567890);
    $this->assertEquals(1234567890, $entity->getChanged());

    $entity->setUid(42);
    $this->assertEquals(42, $entity->getUid());
  }

  /**
   * Tests entity property chaining.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setNodes
   * @covers ::setEdges
   * @covers ::setMetadata
   * @covers ::setCreated
   * @covers ::setChanged
   * @covers ::setUid
   */
  public function testEntityPropertyChaining(): void {
    $entity = $this->createTestEntity();

    // Test that setters return the entity instance for chaining.
    $result = $entity->setLabel("Chained Workflow Label");
    $this->assertSame($entity, $result);

    $result = $entity->setDescription("Chained workflow description");
    $this->assertSame($entity, $result);

    $result = $entity->setNodes(["chained" => "nodes"]);
    $this->assertSame($entity, $result);

    $result = $entity->setEdges(["chained" => "edges"]);
    $this->assertSame($entity, $result);

    $result = $entity->setMetadata(["chained" => "metadata"]);
    $this->assertSame($entity, $result);

    $result = $entity->setCreated(1234567890);
    $this->assertSame($entity, $result);

    $result = $entity->setChanged(1234567890);
    $this->assertSame($entity, $result);

    $result = $entity->setUid(42);
    $this->assertSame($entity, $result);
  }

  /**
   * Tests entity property edge cases.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setNodes
   * @covers ::setEdges
   * @covers ::setMetadata
   * @covers ::setCreated
   * @covers ::setChanged
   * @covers ::setUid
   */
  public function testEntityPropertyEdgeCases(): void {
    $entity = $this->createTestEntity();

    // Test empty strings.
    $entity->setLabel("");
    $this->assertEquals("", $entity->getLabel());

    $entity->setDescription("");
    $this->assertEquals("", $entity->getDescription());

    // Test empty arrays.
    $entity->setNodes([]);
    $this->assertEquals([], $entity->getNodes());

    $entity->setEdges([]);
    $this->assertEquals([], $entity->getEdges());

    $entity->setMetadata([]);
    $this->assertEquals([], $entity->getMetadata());

    // Test zero values.
    $entity->setCreated(0);
    $this->assertEquals(0, $entity->getCreated());

    $entity->setChanged(0);
    $this->assertEquals(0, $entity->getChanged());

    $entity->setUid(0);
    $this->assertEquals(0, $entity->getUid());
  }

  /**
   * Tests entity creation and storage.
   *
   * @covers ::save
   * @covers ::load
   */
  public function testEntityCreationAndStorage(): void {
    $entity = $this->createTestEntity();

    // Save the entity.
    $entity->save();
    $this->assertNotEmpty($entity->uuid());

    // Load the entity from storage.
    $loaded_entity = FlowDropWorkflow::load("test_workflow");
    $this->assertInstanceOf(FlowDropWorkflow::class, $loaded_entity);
    $this->assertEquals("test_workflow", $loaded_entity->id());
    $this->assertEquals("Test Workflow", $loaded_entity->label());
  }

  /**
   * Tests entity update functionality.
   *
   * @covers ::save
   */
  public function testEntityUpdate(): void {
    $entity = $this->createTestEntity();
    $entity->save();

    // Update the entity.
    $entity->setLabel("Updated Workflow Label");
    $entity->setDescription("Updated workflow description");
    $entity->setNodes([]);
    $entity->setEdges([]);
    $entity->setMetadata(["updated" => "metadata"]);
    $entity->setUid(42);

    $entity->save();

    // Load and verify updates.
    $updated_entity = FlowDropWorkflow::load("test_workflow");
    $this->assertEquals("Updated Workflow Label", $updated_entity->label());
    $this->assertEquals("Updated workflow description", $updated_entity->getDescription());
    $this->assertEquals([], $updated_entity->getNodes());
    $this->assertEquals([], $updated_entity->getEdges());
    $this->assertEquals(["updated" => "metadata"], $updated_entity->getMetadata());
    $this->assertEquals(42, $updated_entity->getUid());
  }

  /**
   * Tests entity deletion.
   *
   * @covers ::delete
   */
  public function testEntityDeletion(): void {
    $entity = $this->createTestEntity();
    $entity->save();
    $this->assertInstanceOf(FlowDropWorkflow::class, FlowDropWorkflow::load("test_workflow"));

    $entity->delete();
    $this->assertNull(FlowDropWorkflow::load("test_workflow"));
  }

  /**
   * Tests entity timestamp handling.
   *
   * @covers ::preSave
   */
  public function testEntityTimestampHandling(): void {
    $entity = $this->createTestEntity();

    // Test that timestamps are set on save.
    $before_save = time();
    $entity->save();
    $after_save = time();

    $this->assertGreaterThanOrEqual($before_save, $entity->getCreated());
    $this->assertLessThanOrEqual($after_save, $entity->getCreated());
    $this->assertGreaterThanOrEqual($before_save, $entity->getChanged());
    $this->assertLessThanOrEqual($after_save, $entity->getChanged());

    // Test that changed timestamp is updated on subsequent saves.
    $original_changed = $entity->getChanged();
    // Ensure time difference.
    sleep(1);
    $entity->setLabel("Updated Label");
    $entity->save();

    $this->assertGreaterThan($original_changed, $entity->getChanged());
  }

  /**
   * Tests workflow structure with nodes and edges.
   *
   * @covers ::setNodes
   * @covers ::setEdges
   * @covers ::getNodes
   * @covers ::getEdges
   */
  public function testWorkflowStructure(): void {
    $entity = $this->createTestEntity();

    // Create a simple workflow structure with minimal data.
    $nodes = [
      "start" => [
        "id" => "start",
        "type" => "input",
      ],
      "process" => [
        "id" => "process",
        "type" => "processing",
      ],
      "end" => [
        "id" => "end",
        "type" => "output",
      ],
    ];

    $edges = [
      "edge1" => [
        "id" => "edge1",
        "source" => "start",
        "target" => "process",
      ],
      "edge2" => [
        "id" => "edge2",
        "source" => "process",
        "target" => "end",
      ],
    ];

    $entity->setNodes($nodes);
    $entity->setEdges($edges);

    $this->assertEquals($nodes, $entity->getNodes());
    $this->assertEquals($edges, $entity->getEdges());

    // Test that the structure is preserved after save/load.
    $entity->save();
    $loaded_entity = FlowDropWorkflow::load("test_workflow");
    $this->assertEquals($nodes, $loaded_entity->getNodes());
    $this->assertEquals($edges, $loaded_entity->getEdges());
  }

  /**
   * Tests configuration export and import.
   *
   * @covers ::toArray
   * @covers ::create
   */
  public function testConfigurationExportAndImport(): void {
    $original_entity = $this->createTestEntity();

    // Set up the entity with test data.
    $original_entity->setLabel("Config Test Workflow");
    $original_entity->setDescription("Testing config export/import");
    $original_entity->setNodes(["test" => "nodes"]);
    $original_entity->setEdges(["test" => "edges"]);
    $original_entity->setMetadata(["test" => "metadata"]);
    $original_entity->setUid(42);

    // Export configuration.
    $exported_config = $original_entity->toArray();

    // Import configuration.
    $imported_entity = FlowDropWorkflow::create($exported_config);

    // Verify imported entity matches original.
    $this->assertEquals("test_workflow", $imported_entity->id());
    $this->assertEquals("Config Test Workflow", $imported_entity->label());
    $this->assertEquals("Testing config export/import", $imported_entity->getDescription());
    $this->assertEquals(["test" => "nodes"], $imported_entity->getNodes());
    $this->assertEquals(["test" => "edges"], $imported_entity->getEdges());
    $this->assertEquals(["test" => "metadata"], $imported_entity->getMetadata());
    $this->assertEquals(42, $imported_entity->getUid());
  }

  /**
   * Creates a test entity using the entity storage system.
   *
   * @return \Drupal\flowdrop_workflow\FlowDropWorkflowInterface
   *   A test entity instance.
   */
  private function createTestEntity(): FlowDropWorkflowInterface {
    $storage = $this->entityTypeManager->getStorage('flowdrop_workflow');
    $entity = $storage->create([
      'id' => 'test_workflow',
      'label' => 'Test Workflow',
    ]);
    if (!$entity instanceof FlowDropWorkflowInterface) {
      throw new \RuntimeException('Failed to create "FlowDrop Workflow" test entity.');
    }
    return $entity;
  }

}
