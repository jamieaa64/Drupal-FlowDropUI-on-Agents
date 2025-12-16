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
 * Executor for Regex Extractor nodes.
 */
#[FlowDropNodeProcessor(
  id: "regex_extractor",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("Regex Extractor"),
  type: "default",
  supportedTypes: ["default"],
  category: "processing",
  description: "Extract data using regular expressions",
  version: "1.0.0",
  tags: ["regex", "extract", "processing"]
)]
class RegexExtractor extends AbstractFlowDropNodeProcessor {

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
    $pattern = $config->getConfig('pattern', '');
    $flags = $config->getConfig('flags', '');
    $matchAll = $config->getConfig('matchAll', FALSE);

    // Get the input text.
    $text = $inputs->get('text') ?: '';

    $matches = [];
    if (!empty($pattern) && !empty($text)) {
      $regex = '/' . $pattern . '/' . $flags;

      if ($matchAll) {
        preg_match_all($regex, $text, $matches, PREG_SET_ORDER);
      }
      else {
        preg_match($regex, $text, $matches);
      }
    }

    $this->getLogger()->info('Regex extractor executed successfully', [
      'pattern' => $pattern,
      'matches_count' => count($matches),
      'text_length' => strlen($text),
    ]);

    return [
      'matches' => $matches,
      'pattern' => $pattern,
      'flags' => $flags,
      'match_all' => $matchAll,
      'total_matches' => count($matches),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Regex extractor nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'matches' => [
          'type' => 'array',
          'description' => 'The regex matches',
        ],
        'pattern' => [
          'type' => 'string',
          'description' => 'The regex pattern used',
        ],
        'flags' => [
          'type' => 'string',
          'description' => 'The regex flags used',
        ],
        'match_all' => [
          'type' => 'boolean',
          'description' => 'Whether to match all occurrences',
        ],
        'total_matches' => [
          'type' => 'integer',
          'description' => 'The total number of matches',
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
        'pattern' => [
          'type' => 'string',
          'title' => 'Pattern',
          'description' => 'The regex pattern to match',
          'default' => '',
        ],
        'flags' => [
          'type' => 'string',
          'title' => 'Flags',
          'description' => 'Regex flags (i, m, s, x, etc.)',
          'default' => '',
        ],
        'matchAll' => [
          'type' => 'boolean',
          'title' => 'Match All',
          'description' => 'Whether to match all occurrences',
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
        'text' => [
          'type' => 'string',
          'title' => 'Text',
          'description' => 'The text to extract from',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
