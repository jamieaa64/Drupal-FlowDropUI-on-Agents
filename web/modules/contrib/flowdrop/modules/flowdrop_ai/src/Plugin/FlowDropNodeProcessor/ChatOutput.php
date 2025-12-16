<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI-specific executor for Chat Output nodes.
 */
#[FlowDropNodeProcessor(
  id: "chat_output",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Chat Output"),
  type: "default",
  supportedTypes: ["default"],
  description: "AI-specific chat output formatting and display",
  category: "output",
  version: "1.0.0",
  tags: ["ai", "chat", "output", "display"]
)]
class ChatOutput extends AbstractFlowDropNodeProcessor {

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
    // Check if at least one of message, response, or text is provided.
    return !empty($inputs['message'] ?? $inputs['response'] ?? $inputs['text'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $message = $inputs->get('message') ?? $inputs->get('response') ?? $inputs->get('text', '');
    $show_timestamp = $config->getConfig('showTimestamp', TRUE);
    $max_length = $config->getConfig('maxLength', 2000);
    $markdown = $config->getConfig('markdown', TRUE);
    $format = $config->getConfig('format', 'chat');

    if (empty($message)) {
      throw new \Exception('No message provided to chat output');
    }

    try {
      // Format the message based on configuration.
      $formatted_message = $this->formatMessage($message, $format, $markdown, $max_length);

      // Add timestamp if requested.
      if ($show_timestamp) {
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] {$formatted_message}";
      }

      $this->getLogger()->info('Chat output executed successfully', [
        'format' => $format,
        'length' => strlen($formatted_message),
      ]);

      return [
        'output' => $formatted_message,
        'format' => $format,
        'timestamp' => $show_timestamp ? time() : NULL,
        'original_length' => strlen($message),
        'formatted_length' => strlen($formatted_message),
        'markdown_enabled' => $markdown,
        'max_length' => $max_length,
      ];

    }
    catch (\Exception $e) {
      $this->getLogger()->error('Chat output execution failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Format message based on configuration.
   *
   * @param string $message
   *   The message to format.
   * @param string $format
   *   The format type.
   * @param bool $markdown
   *   Whether to enable markdown.
   * @param int $max_length
   *   Maximum length of the message.
   *
   * @return string
   *   The formatted message.
   */
  private function formatMessage(string $message, string $format, bool $markdown, int $max_length): string {
    // Truncate if necessary.
    if (strlen($message) > $max_length) {
      $message = substr($message, 0, $max_length - 3) . '...';
    }

    // Apply format-specific formatting.
    switch ($format) {
      case 'chat':
        return $this->formatAsChat($message, $markdown);

      case 'log':
        return $this->formatAsLog($message);

      case 'json':
        return $this->formatAsJson($message);

      default:
        return $message;
    }
  }

  /**
   * Format message as chat.
   *
   * @param string $message
   *   The message to format.
   * @param bool $markdown
   *   Whether to enable markdown.
   *
   * @return string
   *   The formatted chat message.
   */
  private function formatAsChat(string $message, bool $markdown): string {
    if ($markdown) {
      // Basic markdown processing.
      $message = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $message);
      $message = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $message);
      $message = preg_replace('/`(.*?)`/', '<code>$1</code>', $message);
    }
    return $message;
  }

  /**
   * Format message as log.
   *
   * @param string $message
   *   The message to format.
   *
   * @return string
   *   The formatted log message.
   */
  private function formatAsLog(string $message): string {
    return "LOG: " . $message;
  }

  /**
   * Format message as JSON.
   *
   * @param string $message
   *   The message to format.
   *
   * @return string
   *   The formatted JSON message.
   */
  private function formatAsJson(string $message): string {
    return json_encode(['message' => $message]);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'output' => [
          'type' => 'string',
          'description' => 'The formatted output message',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The format used',
        ],
        'timestamp' => [
          'type' => 'integer',
          'description' => 'The timestamp if enabled',
        ],
        'original_length' => [
          'type' => 'integer',
          'description' => 'Original message length',
        ],
        'formatted_length' => [
          'type' => 'integer',
          'description' => 'Formatted message length',
        ],
        'markdown_enabled' => [
          'type' => 'boolean',
          'description' => 'Whether markdown was enabled',
        ],
        'max_length' => [
          'type' => 'integer',
          'description' => 'Maximum length setting',
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
        'showTimestamp' => [
          'type' => 'boolean',
          'title' => 'Show Timestamp',
          'description' => 'Whether to include timestamp',
          'default' => TRUE,
        ],
        'maxLength' => [
          'type' => 'integer',
          'title' => 'Max Length',
          'description' => 'Maximum message length',
          'default' => 2000,
          'minimum' => 1,
          'maximum' => 10000,
        ],
        'markdown' => [
          'type' => 'boolean',
          'title' => 'Markdown',
          'description' => 'Enable markdown formatting',
          'default' => TRUE,
        ],
        'format' => [
          'type' => 'string',
          'title' => 'Format',
          'description' => 'Output format type',
          'default' => 'chat',
          'enum' => ['chat', 'log', 'json'],
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
          'description' => 'Message to output',
          'required' => FALSE,
        ],
        'response' => [
          'type' => 'string',
          'title' => 'Response',
          'description' => 'AI response to output',
          'required' => FALSE,
        ],
        'text' => [
          'type' => 'string',
          'title' => 'Text',
          'description' => 'Text to output',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
