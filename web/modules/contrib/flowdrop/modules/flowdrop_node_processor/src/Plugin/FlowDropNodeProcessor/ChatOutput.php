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
 * Executor for Chat Output nodes.
 */
#[FlowDropNodeProcessor(
  id: "chat_output",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Chat Output"),
  type: "default",
  supportedTypes: ["default"],
  description: "Output chat messages in various formats",
  category: "output",
  version: "1.0.0",
  tags: ["chat", "output", "message", "display"]
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
    // Chat output can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $message = $config->getConfig('message', '');
    $format = $config->getConfig('format', 'text');
    $timestamp = $config->getConfig('showTimestamp', FALSE) ? time() : NULL;

    // If inputs are provided, use the first input as the message.
    $input_message = $inputs->get('message');
    if ($input_message !== NULL) {
      $message = $input_message;
    }

    $this->getLogger()->info('Chat output executed successfully', [
      'message_length' => strlen($message),
      'format' => $format,
    ]);

    return [
      'message' => $message,
      'format' => $format,
      'timestamp' => $timestamp,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'message' => [
          'type' => 'string',
          'description' => 'The chat message',
        ],
        'format' => [
          'type' => 'string',
          'description' => 'The message format',
        ],
        'timestamp' => [
          'type' => 'integer',
          'description' => 'The message timestamp',
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
        'message' => [
          'type' => 'string',
          'title' => 'Message',
          'description' => 'Default chat message',
          'default' => '',
        ],
        'format' => [
          'type' => 'string',
          'title' => 'Format',
          'description' => 'Message format (text, markdown, html)',
          'default' => 'text',
        ],
        'showTimestamp' => [
          'type' => 'boolean',
          'title' => 'Show Timestamp',
          'description' => 'Whether to include timestamp',
          'default' => FALSE,
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
          'title' => 'Chat Message',
          'description' => 'The chat message to output',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
