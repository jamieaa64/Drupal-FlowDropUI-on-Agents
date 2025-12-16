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
 * Executor for Conversation Buffer nodes.
 */
#[FlowDropNodeProcessor(
  id: "conversation_buffer",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Conversation Buffer"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "Buffer for conversation history",
  version: "1.0.0",
  tags: ["ai", "conversation", "buffer", "history"]
)]
class ConversationBuffer extends AbstractFlowDropNodeProcessor {

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
    $maxMessages = $config->getConfig('maxMessages', 10);
    $includeMetadata = $config->getConfig('includeMetadata', TRUE);
    $format = $config->getConfig('format', 'array');

    // Get the input message.
    $message = $inputs->get('message') ?: '';

    // Simulate conversation buffer.
    $buffer = $this->getBuffer();

    switch ($operation) {
      case 'add':
        $buffer = $this->addMessage($buffer, $message, $includeMetadata);
        break;

      case 'clear':
        $buffer = [];
        break;

      case 'get':
        // Buffer already loaded.
        break;

      case 'truncate':
        $buffer = array_slice($buffer, -$maxMessages);
        break;
    }

    // Ensure buffer doesn't exceed max messages.
    if (count($buffer) > $maxMessages) {
      $buffer = array_slice($buffer, -$maxMessages);
    }

    // Format output.
    $output = $this->formatBuffer($buffer, $format);

    $this->getLogger()->info('Conversation buffer executed successfully', [
      'operation' => $operation,
      'buffer_size' => count($buffer),
      'max_messages' => $maxMessages,
    ]);

    return [
      'buffer' => $output,
      'operation' => $operation,
      'buffer_size' => count($buffer),
      'max_messages' => $maxMessages,
      'include_metadata' => $includeMetadata,
      'format' => $format,
    ];
  }

  /**
   * Get the conversation buffer.
   */
  private function getBuffer(): array {
    // In a real implementation, this would retrieve from persistent storage.
    return [
      [
        'role' => 'system',
        'content' => 'You are a helpful assistant.',
        'timestamp' => time() - 3600,
      ],
      [
        'role' => 'user',
        'content' => 'Hello, how are you?',
        'timestamp' => time() - 1800,
      ],
      [
        'role' => 'assistant',
        'content' => 'I am doing well, thank you for asking! How can I help you today?',
        'timestamp' => time() - 1800,
      ],
    ];
  }

  /**
   * Add a message to the buffer.
   */
  private function addMessage(array $buffer, string $message, bool $includeMetadata): array {
    $newMessage = [
      'role' => 'user',
      'content' => $message,
    ];

    if ($includeMetadata) {
      $newMessage['timestamp'] = time();
      $newMessage['id'] = uniqid('msg_');
    }

    $buffer[] = $newMessage;

    return $buffer;
  }

  /**
   * Format the buffer for output.
   */
  private function formatBuffer(array $buffer, string $format): array|string {
    switch ($format) {
      case 'json':
        return json_encode($buffer, JSON_PRETTY_PRINT);

      case 'text':
        $text = '';
        foreach ($buffer as $message) {
          $text .= "{$message['role']}: {$message['content']}\n";
        }
        return trim($text);

      case 'array':
      default:
        return $buffer;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Conversation buffer nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'buffer' => [
          'type' => 'mixed',
          'description' => 'The conversation buffer',
        ],
        'operation' => [
          'type' => 'string',
          'description' => 'The operation performed',
        ],
        'buffer_size' => [
          'type' => 'integer',
          'description' => 'Number of messages in buffer',
        ],
        'max_messages' => [
          'type' => 'integer',
          'description' => 'Maximum messages allowed',
        ],
        'include_metadata' => [
          'type' => 'boolean',
          'description' => 'Whether metadata is included',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The output format',
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
          'description' => 'Buffer operation to perform',
          'default' => 'add',
          'enum' => ['add', 'clear', 'get', 'truncate'],
        ],
        'maxMessages' => [
          'type' => 'integer',
          'title' => 'Max Messages',
          'description' => 'Maximum number of messages to keep',
          'default' => 10,
        ],
        'includeMetadata' => [
          'type' => 'boolean',
          'title' => 'Include Metadata',
          'description' => 'Whether to include message metadata',
          'default' => TRUE,
        ],
        'format' => [
          'type' => 'string',
          'title' => 'Format',
          'description' => 'Output format to use',
          'default' => 'array',
          'enum' => ['array', 'json', 'text'],
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
        'message' => [
          'type' => 'string',
          'title' => 'Message',
          'description' => 'The message to add to the buffer',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
