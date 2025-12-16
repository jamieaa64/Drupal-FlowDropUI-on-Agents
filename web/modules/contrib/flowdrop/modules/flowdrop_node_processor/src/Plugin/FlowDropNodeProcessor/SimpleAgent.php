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
 * Executor for Simple Agent nodes.
 */
#[FlowDropNodeProcessor(
  id: "simple_agent",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Simple Agent"),
  type: "default",
  supportedTypes: ["default"],
  category: "ai",
  description: "Simple AI agent implementation",
  version: "1.0.0",
  tags: ["ai", "agent", "simple"]
)]
class SimpleAgent extends AbstractFlowDropNodeProcessor {

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
    $systemPrompt = $config->getConfig('systemPrompt', 'You are a helpful assistant.');
    $temperature = $config->getConfig('temperature', 0.7);
    $maxTokens = $config->getConfig('maxTokens', 1000);
    $tools = $config->getConfig('tools', []);

    // Get the input message.
    $message = $inputs->get('message') ?: '';

    $tools = $inputs->get('tools') ?: $tools;

    // Simulate agent processing.
    $response = $this->processAgentRequest($message, $systemPrompt, $temperature, $maxTokens, $tools);

    $this->getLogger()->info('Simple agent executed successfully', [
      'message_length' => strlen($message),
      'temperature' => $temperature,
      'max_tokens' => $maxTokens,
      'tools_count' => count($tools),
    ]);

    return [
      'response' => $response,
      'system_prompt' => $systemPrompt,
      'temperature' => $temperature,
      'max_tokens' => $maxTokens,
      'tools_used' => $response['tools_used'] ?? [],
      'message' => $message,
    ];
  }

  /**
   * Process agent request.
   */
  private function processAgentRequest(string $message, string $systemPrompt, float $temperature, int $maxTokens, array $tools): array {
    // Simulate agent processing.
    $response = [
      'content' => "I understand you said: '{$message}'. How can I help you with that?",
      'role' => 'assistant',
      'timestamp' => time(),
      'tools_used' => [],
    ];

    // Simulate tool usage if tools are available.
    if (!empty($tools)) {
      // Use first 2 tools.
      $response['tools_used'] = array_slice($tools, 0, 2);
      $response['content'] .= " I can use the following tools: " . implode(', ', array_column($response['tools_used'], 'name'));
    }

    // Add some randomness based on temperature.
    if ($temperature > 0.5) {
      $response['content'] .= " (This response has some creative variation based on temperature: {$temperature})";
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Simple agent nodes can accept any inputs or none.
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
          'type' => 'object',
          'description' => 'The agent response',
        ],
        'system_prompt' => [
          'type' => 'string',
          'description' => 'The system prompt used',
        ],
        'temperature' => [
          'type' => 'number',
          'description' => 'The temperature setting',
        ],
        'max_tokens' => [
          'type' => 'integer',
          'description' => 'The maximum tokens allowed',
        ],
        'tools_used' => [
          'type' => 'array',
          'description' => 'The tools used by the agent',
        ],
        'message' => [
          'type' => 'string',
          'description' => 'The input message',
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
        'systemPrompt' => [
          'type' => 'string',
          'title' => 'System Prompt',
          'description' => 'System prompt for the agent',
          'format' => 'multiline',
          'default' => 'You are a helpful assistant.',
        ],
        'temperature' => [
          'type' => 'number',
          'title' => 'Temperature',
          'description' => 'Temperature for response generation (0.0 to 1.0)',
          'default' => 0.7,
        ],
        'maxTokens' => [
          'type' => 'integer',
          'title' => 'Max Tokens',
          'description' => 'Maximum tokens for response',
          'default' => 1000,
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
          'description' => 'The message for the agent to process',
          'required' => FALSE,
        ],
        'tools' => [
          'type' => 'array',
          'title' => 'Tools',
          'description' => 'Tools available to the agent',
          'default' => [],
        ],
      ],
    ];
  }

}
