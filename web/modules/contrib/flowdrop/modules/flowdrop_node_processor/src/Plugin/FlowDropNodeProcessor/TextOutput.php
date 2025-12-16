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
 * Executor for Text Output nodes.
 */
#[FlowDropNodeProcessor(
  id: "text_output",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Text Output"),
  type: "simple",
  supportedTypes: ["square", "simple", "default"],
  category: "output",
  description: "Text output formatting",
  version: "1.0.0",
  tags: ["text", "output", "display"]
)]
class TextOutput extends AbstractFlowDropNodeProcessor {

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
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    // Text output nodes simply pass through the input text.
    $text = '';

    if (!empty($inputs->get('text'))) {
      $text = $inputs->get('text');
    }
    elseif (!$inputs->isEmpty()) {
      // If no 'text' input, use the first input.
      $input_array = $inputs->toArray();
      $text = reset($input_array);
    }

    // Convert array to string if necessary.
    if (is_array($text)) {
      $text = json_encode($text);
    }

    // Ensure text is a string.
    $text = (string) $text;

    // Apply max length if configured.
    $max_length = $config->getConfig('maxLength', 1000);
    if (strlen($text) > $max_length) {
      $text = substr($text, 0, $max_length) . '...';
    }

    $this->getLogger()->info('Text output executed successfully', [
      'output_length' => strlen($text),
      'max_length' => $max_length,
      'was_truncated' => strlen($text) > $max_length,
    ]);

    return [
      'output' => $text,
      'length' => strlen($text),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Text output nodes can accept any inputs.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(): array {
    return [
      'category' => 'Output',
      'icon' => 'text-output',
      'color' => '#dc3545',
      'version' => '1.0.0',
      'author' => 'FlowDrop Team',
      'description' => 'Text output node for displaying formatted text',
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
        'maxLength' => [
          'type' => 'integer',
          'title' => 'Maximum Length',
          'description' => 'Maximum length of output text',
          'default' => 1000,
          'minimum' => 1,
          'maximum' => 10000,
        ],
        'format' => [
          'type' => 'string',
          'title' => 'Text Format',
          'description' => 'Text formatting option',
          'default' => 'plain',
          'enum' => ['plain', 'html', 'markdown'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'text' => [
          'type' => 'string',
          'title' => 'Text Input',
          'description' => 'The text to output',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
