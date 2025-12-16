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
 * Executor for Hugging Face Embeddings nodes.
 */
#[FlowDropNodeProcessor(
  id: "huggingface_embeddings",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Hugging Face Embeddings"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "Huggingface embeddings generation",
  version: "1.0.0",
  tags: ["ai", "huggingface", "embeddings"]
)]
class HuggingfaceEmbeddings extends AbstractFlowDropNodeProcessor {

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
    $model = $config->getConfig('model', 'sentence-transformers/all-MiniLM-L6-v2');
    $maxLength = $config->getConfig('maxLength', 512);
    $normalize = $config->getConfig('normalize', TRUE);

    // Get the input text.
    $texts = $inputs->get('texts') ?: [];

    $embeddings = [];
    $usage = [
      'requests' => count($texts),
      'tokens' => 0,
    ];

    foreach ($texts as $index => $text) {
      // Simulate Hugging Face embeddings API call.
      $embedding = $this->generateEmbedding($text, $model, $maxLength, $normalize);
      $embeddings[] = [
        'embedding' => $embedding,
        'index' => $index,
        'text' => $text,
      ];

      // Rough estimation.
      $usage['tokens'] += strlen($text) / 4;
    }

    $this->getLogger()->info('Hugging Face embeddings executed successfully', [
      'model' => $model,
      'texts_count' => count($texts),
      'embeddings_count' => count($embeddings),
    ]);

    return [
      'embeddings' => $embeddings,
      'model' => $model,
      'usage' => $usage,
      'texts_count' => count($texts),
      'embedding_dimensions' => count($embeddings[0]['embedding'] ?? []),
      'normalize' => $normalize,
    ];
  }

  /**
   * Generate embedding for text using Hugging Face model.
   */
  private function generateEmbedding(string $text, string $model, int $maxLength, bool $normalize): array {
    // Simulate embedding generation
    // In real implementation, this would call
    // Hugging Face's API or local model.
    // Default for all-MiniLM-L6-v2.
    $dimensions = 384;
    $embedding = [];

    for ($i = 0; $i < $dimensions; $i++) {
      $embedding[] = (float) (rand(-1000, 1000) / 1000);
    }

    // Normalize if requested.
    if ($normalize) {
      $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
      if ($magnitude > 0) {
        $embedding = array_map(fn($x) => $x / $magnitude, $embedding);
      }
    }

    return $embedding;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Hugging Face embeddings nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'embeddings' => [
          'type' => 'array',
          'description' => 'The generated embeddings',
        ],
        'model' => [
          'type' => 'string',
          'description' => 'The model used',
        ],
        'usage' => [
          'type' => 'object',
          'description' => 'Usage information',
        ],
        'texts_count' => [
          'type' => 'integer',
          'description' => 'Number of texts processed',
        ],
        'embedding_dimensions' => [
          'type' => 'integer',
          'description' => 'Number of dimensions in embeddings',
        ],
        'normalize' => [
          'type' => 'boolean',
          'description' => 'Whether embeddings were normalized',
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
        'model' => [
          'type' => 'string',
          'title' => 'Model',
          'description' => 'Hugging Face model to use for embeddings',
          'default' => 'sentence-transformers/all-MiniLM-L6-v2',
        ],
        'apiKey' => [
          'type' => 'string',
          'title' => 'API Key',
          'description' => 'Hugging Face API key',
          'default' => '',
        ],
        'maxLength' => [
          'type' => 'integer',
          'title' => 'Max Length',
          'description' => 'Maximum sequence length',
          'default' => 512,
        ],
        'normalize' => [
          'type' => 'boolean',
          'title' => 'Normalize',
          'description' => 'Whether to normalize embeddings',
          'default' => TRUE,
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
        'texts' => [
          'type' => 'array',
          'title' => 'Texts',
          'description' => 'Texts to generate embeddings for',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
