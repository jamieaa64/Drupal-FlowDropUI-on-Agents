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
 * Executor for Split Text nodes.
 */
#[FlowDropNodeProcessor(
  id: "split_text",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Split Text"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Split text into chunks",
  version: "1.0.0",
  tags: ["text", "split", "processing"]
)]
class SplitText extends AbstractFlowDropNodeProcessor {

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
    $method = $config->getConfig('method', 'words');
    $chunkSize = $config->getConfig('chunkSize', 100);
    $separator = $config->getConfig('separator', ' ');

    // Get the input text.
    $text = $inputs->get('text') ?: '';

    $chunks = [];
    switch ($method) {
      case 'words':
        $words = explode($separator, $text);
        $chunks = array_chunk($words, $chunkSize);
        $chunks = array_map(fn($chunk) => implode($separator, $chunk), $chunks);
        break;

      case 'characters':
        $chunks = str_split($text, $chunkSize);
        break;

      case 'sentences':
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = array_chunk($sentences, $chunkSize);
        $chunks = array_map(fn($chunk) => implode('. ', $chunk) . '.', $chunks);
        break;

      case 'paragraphs':
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = array_chunk($paragraphs, $chunkSize);
        $chunks = array_map(fn($chunk) => implode("\n\n", $chunk), $chunks);
        break;
    }

    $this->getLogger()->info('Split text executed successfully', [
      'method' => $method,
      'chunk_size' => $chunkSize,
      'chunks_count' => count($chunks),
      'text_length' => strlen($text),
    ]);

    return [
      'chunks' => $chunks,
      'method' => $method,
      'chunk_size' => $chunkSize,
      'total_chunks' => count($chunks),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Split text nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'chunks' => [
          'type' => 'array',
          'description' => 'The text chunks',
        ],
        'method' => [
          'type' => 'string',
          'description' => 'The splitting method used',
        ],
        'chunk_size' => [
          'type' => 'integer',
          'description' => 'The size of each chunk',
        ],
        'total_chunks' => [
          'type' => 'integer',
          'description' => 'The total number of chunks',
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
        'method' => [
          'type' => 'string',
          'title' => 'Method',
          'description' => 'Splitting method to use',
          'default' => 'words',
          'enum' => ['words', 'characters', 'sentences', 'paragraphs'],
        ],
        'chunkSize' => [
          'type' => 'integer',
          'title' => 'Chunk Size',
          'description' => 'Size of each chunk',
          'default' => 100,
        ],
        'separator' => [
          'type' => 'string',
          'title' => 'Separator',
          'description' => 'Separator for word splitting',
          'default' => ' ',
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
          'title' => 'Text',
          'description' => 'The text to split',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
