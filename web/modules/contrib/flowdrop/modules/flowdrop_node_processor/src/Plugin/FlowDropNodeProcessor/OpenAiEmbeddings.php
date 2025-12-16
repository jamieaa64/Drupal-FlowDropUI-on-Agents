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
 * Executor for OpenAI Embeddings nodes.
 */
#[FlowDropNodeProcessor(
  id: "openai_embeddings",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("OpenAI Embeddings"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "OpenAI embeddings generation",
  version: "1.0.0",
  tags: ["ai", "openai", "embeddings"]
)]
class OpenAiEmbeddings extends AbstractFlowDropNodeProcessor {

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
    $model = $config->getConfig('model', 'text-embedding-ada-002');
    // Get the input text.
    $texts = $inputs->get('texts') ?: [];

    $embeddings = [];
    $usage = [
      'prompt_tokens' => 0,
      'total_tokens' => 0,
    ];

    foreach ($texts as $index => $text) {
      // Simulate OpenAI embeddings API call.
      $embedding = $this->generateEmbedding($text, $model);
      $embeddings[] = [
        'object' => 'embedding',
        'embedding' => $embedding,
        'index' => $index,
      ];

      // Rough estimation.
      $usage['prompt_tokens'] += strlen($text) / 4;
      $usage['total_tokens'] += strlen($text) / 4;
    }

    $this->getLogger()->info('OpenAI embeddings executed successfully', [
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
    ];
  }

  /**
   * Generate embedding for text.
   */
  private function generateEmbedding(string $text, string $model): array {
    // Simulate embedding generation
    // In real implementation, this would call OpenAI's embedding API.
    // Default for text-embedding-ada-002.
    $dimensions = 1536;
    $embedding = [];

    for ($i = 0; $i < $dimensions; $i++) {
      $embedding[] = (float) (rand(-1000, 1000) / 1000);
    }

    return $embedding;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // OpenAI embeddings nodes can accept any inputs or none.
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
          'description' => 'Token usage information',
        ],
        'texts_count' => [
          'type' => 'integer',
          'description' => 'Number of texts processed',
        ],
        'embedding_dimensions' => [
          'type' => 'integer',
          'description' => 'Number of dimensions in embeddings',
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
          'description' => 'OpenAI embedding model to use',
          'default' => 'text-embedding-ada-002',
        ],
        'apiKey' => [
          'type' => 'string',
          'title' => 'API Key',
          'description' => 'OpenAI API key',
          'default' => '',
        ],
        'maxTokens' => [
          'type' => 'integer',
          'title' => 'Max Tokens',
          'description' => 'Maximum tokens per request',
          'default' => 8191,
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
