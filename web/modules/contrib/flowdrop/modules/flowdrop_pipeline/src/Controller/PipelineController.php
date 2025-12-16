<?php

declare(strict_types=1);

namespace Drupal\flowdrop_pipeline\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;
use Drupal\flowdrop_pipeline\Service\JobGenerationService;
use Drupal\flowdrop_workflow\FlowDropWorkflowInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for pipeline operations.
 */
final class PipelineController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly JobGenerationService $jobGenerationService,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormat,
    private readonly LoggerChannelInterface $logger,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('flowdrop_pipeline.job_generation'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('logger.factory')->get('flowdrop_pipeline'),
      $container->get('messenger'),
    );
  }

  /**
   * Returns a redirect response object for the specified route.
   *
   * @param string $route_name
   *   The name of the route to which to redirect.
   * @param array $route_parameters
   *   (optional) Parameters for the route.
   * @param array $options
   *   (optional) An associative array of additional options.
   * @param int $status
   *   (optional) The HTTP redirect status code for the redirect. The default is
   *   302 Found.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302) {
    $options['absolute'] = TRUE;
    return new RedirectResponse(Url::fromRoute($route_name, $route_parameters, $options)->toString(), $status);
  }

  /**
   * Generate jobs for a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $flowdrop_pipeline
   *   The pipeline entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function generateJobs(FlowDropPipelineInterface $flowdrop_pipeline): RedirectResponse {
    try {
      // Check if jobs already exist.
      $existing_jobs = $flowdrop_pipeline->getJobs();
      if (!empty($existing_jobs)) {
        $this->messenger->addWarning($this->t('Pipeline already has @count jobs. Clear existing jobs first if you want to regenerate them.', [
          '@count' => count($existing_jobs),
        ]));
        return $this->redirect('entity.flowdrop_pipeline.canonical', [
          'flowdrop_pipeline' => $flowdrop_pipeline->id(),
        ]);
      }

      // Generate jobs.
      $created_jobs = $this->jobGenerationService->generateJobs($flowdrop_pipeline);

      $this->messenger->addStatus($this->t('Successfully generated @count jobs for pipeline %label.', [
        '@count' => count($created_jobs),
        '%label' => $flowdrop_pipeline->label(),
      ]));

      // Show details of created jobs.
      if (count($created_jobs) > 0) {
        $job_labels = array_map(fn($job) => $job->label(), array_slice($created_jobs, 0, 5));
        $job_list = implode(', ', $job_labels);
        if (count($created_jobs) > 5) {
          $job_list .= ' ' . $this->t('and @more more', ['@more' => count($created_jobs) - 5]);
        }

        $this->messenger->addStatus($this->t('Created jobs: @jobs', [
          '@jobs' => $job_list,
        ]));
      }

    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to generate jobs: @message', [
        '@message' => $e->getMessage(),
      ]));

      $this->logger->error('Job generation failed for pipeline @id: @message', [
        '@id' => $flowdrop_pipeline->id(),
        '@message' => $e->getMessage(),
      ]);
    }

    // Redirect back to pipeline view.
    return $this->redirect('entity.flowdrop_pipeline.canonical', [
      'flowdrop_pipeline' => $flowdrop_pipeline->id(),
    ]);
  }

  /**
   * Clear jobs for a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $flowdrop_pipeline
   *   The pipeline entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function clearJobs(FlowDropPipelineInterface $flowdrop_pipeline): RedirectResponse {
    try {
      $count = $this->jobGenerationService->clearJobs($flowdrop_pipeline);

      if ($count > 0) {
        $this->messenger->addStatus($this->t('Successfully cleared @count jobs from pipeline %label.', [
          '@count' => $count,
          '%label' => $flowdrop_pipeline->label(),
        ]));
      }
      else {
        $this->messenger->addWarning($this->t('No jobs found to clear for pipeline %label.', [
          '%label' => $flowdrop_pipeline->label(),
        ]));
      }

    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Failed to clear jobs: @message', [
        '@message' => $e->getMessage(),
      ]));

      $this->logger->error('Job clearing failed for pipeline @id: @message', [
        '@id' => $flowdrop_pipeline->id(),
        '@message' => $e->getMessage(),
      ]);
    }

    // Redirect back to pipeline view.
    return $this->redirect('entity.flowdrop_pipeline.canonical', [
      'flowdrop_pipeline' => $flowdrop_pipeline->id(),
    ]);
  }

  /**
   * List jobs for a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $flowdrop_pipeline
   *   The pipeline entity.
   *
   * @return array
   *   The render array for the jobs list.
   */
  public function listJobs(FlowDropPipelineInterface $flowdrop_pipeline): array {
    $build = [];

    // Get all jobs for this pipeline.
    $jobs = $flowdrop_pipeline->getJobs();

    if (empty($jobs)) {
      $build['empty'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No jobs found for this pipeline.') . '</p>',
      ];

      $build['generate_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Generate Jobs'),
        '#url' => Url::fromRoute('entity.flowdrop_pipeline.generate_jobs', [
          'flowdrop_pipeline' => $flowdrop_pipeline->id(),
        ]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];

      return $build;
    }

    // Action buttons.
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['action-links']],
      '#weight' => -10,
    ];

    $build['actions']['generate'] = [
      '#type' => 'link',
      '#title' => $this->t('Regenerate Jobs'),
      '#url' => Url::fromRoute('entity.flowdrop_pipeline.clear_jobs', [
        'flowdrop_pipeline' => $flowdrop_pipeline->id(),
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'onclick' => 'return confirm("This will clear all existing jobs and regenerate them. Continue?")',
      ],
    ];

    $build['actions']['clear'] = [
      '#type' => 'link',
      '#title' => $this->t('Clear Jobs'),
      '#url' => Url::fromRoute('entity.flowdrop_pipeline.clear_jobs', [
        'flowdrop_pipeline' => $flowdrop_pipeline->id(),
      ]),
      '#attributes' => [
        'class' => ['button'],
        'onclick' => 'return confirm("This will permanently delete all jobs. Continue?")',
      ],
    ];

    // Jobs table.
    $header = [
      $this->t('Label'),
      $this->t('Node ID'),
      $this->t('Status'),
      $this->t('Priority'),
      $this->t('Dependencies'),
      $this->t('Started'),
      $this->t('Completed'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($jobs as $job) {
      $dependent_jobs = $job->getDependentJobs();
      $dependencies_str = !empty($dependent_jobs) ?
        implode(', ', array_map(fn($dep_job) => $dep_job->label(), $dependent_jobs)) :
        $this->t('None');

      $operations = [];

      // View link.
      $operations['view'] = [
        'title' => $this->t('View'),
        'url' => $job->toUrl('canonical'),
      ];

      // Edit link.
      if ($job->access('update')) {
        $operations['edit'] = [
          'title' => $this->t('Edit'),
          'url' => $job->toUrl('edit-form'),
        ];
      }

      // Delete link.
      if ($job->access('delete')) {
        $operations['delete'] = [
          'title' => $this->t('Delete'),
          'url' => $job->toUrl('delete-form'),
        ];
      }

      $rows[] = [
        $job->toLink($job->label()),
        $job->getNodeId(),
        $job->getStatus(),
        $job->getPriority(),
        $dependencies_str,
        $job->getStarted() ? $this->dateFormat->format($job->getStarted(), 'short') : '-',
        $job->getCompleted() ? $this->dateFormat->format($job->getCompleted(), 'short') : '-',
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $build['jobs_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No jobs found.'),
      '#cache' => [
        'tags' => ['flowdrop_job_list'],
      ],
    ];

    // Summary information.
    $job_counts = $flowdrop_pipeline->calculateJobCounts();
    $build['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Job Summary'),
      '#open' => TRUE,
      '#weight' => 10,
    ];

    $summary_data = [
      $this->t('Total Jobs')->render() => $job_counts['total'] ?? 0,
      $this->t('Pending')->render() => $job_counts['pending'] ?? 0,
      $this->t('Running')->render() => $job_counts['running'] ?? 0,
      $this->t('Completed')->render() => $job_counts['completed'] ?? 0,
      $this->t('Failed')->render() => $job_counts['failed'] ?? 0,
      $this->t('Cancelled')->render() => $job_counts['cancelled'] ?? 0,
    ];

    $summary_items = [];
    foreach ($summary_data as $label => $count) {
      $summary_items[] = $this->t('@label: @count', ['@label' => $label, '@count' => $count]);
    }

    $build['summary']['content'] = [
      '#type' => 'item',
      '#markup' => implode('<br>', $summary_items),
    ];

    return $build;
  }

  /**
   * Lists pipelines for a specific workflow.
   *
   * @param \Drupal\flowdrop_workflow\FlowDropWorkflowInterface $flowdrop_workflow
   *   The workflow entity.
   *
   * @return array
   *   The render array for the pipelines list.
   */
  public function listWorkflowPipelines(FlowDropWorkflowInterface $flowdrop_workflow): array {
    $build = [];

    // Query for pipelines referencing this workflow.
    $pipeline_storage = $this->entityTypeManager->getStorage('flowdrop_pipeline');
    $pipeline_ids = $pipeline_storage->getQuery()
      ->condition('workflow_id', $flowdrop_workflow->id())
      ->sort('created', 'DESC')
      ->accessCheck(TRUE)
      ->execute();

    if (empty($pipeline_ids)) {
      $build['empty'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No pipelines found for this workflow.') . '</p>',
      ];

      $build['create_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Create Pipeline'),
        '#url' => Url::fromRoute('entity.flowdrop_pipeline.add_form', [
          'flowdrop_pipeline_type' => 'default',
        ], [
          'query' => ['workflow_id' => $flowdrop_workflow->id()],
        ]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ];

      return $build;
    }

    // Load and display pipelines.
    $pipelines = $pipeline_storage->loadMultiple($pipeline_ids);

    $build['create_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Create Pipeline'),
      '#url' => Url::fromRoute('entity.flowdrop_pipeline.add_form', [
        'flowdrop_pipeline_type' => 'default',
      ], [
        'query' => ['workflow_id' => $flowdrop_workflow->id()],
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#weight' => -10,
    ];

    $header = [
      $this->t('Label'),
      $this->t('Status'),
      $this->t('Created'),
      $this->t('Jobs'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($pipelines as $pipeline) {
      assert($pipeline instanceof FlowDropPipelineInterface);
      $job_counts = $pipeline->calculateJobCounts();
      $job_summary = $this->t('@total total (@completed completed, @failed failed)', [
        '@total' => $job_counts['total'] ?? 0,
        '@completed' => $job_counts['completed'] ?? 0,
        '@failed' => $job_counts['failed'] ?? 0,
      ]);

      $operations = [];

      // View link.
      $operations['view'] = [
        'title' => $this->t('View'),
        'url' => $pipeline->toUrl('canonical'),
      ];

      // Edit link.
      if ($pipeline->access('update')) {
        $operations['edit'] = [
          'title' => $this->t('Edit'),
          'url' => $pipeline->toUrl('edit-form'),
        ];
      }

      // Generate Jobs action (if no jobs exist)
      if (($job_counts['total'] ?? 0) === 0) {
        $operations['generate_jobs'] = [
          'title' => $this->t('Generate Jobs'),
          'url' => Url::fromRoute('entity.flowdrop_pipeline.generate_jobs', [
            'flowdrop_pipeline' => $pipeline->id(),
          ]),
        ];
      }

      // Delete link.
      if ($pipeline->access('delete')) {
        $operations['delete'] = [
          'title' => $this->t('Delete'),
          'url' => $pipeline->toUrl('delete-form'),
        ];
      }

      $rows[] = [
        Link::fromTextAndUrl($pipeline->label(), $pipeline->toUrl('canonical')),
        $pipeline->getStatus(),
        $this->dateFormat->format((int) $pipeline->get('created')->value, 'short'),
        $job_summary,
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $build['pipelines_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No pipelines found.'),
      '#cache' => [
        'tags' => ['flowdrop_pipeline_list'],
      ],
    ];

    return $build;
  }

}
