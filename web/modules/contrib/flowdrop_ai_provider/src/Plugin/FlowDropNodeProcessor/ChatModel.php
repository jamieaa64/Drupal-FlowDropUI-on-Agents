<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Plugin\FlowDropNodeProcessor;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop_ai_provider\Service\AiModelService;
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
    protected $aiProviderManager,
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
      $container->get('flowdrop_ai_provider.model_service'),
      $container->get('ai.provider')
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

    // Get model_id from config, fallback to default configured model.
    $model_id = $config->getConfig('model', '');
    if (empty($model_id)) {
      // Try to get the default model from AI settings.
      $model_id = $this->aiModelService->getDefaultModelForOperationType('chat');

      // If no default is set, use the first available model.
      if (empty($model_id)) {
        $available_models = $this->aiModelService->getAvailableModels();
        $model_ids = array_keys($available_models);
        $model_id = !empty($model_ids) ? $model_ids[0] : '';

        if (empty($model_id)) {
          throw new \Exception('No AI models are configured. Please configure at least one AI provider.');
        }
      }
    }

    $temperature = $config->getConfig('temperature', 0.7);
    $max_tokens = $config->getConfig('maxTokens', 1000);
    $system_prompt = $config->getConfig('systemPrompt', '');

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
      $response = $this->executeChatRequest($message, $model_id, $temperature, $max_tokens, $model_config, $system_prompt);

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
   * @param string $system_prompt
   *   The system prompt to set AI behavior.
   *
   * @return array
   *   The AI response.
   */
  private function executeChatRequest(string $message, string $model_id, float $temperature, int $max_tokens, array $model_config, string $system_prompt = ''): array {
    // Use the Drupal AI module to make the actual request.
    try {
      $provider_id = $model_config['provider'] ?? 'ollama';

      // Get the AI provider instance.
      $provider = $this->aiProviderManager->createInstance($provider_id, [
        'temperature' => $temperature,
        'max_tokens' => $max_tokens,
      ]);

      // Set up the chat request with ChatInput object.
      $chat_messages = [];

      // Add system message if provided.
      if (!empty($system_prompt)) {
        $chat_messages[] = new ChatMessage('system', $system_prompt);
      }

      // Add user message.
      $chat_messages[] = new ChatMessage('user', $message);
      $chat_input = new ChatInput($chat_messages);

      // Call the provider's chat method.
      $response = $provider->chat($chat_input, $model_id, []);

      // Extract the response content.
      $content = '';
      $tokens_used = 0;

      // Handle response object with normalized method (preferred).
      if (is_object($response) && method_exists($response, 'getNormalized')) {
        $normalized = $response->getNormalized();
        $content = $normalized['text'] ?? '';
        $tokens_used = $normalized['total_tokens'] ?? 0;
      }
      // Handle array response (OpenAI-style).
      elseif (is_array($response) && isset($response['choices'][0]['message']['content'])) {
        $content = $response['choices'][0]['message']['content'];
        $tokens_used = $response['usage']['total_tokens'] ?? 0;
      }
      // Handle string response.
      elseif (is_string($response)) {
        $content = $response;
      }
      // Handle response object with getText method.
      elseif (is_object($response) && method_exists($response, 'getText')) {
        $content = $response->getText();
      }

      return [
        'content' => $content,
        'tokens_used' => $tokens_used,
      ];
    }
    catch (\Exception $e) {
      $this->getLogger()->error('AI chat request failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
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
    $available_models = $this->aiModelService->getAvailableModels();
    $model_ids = array_keys($available_models);
    $model_options = [];

    foreach ($available_models as $model_id => $model_info) {
      $model_options[] = [
        'value' => $model_id,
        'label' => $model_info['name'] . ' (' . $model_info['provider'] . ')',
      ];
    }

    // Get default model from AI settings, or fallback to first available.
    $default_model = $this->aiModelService->getDefaultModelForOperationType('chat');
    if (empty($default_model) && !empty($model_ids)) {
      $default_model = $model_ids[0];
    }

    return [
      'type' => 'object',
      'properties' => [
        'model' => [
          'type' => 'string',
          'title' => 'Model',
          'description' => 'AI model to use for chat',
          'default' => $default_model,
          'enum' => $model_ids,
          'options' => $model_options,
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
        'systemPrompt' => [
          'type' => 'string',
          'title' => 'System Prompt',
          'description' => 'System prompt to set the AI behavior and context',
          'default' => '',
          'format' => 'multiline',
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
