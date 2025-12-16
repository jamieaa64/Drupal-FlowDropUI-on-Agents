<?php

declare(strict_types=1);

namespace Drupal\Tests\flowdrop\Kernel;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager;
use Drupal\flowdrop\DTO\Input;
use Drupal\flowdrop\DTO\Config;
use Drupal\flowdrop\DTO\OutputInterface;

/**
 * Kernel tests for FlowDrop node processors.
 *
 * @group flowdrop
 */
class FlowDropNodeProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'flowdrop',
    'flowdrop_node_category',
    'flowdrop_node_type',
    'flowdrop_node_processor',
    'flowdrop_node_test',
  ];

  /**
   * The plugin manager.
   *
   * @var \Drupal\flowdrop\Service\FlowDropNodeProcessorPluginManager
   */
  protected FlowDropNodeProcessorPluginManager $pluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginManager = $this->container->get('flowdrop.node_processor_plugin_manager');
  }

  /**
   * Test that all test processors are discovered.
   */
  public function testProcessorDiscovery(): void {
    $definitions = $this->pluginManager->getDefinitions();

    $test_processors = [
      'start_node_processor' => 'Start Node Processor',
      'log_node_processor' => 'Log Node Processor',
      'transform_node_processor' => 'Transform Node Processor',
    ];

    foreach ($test_processors as $id => $expected_label) {
      $this->assertArrayHasKey($id, $definitions, "Processor {$id} should be discovered");
      $this->assertEquals($expected_label, $definitions[$id]['label'], "Processor {$id} should have correct label");
      $this->assertEquals('testing', $definitions[$id]['category'], "Processor {$id} should be in testing category");
    }
  }

  /**
   * Test the Start Node Processor.
   */
  public function testStartNodeProcessor(): void {
    $plugin = $this->pluginManager->createInstance('start_node_processor', [
      'message' => 'Hello from test!',
    ]);

    $inputs = new Input();
    $config = new Config();
    $config->fromArray(['message' => 'Hello from test!']);

    $output = $plugin->execute($inputs, $config);

    $this->assertInstanceOf(OutputInterface::class, $output);
    $this->assertEquals('Hello from test!', $output->get('data'));
    $this->assertIsInt($output->get('timestamp'));
    $this->assertEquals('start_node_processor', $output->get('source'));
  }

  /**
   * Test the Log Node Processor.
   */
  public function testLogNodeProcessor(): void {
    $plugin = $this->pluginManager->createInstance('log_node_processor', [
      'log_level' => 'info',
      'include_timestamp' => TRUE,
    ]);

    $inputs = new Input();
    $inputs->set('data', 'Test message from Log Node Processor');

    $config = new Config();
    $config->fromArray([
      'log_level' => 'info',
      'include_timestamp' => TRUE,
    ]);

    $output = $plugin->execute($inputs, $config);

    $this->assertInstanceOf(OutputInterface::class, $output);
    $this->assertEquals('Test message from Log Node Processor', $output->get('data'));
    $this->assertTrue($output->get('logged'));
    $this->assertEquals('info', $output->get('log_level'));
    $this->assertIsInt($output->get('timestamp'));
  }

  /**
   * Test the Transform Node Processor with different transformations.
   */
  public function testTransformNodeProcessor(): void {
    $plugin = $this->pluginManager->createInstance('transform_node_processor', [
      'transformation_type' => 'uppercase',
      'prefix' => 'TRANSFORMED: ',
      'suffix' => ' (processed)',
    ]);

    $inputs = new Input();
    $inputs->set('data', 'hello world');

    $config = new Config();
    $config->fromArray([
      'transformation_type' => 'uppercase',
      'prefix' => 'TRANSFORMED: ',
      'suffix' => ' (processed)',
    ]);

    $output = $plugin->execute($inputs, $config);

    $this->assertInstanceOf(OutputInterface::class, $output);
    $this->assertEquals('hello world', $output->get('original_data'));
    $this->assertEquals('TRANSFORMED: HELLO WORLD (processed)', $output->get('transformed_data'));
    $this->assertEquals('uppercase', $output->get('transformation_applied'));
    $this->assertIsInt($output->get('timestamp'));
  }

  /**
   * Test Transform Node Processor with different transformation types.
   */
  public function testTransformNodeProcessorDifferentTypes(): void {
    $test_cases = [
      'lowercase' => ['input' => 'HELLO WORLD', 'expected' => 'hello world'],
      'reverse' => ['input' => 'hello', 'expected' => 'olleh'],
      'capitalize' => ['input' => 'hello world', 'expected' => 'Hello World'],
      'trim' => ['input' => '  hello world  ', 'expected' => 'hello world'],
    ];

    foreach ($test_cases as $transformation_type => $test_case) {
      $plugin = $this->pluginManager->createInstance('transform_node_processor', [
        'transformation_type' => $transformation_type,
        'prefix' => '',
        'suffix' => '',
      ]);

      $inputs = new Input();
      $inputs->set('data', $test_case['input']);

      $config = new Config();
      $config->fromArray([
        'transformation_type' => $transformation_type,
        'prefix' => '',
        'suffix' => '',
      ]);

      $output = $plugin->execute($inputs, $config);

      $this->assertEquals($test_case['expected'], $output->get('transformed_data'),
        "Transformation '{$transformation_type}' should work correctly");
      $this->assertEquals($transformation_type, $output->get('transformation_applied'));
    }
  }

  /**
   * Test input validation for processors that require inputs.
   */
  public function testInputValidation(): void {
    // Test Log Node Processor with missing input.
    $plugin = $this->pluginManager->createInstance('log_node_processor');
    // Empty inputs.
    $inputs = new Input();
    $config = new Config();
    $config->fromArray([]);

    // Should not throw exception but return false for validation.
    $this->assertFalse($plugin->validateInputs($inputs->toArray()));

    // Test with valid input.
    $inputs->set('data', 'Valid input');
    $this->assertTrue($plugin->validateInputs($inputs->toArray()));

    // Test Transform Node Processor with missing input.
    $plugin = $this->pluginManager->createInstance('transform_node_processor');
    // Empty inputs.
    $inputs = new Input();
    $this->assertFalse($plugin->validateInputs($inputs->toArray()));

    // Test with valid input.
    $inputs->set('data', 'Valid input');
    $this->assertTrue($plugin->validateInputs($inputs->toArray()));
  }

  /**
   * Test processor configuration handling.
   */
  public function testProcessorConfiguration(): void {
    // Test Start Node Processor with custom configuration.
    $plugin = $this->pluginManager->createInstance('start_node_processor', [
      'message' => 'Custom message',
    ]);

    $inputs = new Input();
    $config = new Config();
    $config->fromArray(['message' => 'Custom message']);

    $output = $plugin->execute($inputs, $config);
    $this->assertEquals('Custom message', $output->get('data'));

    // Test Log Node Processor with different log levels.
    $log_levels = ['debug', 'info', 'warning', 'error'];
    foreach ($log_levels as $log_level) {
      $plugin = $this->pluginManager->createInstance('log_node_processor', [
        'log_level' => $log_level,
      ]);

      $inputs = new Input();
      $inputs->set('data', 'Test message');

      $config = new Config();
      $config->fromArray(['log_level' => $log_level]);

      $output = $plugin->execute($inputs, $config);
      $this->assertEquals($log_level, $output->get('log_level'));
    }
  }

  /**
   * Test processor error handling.
   */
  public function testProcessorErrorHandling(): void {
    // Test with non-existent processor.
    $this->expectException(PluginNotFoundException::class);
    $this->pluginManager->createInstance('non_existent_processor');
  }

  /**
   * Test processor metadata and schema.
   */
  public function testProcessorMetadata(): void {
    $definitions = $this->pluginManager->getDefinitions();

    $start_processor = $this->pluginManager->createInstance('start_node_processor');
    $log_processor = $this->pluginManager->createInstance('log_node_processor');
    $transform_processor = $this->pluginManager->createInstance('transform_node_processor');

    // Test metadata.
    $this->assertEquals('start', $start_processor->getType());
    $this->assertEquals('log', $log_processor->getType());
    $this->assertEquals('transform', $transform_processor->getType());

    $this->assertEquals('start_node_processor', $start_processor->getId());
    $this->assertEquals('log_node_processor', $log_processor->getId());
    $this->assertEquals('transform_node_processor', $transform_processor->getId());

    $this->assertEquals('testing', $start_processor->getCategory());
    $this->assertEquals('testing', $log_processor->getCategory());
    $this->assertEquals('testing', $transform_processor->getCategory());

    // Test schema methods.
    $this->assertIsArray($start_processor->getInputSchema());
    $this->assertIsArray($start_processor->getOutputSchema());
    $this->assertIsArray($start_processor->getConfigSchema());
  }

}
