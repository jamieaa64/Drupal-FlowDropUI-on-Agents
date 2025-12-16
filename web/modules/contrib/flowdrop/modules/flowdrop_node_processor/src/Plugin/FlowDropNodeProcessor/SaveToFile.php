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
 * Executor for Save to File nodes.
 */
#[FlowDropNodeProcessor(
  id: "save_to_file",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Save to File"),
  type: "default",
  supportedTypes: ["default"],
  category: "output",
  description: "Save data to file",
  version: "1.0.0",
  tags: ["file", "save", "output"]
)]
class SaveToFile extends AbstractFlowDropNodeProcessor {

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
    $filename = $config->getConfig('filename', 'output.txt');
    $format = $config->getConfig('format', 'text');
    $append = $config->getConfig('append', FALSE);
    $directory = $config->getConfig('directory', 'public://flowdrop/');

    // Get the content to save.
    $content = $inputs->get('content') ?: '';
    if ($format === 'json') {
      $content = json_encode($content, JSON_PRETTY_PRINT);
    }

    // Ensure directory exists.
    $directory = rtrim($directory, '/') . '/';
    if (!is_dir($directory)) {
      mkdir($directory, 0755, TRUE);
    }

    $filepath = $directory . $filename;
    $mode = $append ? 'a' : 'w';

    $success = FALSE;
    $bytes_written = 0;

    if ($handle = fopen($filepath, $mode)) {
      $bytes_written = fwrite($handle, $content);
      fclose($handle);
      $success = TRUE;
    }

    $this->getLogger()->info('Save to file executed successfully', [
      'filename' => $filename,
      'format' => $format,
      'bytes_written' => $bytes_written,
      'success' => $success,
    ]);

    return [
      'success' => $success,
      'filename' => $filename,
      'filepath' => $filepath,
      'bytes_written' => $bytes_written,
      'format' => $format,
      'append' => $append,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Save to file nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'success' => [
          'type' => 'boolean',
          'description' => 'Whether the file was saved successfully',
        ],
        'filename' => [
          'type' => 'string',
          'description' => 'The filename used',
        ],
        'filepath' => [
          'type' => 'string',
          'description' => 'The full file path',
        ],
        'bytes_written' => [
          'type' => 'integer',
          'description' => 'Number of bytes written',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The output format used',
        ],
        'append' => [
          'type' => 'boolean',
          'description' => 'Whether the file was appended to',
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
        'filename' => [
          'type' => 'string',
          'title' => 'Filename',
          'description' => 'The filename to save to',
          'default' => 'output.txt',
        ],
        'format' => [
          'type' => 'string',
          'title' => 'Format',
          'description' => 'Output format to use',
          'default' => 'text',
          'enum' => ['text', 'json'],
        ],
        'append' => [
          'type' => 'boolean',
          'title' => 'Append',
          'description' => 'Whether to append to existing file',
          'default' => FALSE,
        ],
        'directory' => [
          'type' => 'string',
          'title' => 'Directory',
          'description' => 'Directory to save file in',
          'default' => 'public://flowdrop/',
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
        'content' => [
          'type' => 'mixed',
          'title' => 'Content',
          'description' => 'The content to save to file',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
