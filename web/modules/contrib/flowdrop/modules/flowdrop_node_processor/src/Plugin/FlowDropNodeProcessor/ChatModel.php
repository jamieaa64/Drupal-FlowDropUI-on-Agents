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
 * Executor for Chat Model nodes.
 */
#[FlowDropNodeProcessor(
  id: "chat_model",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Chat Model"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "AI chat model integration",
  version: "1.0.0",
  tags: ["ai", "chat", "model"]
)]
class ChatModel extends AbstractFlowDropNodeProcessor {

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
    $model = $config->getConfig('model', 'gpt-3.5-turbo');
    $temperature = $config->getConfig('temperature', 0.7);

    // Get the input message.
    $message = $inputs->get('message', '');

    $this->getLogger()->info('Chat model executed successfully', [
      'model' => $model,
      'temperature' => $temperature,
      'message_length' => strlen($message),
    ]);

    // Simulate chat model response.
    $response = "This is a simulated response from the {$model} model. In a real implementation, this would call the actual AI model API.";

    return [
      'response' => $response,
      'model' => $model,
      'temperature' => $temperature,
    // Rough estimation.
      'tokens_used' => strlen($response) / 4,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Chat model nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'response' => [
          'type' => 'string',
          'description' => 'The model response',
        ],
        'model' => [
          'type' => 'string',
          'description' => 'The model used',
        ],
        'temperature' => [
          'type' => 'number',
          'description' => 'The temperature setting',
        ],
        'tokens_used' => [
          'type' => 'integer',
          'description' => 'Number of tokens used',
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
          'description' => 'The chat model to use',
          'default' => 'gpt-3.5-turbo',
        ],
        'temperature' => [
          'type' => 'number',
          'title' => 'Temperature',
          'description' => 'Model temperature (0.0 to 2.0)',
          'default' => 0.7,
        ],
        'maxTokens' => [
          'type' => 'integer',
          'title' => 'Max Tokens',
          'description' => 'Maximum tokens in response',
          'default' => 1000,
        ],
        'systemPrompt' => [
          'type' => 'string',
          'title' => 'System Prompt',
          'description' => 'System prompt for the model',
          'format' => 'multiline',
          'default' => '',
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
          'description' => 'The message to send to the model',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
