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
 * Executor for Chroma Vector Store nodes.
 */
#[FlowDropNodeProcessor(
  id: "chroma_vector_store",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Chroma Vector Store"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "Chroma vector store operations",
  version: "1.0.0",
  tags: ["ai", "vector", "store", "chroma"]
)]
class ChromaVectorStore extends AbstractFlowDropNodeProcessor {

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
    $operation = $config->getConfig('operation', 'add');
    $collectionName = $config->getConfig('collectionName', 'default');
    $embeddingModel = $config->getConfig('embeddingModel', 'text-embedding-ada-002');
    $distanceMetric = $config->getConfig('distanceMetric', 'cosine');

    // Get the input data.
    $data = $inputs->get('data') ?: [];

    $result = [];
    switch ($operation) {
      case 'add':
        $result = $this->addDocuments($data, $collectionName, $embeddingModel);
        break;

      case 'query':
        $result = $this->queryDocuments($data, $collectionName, $distanceMetric);
        break;

      case 'delete':
        $result = $this->deleteDocuments($data, $collectionName);
        break;

      case 'update':
        $result = $this->updateDocuments($data, $collectionName);
        break;
    }

    $this->getLogger()->info('Chroma vector store executed successfully', [
      'operation' => $operation,
      'collection' => $collectionName,
      'documents_count' => count($data),
    ]);

    return [
      'result' => $result,
      'operation' => $operation,
      'collection_name' => $collectionName,
      'embedding_model' => $embeddingModel,
      'distance_metric' => $distanceMetric,
      'documents_processed' => count($data),
    ];
  }

  /**
   * Add documents to the vector store.
   */
  private function addDocuments(array $data, string $collectionName, string $embeddingModel): array {
    // Simulate adding documents to Chroma.
    $addedIds = [];
    foreach ($data as $index => $document) {
      $addedIds[] = "doc_" . time() . "_" . $index;
    }

    return [
      'success' => TRUE,
      'added_ids' => $addedIds,
      'collection' => $collectionName,
      'embedding_model' => $embeddingModel,
    ];
  }

  /**
   * Query documents from the vector store.
   */
  private function queryDocuments(array $data, string $collectionName, string $distanceMetric): array {
    // Simulate querying documents from Chroma.
    $query = reset($data);
    $results = [
      [
        'id' => 'doc_1',
        'document' => 'Sample document 1',
        'metadata' => ['source' => 'test'],
        'distance' => 0.1,
      ],
      [
        'id' => 'doc_2',
        'document' => 'Sample document 2',
        'metadata' => ['source' => 'test'],
        'distance' => 0.2,
      ],
    ];

    return [
      'success' => TRUE,
      'results' => $results,
      'query' => $query,
      'collection' => $collectionName,
      'distance_metric' => $distanceMetric,
    ];
  }

  /**
   * Delete documents from the vector store.
   */
  private function deleteDocuments(array $data, string $collectionName): array {
    // Simulate deleting documents from Chroma.
    $deletedIds = [];
    foreach ($data as $index => $document) {
      $deletedIds[] = "doc_" . time() . "_" . $index;
    }

    return [
      'success' => TRUE,
      'deleted_ids' => $deletedIds,
      'collection' => $collectionName,
    ];
  }

  /**
   * Update documents in the vector store.
   */
  private function updateDocuments(array $data, string $collectionName): array {
    // Simulate updating documents in Chroma.
    $updatedIds = [];
    foreach ($data as $index => $document) {
      $updatedIds[] = "doc_" . time() . "_" . $index;
    }

    return [
      'success' => TRUE,
      'updated_ids' => $updatedIds,
      'collection' => $collectionName,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Chroma vector store nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'result' => [
          'type' => 'object',
          'description' => 'The operation result',
        ],
        'operation' => [
          'type' => 'string',
          'description' => 'The operation performed',
        ],
        'collection_name' => [
          'type' => 'string',
          'description' => 'The collection name',
        ],
        'embedding_model' => [
          'type' => 'string',
          'description' => 'The embedding model used',
        ],
        'distance_metric' => [
          'type' => 'string',
          'description' => 'The distance metric used',
        ],
        'documents_processed' => [
          'type' => 'integer',
          'description' => 'Number of documents processed',
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
        'operation' => [
          'type' => 'string',
          'title' => 'Operation',
          'description' => 'Vector store operation (add, query, delete, update)',
          'default' => 'add',
        ],
        'collectionName' => [
          'type' => 'string',
          'title' => 'Collection Name',
          'description' => 'Name of the Chroma collection',
          'default' => 'default',
        ],
        'embeddingModel' => [
          'type' => 'string',
          'title' => 'Embedding Model',
          'description' => 'Model to use for embeddings',
          'default' => 'text-embedding-ada-002',
        ],
        'distanceMetric' => [
          'type' => 'string',
          'title' => 'Distance Metric',
          'description' => 'Distance metric for similarity search (cosine, euclidean, manhattan)',
          'default' => 'cosine',
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
        'documents' => [
          'type' => 'array',
          'title' => 'Documents',
          'description' => 'Documents to process in the vector store',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
