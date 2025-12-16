<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop_node_type\Kernel;

use Drupal\flowdrop_node_type\Entity\FlowDropNodeType;
use Drupal\flowdrop_node_type\FlowDropNodeTypeInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the FlowDropNodeType entity creation and storage.
 *
 * @group flowdrop_node_type
 * @coversDefaultClass \Drupal\flowdrop_node_type\Entity\FlowDropNodeType
 */
class FlowDropNodeTypeKernelTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    "system",
    "user",
    'entity_test',
    'flowdrop_node_category',
    'flowdrop_node_type',
    'flowdrop',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(["system"]);

    $this->installEntitySchema('flowdrop_node_category');
    $this->installEntitySchema('flowdrop_node_type');

    // Disable schema checking for tests to avoid validation errors.
    $this->config('system.logging')->set('error_level', 'hide')->save();
  }

  /**
   * Tests entity property setters and getters.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setCategory
   * @covers ::setIcon
   * @covers ::setColor
   * @covers ::setVersion
   * @covers ::setEnabled
   * @covers ::setConfig
   * @covers ::setTags
   * @covers ::setExecutorPlugin
   */
  public function testEntityPropertySettersAndGetters(): void {
    // Create a real entity using the entity storage system.
    $entity = $this->createTestEntity();

    // Test all setters and getters.
    $entity->setLabel("Updated Label");
    $this->assertEquals("Updated Label", $entity->getLabel());

    $entity->setDescription("Updated Description");
    $this->assertEquals("Updated Description", $entity->getDescription());

    $entity->setCategory("input");
    $this->assertEquals("input", $entity->getCategory());

    $entity->setIcon("mdi:input");
    $this->assertEquals("mdi:input", $entity->getIcon());

    $entity->setColor("#ff0000");
    $this->assertEquals("#ff0000", $entity->getColor());

    $entity->setVersion("2.0.0");
    $this->assertEquals("2.0.0", $entity->getVersion());

    $entity->setEnabled(FALSE);
    $this->assertFalse($entity->isEnabled());

    $config = ["test" => "config"];
    $entity->setConfig($config);
    $this->assertEquals($config, $entity->getConfig());

    $tags = ["test", "tags"];
    $entity->setTags($tags);
    $this->assertEquals($tags, $entity->getTags());

    $entity->setExecutorPlugin("test_plugin");
    $this->assertEquals("test_plugin", $entity->getExecutorPlugin());
  }

  /**
   * Tests entity property type handling.
   *
   * @covers ::setConfig
   * @covers ::setTags
   */
  public function testEntityPropertyTypeHandling(): void {
    $entity = $this->createTestEntity();

    // Test with different data types.
    $complex_config = [
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

    $entity->setConfig($complex_config);
    $this->assertEquals($complex_config, $entity->getConfig());

    $mixed_tags = ["string", 123, TRUE, NULL];
    $entity->setTags($mixed_tags);
    $this->assertEquals($mixed_tags, $entity->getTags());
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
    $this->assertEquals("processing", $entity->getCategory());
    $this->assertEquals("mdi:cog", $entity->getIcon());
    $this->assertEquals("#007cba", $entity->getColor());
    $this->assertEquals("1.0.0", $entity->getVersion());
    $this->assertTrue($entity->isEnabled());
    $this->assertEquals([], $entity->getConfig());
    $this->assertEquals([], $entity->getTags());
    $this->assertEquals("", $entity->getExecutorPlugin());
  }

  /**
   * Tests entity property validation.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setCategory
   * @covers ::setIcon
   * @covers ::setColor
   * @covers ::setVersion
   * @covers ::setEnabled
   * @covers ::setConfig
   * @covers ::setTags
   * @covers ::setExecutorPlugin
   */
  public function testEntityPropertyValidation(): void {
    $entity = $this->createTestEntity();

    // Test that properties can handle various input types.
    $entity->setLabel("Test Label");
    $this->assertEquals("Test Label", $entity->getLabel());

    $entity->setDescription("Test Description");
    $this->assertEquals("Test Description", $entity->getDescription());

    $entity->setCategory("input");
    $this->assertEquals("input", $entity->getCategory());

    $entity->setIcon("mdi:test");
    $this->assertEquals("mdi:test", $entity->getIcon());

    $entity->setColor("#123456");
    $this->assertEquals("#123456", $entity->getColor());

    $entity->setVersion("2.1.0");
    $this->assertEquals("2.1.0", $entity->getVersion());

    $entity->setEnabled(FALSE);
    $this->assertFalse($entity->isEnabled());

    $entity->setEnabled(TRUE);
    $this->assertTrue($entity->isEnabled());

    $entity->setConfig(["test" => "value"]);
    $this->assertEquals(["test" => "value"], $entity->getConfig());

    $entity->setTags(["tag1", "tag2"]);
    $this->assertEquals(["tag1", "tag2"], $entity->getTags());

    $entity->setExecutorPlugin("test_executor");
    $this->assertEquals("test_executor", $entity->getExecutorPlugin());
  }

  /**
   * Tests entity property chaining.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setCategory
   * @covers ::setIcon
   * @covers ::setColor
   * @covers ::setVersion
   * @covers ::setEnabled
   * @covers ::setConfig
   * @covers ::setTags
   * @covers ::setExecutorPlugin
   */
  public function testEntityPropertyChaining(): void {
    $entity = $this->createTestEntity();

    // Test that setters return the entity instance for chaining.
    $result = $entity->setLabel("Chained Label");
    $this->assertSame($entity, $result);

    $result = $entity->setDescription("Chained Description");
    $this->assertSame($entity, $result);

    $result = $entity->setCategory("output");
    $this->assertSame($entity, $result);

    $result = $entity->setIcon("mdi:output");
    $this->assertSame($entity, $result);

    $result = $entity->setColor("#00ff00");
    $this->assertSame($entity, $result);

    $result = $entity->setVersion("3.0.0");
    $this->assertSame($entity, $result);

    $result = $entity->setEnabled(FALSE);
    $this->assertSame($entity, $result);

    $result = $entity->setConfig(["chained" => "config"]);
    $this->assertSame($entity, $result);

    $result = $entity->setTags(["chained", "tags"]);
    $this->assertSame($entity, $result);

    $result = $entity->setExecutorPlugin("chained_executor");
    $this->assertSame($entity, $result);
  }

  /**
   * Tests entity property edge cases.
   *
   * @covers ::setLabel
   * @covers ::setDescription
   * @covers ::setCategory
   * @covers ::setIcon
   * @covers ::setColor
   * @covers ::setVersion
   * @covers ::setEnabled
   * @covers ::setConfig
   * @covers ::setTags
   * @covers ::setExecutorPlugin
   */
  public function testEntityPropertyEdgeCases(): void {
    $entity = $this->createTestEntity();

    // Test empty strings.
    $entity->setLabel("");
    $this->assertEquals("", $entity->getLabel());

    $entity->setDescription("");
    $this->assertEquals("", $entity->getDescription());

    $entity->setCategory("");
    $this->assertEquals("", $entity->getCategory());

    $entity->setIcon("");
    $this->assertEquals("", $entity->getIcon());

    $entity->setColor("");
    $this->assertEquals("", $entity->getColor());

    $entity->setVersion("");
    $this->assertEquals("", $entity->getVersion());

    $entity->setExecutorPlugin("");
    $this->assertEquals("", $entity->getExecutorPlugin());

    // Test empty arrays.
    $entity->setConfig([]);
    $this->assertEquals([], $entity->getConfig());

    $entity->setTags([]);
    $this->assertEquals([], $entity->getTags());
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
    $loaded_entity = FlowDropNodeType::load("test_entity");
    $this->assertInstanceOf(FlowDropNodeType::class, $loaded_entity);
    $this->assertEquals("test_entity", $loaded_entity->id());
    $this->assertEquals("Test Entity", $loaded_entity->label());
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
    $entity->setLabel("Updated Label");
    $entity->setDescription("Updated description");
    $entity->setCategory("input");
    $entity->setEnabled(FALSE);
    $entity->setConfig([]);
    $entity->setTags(["updated", "tags"]);
    $entity->setExecutorPlugin("updated_executor");

    $entity->save();

    // Load and verify updates.
    $updated_entity = FlowDropNodeType::load("test_entity");
    $this->assertEquals("Updated Label", $updated_entity->label());
    $this->assertEquals("Updated description", $updated_entity->getDescription());
    $this->assertEquals("input", $updated_entity->getCategory());
    $this->assertFalse($updated_entity->isEnabled());
    $this->assertEquals([], $updated_entity->getConfig());
    $this->assertEquals(["updated", "tags"], $updated_entity->getTags());
    $this->assertEquals("updated_executor", $updated_entity->getExecutorPlugin());
  }

  /**
   * Tests entity deletion.
   *
   * @covers ::delete
   */
  public function testEntityDeletion(): void {
    $entity = $this->createTestEntity();
    $entity->save();
    $this->assertInstanceOf(FlowDropNodeType::class, FlowDropNodeType::load("test_entity"));

    $entity->delete();
    $this->assertNull(FlowDropNodeType::load("test_entity"));
  }

  /**
   * Tests the toNodeDefinition method.
   *
   * @covers ::toNodeDefinition
   */
  public function testToNodeDefinition(): void {
    $entity = $this->createTestEntity();

    // Set up the entity with test data.
    $entity->setLabel("Definition Test Node");
    $entity->setDescription("Test node definition");
    $entity->setCategory("processing");
    $entity->setIcon("mdi:cog");
    $entity->setColor("#007cba");
    $entity->setVersion("1.0.0");
    $entity->setEnabled(TRUE);
    $entity->setConfig([]);
    $entity->setTags(["definition", "test"]);
    $entity->setExecutorPlugin("test_executor");

    // Save the entity to ensure it's properly initialized.
    $entity->save();

    $definition = $entity->toNodeDefinition();

    $this->assertEquals("test_entity", $definition["id"]);
    $this->assertEquals("Definition Test Node", $definition["name"]);
    $this->assertEquals("Test node definition", $definition["description"]);
    $this->assertEquals("processing", $definition["category"]);
    $this->assertEquals("mdi:cog", $definition["icon"]);
    $this->assertEquals("#007cba", $definition["color"]);
    $this->assertEquals("1.0.0", $definition["version"]);
    $this->assertTrue($definition["enabled"]);
    $this->assertEquals([], $definition["configSchema"]);
    $this->assertEquals(["definition", "test"], $definition["tags"]);
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
    $original_entity->setLabel("Config Test Node");
    $original_entity->setDescription("Testing config export/import");
    $original_entity->setCategory("output");
    $original_entity->setIcon("mdi:output");
    $original_entity->setColor("#ff6600");
    $original_entity->setVersion("3.0.0");
    $original_entity->setEnabled(FALSE);
    $original_entity->setConfig([]);
    $original_entity->setTags(["config", "export", "import"]);
    $original_entity->setExecutorPlugin("config_executor");

    // Export configuration.
    $exported_config = $original_entity->toArray();

    // Import configuration.
    $imported_entity = FlowDropNodeType::create($exported_config);

    // Verify imported entity matches original.
    $this->assertEquals("test_entity", $imported_entity->id());
    $this->assertEquals("Config Test Node", $imported_entity->label());
    $this->assertEquals("Testing config export/import", $imported_entity->getDescription());
    $this->assertEquals("output", $imported_entity->getCategory());
    $this->assertEquals("mdi:output", $imported_entity->getIcon());
    $this->assertEquals("#ff6600", $imported_entity->getColor());
    $this->assertEquals("3.0.0", $imported_entity->getVersion());
    $this->assertFalse($imported_entity->isEnabled());
    $this->assertEquals([], $imported_entity->getConfig());
    $this->assertEquals(["config", "export", "import"], $imported_entity->getTags());
    $this->assertEquals("config_executor", $imported_entity->getExecutorPlugin());
  }

  /**
   * Creates a test entity using the entity storage system.
   *
   * @return \Drupal\flowdrop_node_type\FlowDropNodeTypeInterface
   *   A test entity instance.
   */
  private function createTestEntity(): FlowDropNodeTypeInterface {
    $storage = $this->entityTypeManager->getStorage('flowdrop_node_type');
    $entity = $storage->create([
      'id' => 'test_entity',
      'label' => 'Test Entity',
    ]);
    if (!$entity instanceof FlowDropNodeTypeInterface) {
      throw new \RuntimeException('Failed to create "FlowDrop Node Type" test entity.');
    }
    return $entity;
  }

}
