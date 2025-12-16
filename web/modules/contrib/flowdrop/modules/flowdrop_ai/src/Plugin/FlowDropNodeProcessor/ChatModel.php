<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop_ai\Service\AiModelService;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * AI-specific executor for Chat Model nodes.
 */
#[FlowDropNodeProcessor(
  id: "chat_model",
  label: new TranslatableMarkup("Chat Model"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "AI chat model integration for conversational AI",
  version: "1.0.0",
  tags: ["ai", "chat", "model", "conversation"]
)]
class ChatModel extends AbstractFlowDropNodeProcessor {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected AiModelService $aiModelService,
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
      $container->get('logger.factory'),
      $container->get('flowdrop_ai.model_service')
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
    $message = $inputs->get('message') ?? $inputs->get('text', '');
    $model_id = $config->getConfig('model', 'gpt-3.5-turbo');
    $temperature = $config->getConfig('temperature', 0.7);
    $max_tokens = $config->getConfig('maxTokens', 1000);

    if (empty($message)) {
      throw new \Exception('No message provided to chat model');
    }

    // Validate model configuration.
    if (!$this->aiModelService->validateModelConfig($config->toArray())) {
      throw new \Exception('Invalid model configuration');
    }

    // Check if model supports chat.
    if (!$this->aiModelService->supportsChat($model_id)) {
      throw new \Exception("Model {$model_id} does not support chat functionality");
    }

    try {
      // Get model configuration.
      $model_config = $this->aiModelService->getModel($model_id);
      if (!$model_config) {
        throw new \Exception("Model {$model_id} not found");
      }

      // Execute AI chat request.
      $response = $this->executeChatRequest($message, $model_id, $temperature, $max_tokens, $model_config);

      $this->getLogger()->info('Chat model executed successfully with model @model', [
        '@model' => $model_id,
      ]);

      return [
        'response' => $response['content'],
        'model' => $model_id,
        'provider' => $model_config['provider'],
        'tokens_used' => $response['tokens_used'] ?? 0,
        'temperature' => $temperature,
        'max_tokens' => $max_tokens,
      ];
    }
    catch (\Exception $e) {
      $this->getLogger()->error('Chat model execution failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Execute chat request with AI model.
   *
   * @param string $message
   *   The message to send to the AI model.
   * @param string $model_id
   *   The model ID to use.
   * @param float $temperature
   *   The temperature setting.
   * @param int $max_tokens
   *   The maximum tokens to generate.
   * @param array $model_config
   *   The model configuration.
   *
   * @return array
   *   The AI response.
   */
  private function executeChatRequest(string $message, string $model_id, float $temperature, int $max_tokens, array $model_config): array {
    // This would integrate with the actual AI service
    // For now, return a mock response.
    return [
      'content' => "AI response to: {$message}",
      'tokens_used' => strlen($message) + 50,
    ];
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
          'description' => 'The AI model response',
        ],
        'model' => [
          'type' => 'string',
          'description' => 'The model used',
        ],
        'provider' => [
          'type' => 'string',
          'description' => 'The AI provider',
        ],
        'tokens_used' => [
          'type' => 'integer',
          'description' => 'Number of tokens used',
        ],
        'temperature' => [
          'type' => 'number',
          'description' => 'Temperature setting used',
        ],
        'max_tokens' => [
          'type' => 'integer',
          'description' => 'Maximum tokens setting',
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
          'description' => 'AI model to use for chat',
          'default' => 'gpt-3.5-turbo',
        ],
        'temperature' => [
          'type' => 'number',
          'title' => 'Temperature',
          'description' => 'Creativity level (0.0 to 1.0)',
          'default' => 0.7,
          'minimum' => 0.0,
          'maximum' => 1.0,
        ],
        'maxTokens' => [
          'type' => 'integer',
          'title' => 'Max Tokens',
          'description' => 'Maximum tokens to generate',
          'default' => 1000,
          'minimum' => 1,
          'maximum' => 4000,
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
          'description' => 'Message to send to AI model',
          'required' => FALSE,
        ],
        'text' => [
          'type' => 'string',
          'title' => 'Text',
          'description' => 'Text input for AI model',
          'required' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Check if at least one of message or text is provided.
    return !empty($inputs['message'] ?? $inputs['text'] ?? '');
  }

}
