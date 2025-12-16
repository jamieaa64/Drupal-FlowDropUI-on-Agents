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
 * Executor for Prompt Template nodes.
 */
#[FlowDropNodeProcessor(
  id: "prompt_template",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Prompt Template"),
  type: "default",
  supportedTypes: ["default"],
  description: "Create prompts using templates with variable substitution",
  category: "ai",
  version: "1.0.0",
  tags: ["prompt", "template", "ai", "variable"]
)]
class PromptTemplate extends AbstractFlowDropNodeProcessor {

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
    // Prompt template can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(InputInterface $inputs, ConfigInterface $config): array {
    $template = $config->getConfig('template', '');
    $variables = $config->getConfig('variables', []);

    // Replace variables in template.
    $prompt = $template;
    foreach ($variables as $key => $value) {
      $prompt = str_replace("{{" . $key . "}}", $value, $prompt);
    }

    // Replace input variables.
    if (!$inputs->isEmpty()) {
      foreach ($inputs->toArray() as $key => $value) {
        $prompt = str_replace("{{" . $key . "}}", $value, $prompt);
      }
    }

    $this->getLogger()->info('Prompt template executed successfully', [
      'template_length' => strlen($template),
      'prompt_length' => strlen($prompt),
      'variables_count' => count($variables),
    ]);

    return [
      'prompt' => $prompt,
      'template' => $template,
      'variables' => $variables,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'prompt' => [
          'type' => 'string',
          'description' => 'The generated prompt',
        ],
        'template' => [
          'type' => 'string',
          'description' => 'The original template',
        ],
        'variables' => [
          'type' => 'object',
          'description' => 'The variables used',
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
        'template' => [
          'type' => 'string',
          'title' => 'Template',
          'description' => 'The prompt template with variables in {{variable}} format',
          'default' => '',
        ],
        'variables' => [
          'type' => 'object',
          'title' => 'Variables',
          'description' => 'Default variables for the template',
          'default' => [],
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
        'variables' => [
          'type' => 'object',
          'title' => 'Input Variables',
          'description' => 'Variables to substitute in the template',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
