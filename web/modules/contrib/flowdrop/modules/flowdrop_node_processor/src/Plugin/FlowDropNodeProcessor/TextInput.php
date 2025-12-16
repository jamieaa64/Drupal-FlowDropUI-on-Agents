<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for Text Input nodes.
 *
 * This component provides a simple text input field for user data entry,
 * similar to Langflow's TextInput component.
 */
#[FlowDropNodeProcessor(
  id: "text_input",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Text Input"),
  type: "simple",
  supportedTypes: ["simple", "square", "default"],
  description: "User text input field",
  category: "inputs",
  version: "1.0.0",
  tags: ["input", "text", "user", "form"]
)]
class TextInput extends AbstractFlowDropNodeProcessor {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getLogger(): LoggerChannelInterface {
    return $this->loggerFactory->get('flowdrop_node_processor');
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Text input can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    // For text input nodes, we return the configured default value
    // or the first input if provided.
    $text = $config->getConfig('defaultValue', '');

    // Check if we have any input data.
    if (!$inputs->isEmpty()) {
      $inputData = $inputs->get('data');
      if ($inputData !== NULL) {
        $text = $inputData;
      }
    }

    $this->getLogger()->info('Text input executed successfully', [
      'input_length' => strlen($text),
      'has_inputs' => !$inputs->isEmpty(),
    ]);

    return [
      'text' => $text,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'text' => [
          'type' => 'string',
          'description' => 'The input text value',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'nodeType' => [
          'type' => 'select',
          'title' => 'Node Type',
          'description' => 'Choose the visual representation for this node',
          'default' => 'simple',
          'enum' => ['simple', 'square', 'default'],
          'enumNames' => [
            'Simple (compact layout)',
            'Square (square layout)',
            'Default',
          ],
        ],
        'placeholder' => [
          'type' => 'string',
          'title' => 'Placeholder',
          'description' => 'Placeholder text for the input field',
          'default' => 'Enter text...',
        ],
        'defaultValue' => [
          'type' => 'string',
          'title' => 'Default Value',
          'description' => 'Default text value',
          'default' => '',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    return [];
  }

}
