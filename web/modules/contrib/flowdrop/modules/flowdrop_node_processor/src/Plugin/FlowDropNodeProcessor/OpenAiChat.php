<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executor for OpenAI Chat nodes.
 *
 * This component provides OpenAI GPT model integration for chat completions,
 * similar to Langflow's OpenAI Chat component.
 */
#[FlowDropNodeProcessor(
  id: "openai_chat",
  label: new TranslatableMarkup("OpenAI Chat"),
  type: "default",
  supportedTypes: ["default"],
  category: "models",
  description: "Chat completion using OpenAI's GPT models",
  version: "1.0.0",
  tags: ["ai", "openai", "gpt", "chat", "completion"],
)]
class OpenAiChat extends AbstractFlowDropNodeProcessor {

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
    $prompt = $config->getConfig('prompt', '');
    $model = $config->getConfig('model', 'gpt-3.5-turbo');
    $temperature = $config->getConfig('temperature', 0.7);
    $maxTokens = $config->getConfig('maxTokens', 1000);
    $apiKey = $config->getConfig('apiKey', '');

    // Use input prompt if provided, otherwise use config prompt.
    if (!empty($inputs->get('prompt'))) {
      $prompt = $inputs->get('prompt');
    }

    // Simulate OpenAI API call (in real implementation,
    // this would call the actual API).
    $response = $this->simulateOpenAiCall($prompt, $model, $temperature, $maxTokens, $apiKey);

    $this->getLogger()->info('OpenAI Chat executed successfully', [
      'model' => $model,
      'temperature' => $temperature,
      'prompt_length' => strlen($prompt),
      'response_length' => strlen($response),
    ]);

    return [
      'response' => $response,
      'model' => $model,
      'temperature' => $temperature,
      'tokens_used' => $this->estimateTokens($prompt, $response),
      'finish_reason' => 'stop',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Validate that we have either a prompt in inputs or config.
    if (empty($inputs['prompt'] ?? '') && empty($this->configuration['prompt'] ?? '')) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'prompt' => [
          'type' => 'string',
          'title' => 'Prompt',
          'description' => 'The text prompt to send to OpenAI',
          'required' => FALSE,
        ],
        'system_message' => [
          'type' => 'string',
          'title' => 'System Message',
          'description' => 'System message to set the context',
          'required' => FALSE,
        ],
        'messages' => [
          'type' => 'array',
          'title' => 'Messages',
          'description' => 'Array of conversation messages',
          'required' => FALSE,
        ],
      ],
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
          'description' => 'The generated response from OpenAI',
        ],
        'model' => [
          'type' => 'string',
          'description' => 'The model used for generation',
        ],
        'temperature' => [
          'type' => 'number',
          'description' => 'The temperature setting used',
        ],
        'tokens_used' => [
          'type' => 'integer',
          'description' => 'Number of tokens used in the request',
        ],
        'finish_reason' => [
          'type' => 'string',
          'description' => 'The reason the generation finished',
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
          'description' => 'OpenAI model to use for chat completion',
          'enum' => ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo'],
          'default' => 'gpt-3.5-turbo',
        ],
        'temperature' => [
          'type' => 'number',
          'title' => 'Temperature',
          'description' => 'Controls randomness in the response (0-2)',
          'minimum' => 0,
          'maximum' => 2,
          'default' => 0.7,
        ],
        'maxTokens' => [
          'type' => 'integer',
          'title' => 'Max Tokens',
          'description' => 'Maximum number of tokens to generate',
          'minimum' => 1,
          'maximum' => 4000,
          'default' => 1000,
        ],
        'prompt' => [
          'type' => 'string',
          'title' => 'Default Prompt',
          'description' => 'Default prompt to use if no input is provided',
          'format' => 'multiline',
          'default' => '',
        ],
        'apiKey' => [
          'type' => 'string',
          'title' => 'API Key',
          'description' => 'OpenAI API key (will be stored securely)',
          'format' => 'password',
          'default' => '',
        ],
        'topP' => [
          'type' => 'number',
          'title' => 'Top P',
          'description' => 'Controls diversity via nucleus sampling (0-1)',
          'minimum' => 0,
          'maximum' => 1,
          'default' => 1,
        ],
        'frequencyPenalty' => [
          'type' => 'number',
          'title' => 'Frequency Penalty',
          'description' => 'Reduces repetition of common tokens (-2 to 2)',
          'minimum' => -2,
          'maximum' => 2,
          'default' => 0,
        ],
        'presencePenalty' => [
          'type' => 'number',
          'title' => 'Presence Penalty',
          'description' => 'Reduces repetition of topics (-2 to 2)',
          'minimum' => -2,
          'maximum' => 2,
          'default' => 0,
        ],
      ],
    ];
  }

  /**
   * Simulate OpenAI API call for demonstration purposes.
   *
   * @param string $prompt
   *   The input prompt.
   * @param string $model
   *   The model to use.
   * @param float $temperature
   *   The temperature setting.
   * @param int $maxTokens
   *   The maximum tokens.
   * @param string $apiKey
   *   The API key.
   *
   * @return string
   *   The simulated response.
   */
  private function simulateOpenAiCall(string $prompt, string $model, float $temperature, int $maxTokens, string $apiKey): string {
    // In a real implementation, this would make an actual API call to OpenAI
    // For demonstration, we'll return a simulated response.
    $responses = [
      'I understand your request and will help you with that.',
      'Based on the information provided, here is my analysis.',
      'Let me process that for you and provide a comprehensive response.',
      'I can assist you with that. Here are the key points to consider.',
      'Thank you for your input. Here is what I can tell you about this topic.',
    ];

    $response = $responses[array_rand($responses)];

    // Add some variation based on temperature.
    if ($temperature > 1.0) {
      $response .= ' This response has been generated with higher creativity settings.';
    }

    return $response;
  }

  /**
   * Estimate token usage for the request.
   *
   * @param string $prompt
   *   The input prompt.
   * @param string $response
   *   The generated response.
   *
   * @return int
   *   Estimated token count.
   */
  private function estimateTokens(string $prompt, string $response): int {
    // Rough estimation: 1 token ≈ 4 characters.
    return (int) ((strlen($prompt) + strlen($response)) / 4);
  }

}
