<?php

declare(strict_types=1);

namespace Drupal\flowdrop_node_processor\Plugin\FlowDropNodeProcessor;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\flowdrop\Attribute\FlowDropNodeProcessor;
use Drupal\flowdrop\Plugin\FlowDropNodeProcessor\AbstractFlowDropNodeProcessor;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flowdrop\DTO\ConfigInterface;
use Drupal\flowdrop\DTO\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notes node processor for adding documentation and comments to workflows.
 */
#[FlowDropNodeProcessor(
  id: 'notes',
  label: new TranslatableMarkup('Notes'),
  type: 'note',
  supportedTypes: ['note'],
  category: 'utility',
  description: 'Add notes and comments',
  version: '1.0.0',
  tags: ['notes', 'utility', 'comment']
)]
final class Notes extends AbstractFlowDropNodeProcessor implements ContainerFactoryPluginInterface {

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
    // Notes nodes don't process data, they just store documentation
    // Return the note content as output for potential use by other nodes.
    return [
      'content' => $config->getConfig('content', ''),
      'noteType' => $config->getConfig('noteType', 'info'),
      'message' => 'Note content available for reference',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // Notes nodes don't require any inputs.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'content' => [
          'type' => 'string',
          'title' => 'Note Content',
          'description' => 'The markdown content of the note',
        ],
        'noteType' => [
          'type' => 'string',
          'title' => 'Note Type',
          'description' => 'The visual type of the note (info, warning, success, error, note)',
        ],
        'message' => [
          'type' => 'string',
          'title' => 'Message',
          'description' => 'Status message about the note',
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
        // Notes nodes don't have inputs.
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
        'content' => [
          'type' => 'string',
          'title' => 'Note Content',
          'description' => 'Documentation or comment text (supports Markdown)',
          'format' => 'multiline',
          'default' => "# Workflow Notes\n\nAdd your documentation here using **Markdown** formatting.\n\n## Features\n- Supports **bold** and *italic* text\n- Create lists and code blocks\n- Add links and more!",
        ],
        'noteType' => [
          'type' => 'string',
          'title' => 'Note Type',
          'description' => 'Visual style and color of the note',
          'default' => 'info',
          'enum' => ['info', 'warning', 'success', 'error', 'note'],
        ],
      ],
    ];
  }

}
