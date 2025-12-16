<?php

declare(strict_types=1);

namespace Drupal\tool_explorer\Controller;

use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\tool\Tool\ToolManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Tool Explorer pages.
 */
class ToolExplorerController extends ControllerBase {

  /**
   * Constructs a ToolExplorerController object.
   *
   * @param \Drupal\tool\Tool\ToolManager $toolManager
   *   The tool plugin manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    protected ToolManager $toolManager,
    protected RendererInterface $renderer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.tool'),
      $container->get('renderer'),
    );
  }

  /**
   * Lists all available tools.
   *
   * @return array
   *   A render array.
   */
  public function listTools(): array {
    $definitions = $this->toolManager->getDefinitions();

    $rows = [];
    foreach ($definitions as $plugin_id => $definition) {
      $operations = [
        '#type' => 'dropbutton',
        '#links' => [
          'view' => [
            'title' => $this->t('View'),
            'url' => Url::fromRoute('tool_explorer.view', ['plugin_id' => $plugin_id]),
          ],
          'execute' => [
            'title' => $this->t('Execute'),
            'url' => Url::fromRoute('tool_explorer.execute', ['plugin_id' => $plugin_id]),
          ],
        ],
      ];

      $rows[] = [
        'label' => $definition->getLabel(),
        'id' => $plugin_id,
        'description' => $definition->getDescription(),
        'provider' => $definition->getProvider(),
        'operations' => $this->renderer->render($operations),
      ];
    }

    $build['tools_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),

        $this->t('ID'),
        $this->t('Description'),
        $this->t('Provider'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No tools available.'),
      '#cache' => [
        'tags' => ['tool_list'],
      ],
    ];

    return $build;
  }

  /**
   * Displays details for a specific tool.
   *
   * @param string $plugin_id
   *   The plugin ID of the tool.
   *
   * @return array
   *   A render array.
   */
  public function viewTool(string $plugin_id): array {
    if (!$this->toolManager->hasDefinition($plugin_id)) {
      throw new NotFoundHttpException();
    }
    /** @var \Drupal\tool\Tool\ToolDefinition $definition */
    $definition = $this->toolManager->getDefinition($plugin_id);

    $build['tool_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Tool Information'),
      '#open' => TRUE,
    ];

    $build['tool_details']['info'] = [
      '#type' => 'table',
      '#rows' => [
        [
          ['data' => $this->t('Plugin ID'), 'header' => TRUE],
          $plugin_id,
        ],
        [
          ['data' => $this->t('Label'), 'header' => TRUE],
          $definition->getLabel(),
        ],
        [
          ['data' => $this->t('Description'), 'header' => TRUE],
          $definition->getDescription(),
        ],
        [
          ['data' => $this->t('Class'), 'header' => TRUE],
          $definition->getClass(),
        ],
      ],
    ];
    // @todo Add future support for nested definitions here.
    // Display input definitions.
    if (!empty($definition->getInputDefinitions())) {
      $build['inputs'] = [
        '#type' => 'details',
        '#title' => $this->t('Input Definitions'),
        '#open' => TRUE,
      ];

      $input_rows = [];
      foreach ($definition->getInputDefinitions() as $name => $input_definition) {
        $input_rows[] = [
          $name,
          $input_definition->getLabel() ?? $name,
          $input_definition->getDataType(),
          $input_definition->isRequired() ? $this->t('Yes') : $this->t('No'),
          $input_definition->getDescription() ?? '',
        ];
      }

      $build['inputs']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Label'),
          $this->t('Type'),
          $this->t('Required'),
          $this->t('Description'),
        ],
        '#rows' => $input_rows,
      ];
    }

    // Display output definitions.
    if (!empty($definition->getOutputDefinitions())) {
      $build['outputs'] = [
        '#type' => 'details',
        '#title' => $this->t('Output Definitions'),
        '#open' => TRUE,
      ];

      $output_rows = [];
      foreach ($definition->getOutputDefinitions() as $name => $output_definition) {
        $output_rows[] = [
          $name,
          $output_definition->getLabel() ?? $name,
          $output_definition->getDataType(),
          $output_definition->getDescription() ?? '',
        ];
      }

      $build['outputs']['table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Label'),
          $this->t('Type'),
          $this->t('Description'),
        ],
        '#rows' => $output_rows,
      ];
    }

    return $build;
  }

  /**
   * Gets the title for the view page.
   *
   * @param string $plugin_id
   *   The plugin ID of the tool.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The page title.
   */
  public function viewToolTitle(string $plugin_id): TranslatableMarkup|string {
    if (!$this->toolManager->hasDefinition($plugin_id)) {
      return $this->t('View Tool');
    }

    $definition = $this->toolManager->getDefinition($plugin_id);
    return $this->t('Tool: @label (@id)', ['@label' => $definition->getLabel(), '@id' => $plugin_id]);
  }

  /**
   * Gets the title for the execute page.
   *
   * @param string $plugin_id
   *   The plugin ID of the tool.
   *
   * @return string
   *   The page title.
   */
  public function getExecuteTitle(string $plugin_id): TranslatableMarkup|string {
    if (!$this->toolManager->hasDefinition($plugin_id)) {
      return $this->t('Execute Tool');
    }

    $definition = $this->toolManager->getDefinition($plugin_id);
    return $this->t('Execute: @label', ['@label' => $definition->getLabel() ?? $plugin_id]);
  }

}
