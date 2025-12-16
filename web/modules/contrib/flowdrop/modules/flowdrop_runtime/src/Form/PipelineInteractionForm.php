<?php

namespace Drupal\flowdrop_runtime\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface;
use Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator;
use Drupal\flowdrop_runtime\Service\Orchestrator\SynchronousOrchestrator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for interacting with FlowDrop pipelines.
 *
 * Provides a user interface for executing, controlling, and monitoring
 * pipeline operations including start, pause, resume, cancel, and retry.
 */
class PipelineInteractionForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The asynchronous orchestrator.
   */
  protected AsynchronousOrchestrator $asynchronousOrchestrator;

  /**
   * The synchronous orchestrator.
   */
  protected SynchronousOrchestrator $synchronousOrchestrator;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * The job generation service.
   *
   * @var \Drupal\flowdrop_pipeline\Service\JobGenerationService
   */
  protected $jobGenerationService;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new PipelineInteractionForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\flowdrop_runtime\Service\Orchestrator\AsynchronousOrchestrator $asynchronous_orchestrator
   *   The asynchronous orchestrator.
   * @param \Drupal\flowdrop_runtime\Service\Orchestrator\SynchronousOrchestrator $synchronous_orchestrator
   *   The synchronous orchestrator.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param mixed $job_generation_service
   *   The job generation service.
   * @param mixed $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactory $queue_factory,
    AsynchronousOrchestrator $asynchronous_orchestrator,
    SynchronousOrchestrator $synchronous_orchestrator,
    MessengerInterface $messenger,
    LoggerInterface $logger,
    $job_generation_service,
    $date_formatter,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->asynchronousOrchestrator = $asynchronous_orchestrator;
    $this->synchronousOrchestrator = $synchronous_orchestrator;
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->jobGenerationService = $job_generation_service;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get("entity_type.manager"),
      $container->get("queue"),
      $container->get("flowdrop_runtime.asynchronous_orchestrator"),
      $container->get("flowdrop_runtime.synchronous_orchestrator"),
      $container->get("messenger"),
      $container->get("logger.factory")->get("flowdrop_runtime"),
      $container->get("flowdrop_pipeline.job_generation"),
      $container->get("date.formatter")
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "flowdrop_runtime_pipeline_interaction_form";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Get pipeline from form state or request.
    $pipeline = $form_state->get("pipeline");
    if (!$pipeline) {
      $pipeline_id = $this->getRequest()->query->get("pipeline_id");
      if ($pipeline_id) {
        $pipeline = $this->entityTypeManager
          ->getStorage("flowdrop_pipeline")
          ->load($pipeline_id);
        $form_state->set("pipeline", $pipeline);
      }
    }

    if (!$pipeline instanceof FlowDropPipelineInterface) {
      // Pipeline selection if none provided.
      $form["pipeline_selection"] = [
        "#type" => "fieldset",
        "#title" => $this->t("Select Pipeline"),
        "#description" => $this->t("Choose a pipeline to interact with."),
      ];

      $options = [];
      $pipelines = $this->entityTypeManager
        ->getStorage("flowdrop_pipeline")
        ->loadMultiple();

      foreach ($pipelines as $pipeline_option) {
        assert($pipeline_option instanceof FlowDropPipelineInterface);
        $job_counts = $pipeline_option->calculateJobCounts();
        $status = $pipeline_option->getStatus();
        $label = sprintf(
          "%s (%s) - %d jobs (%d completed, %d failed)",
          $pipeline_option->label(),
          ucfirst($status),
          $job_counts["total"],
          $job_counts["completed"],
          $job_counts["failed"]
        );
        $options[$pipeline_option->id()] = $label;
      }

      $form["pipeline_selection"]["pipeline_id"] = [
        "#type" => "select",
        "#title" => $this->t("Pipeline"),
        "#description" => $this->t("Select the pipeline you want to interact with."),
        "#options" => $options,
        "#empty_option" => $this->t("- Select a pipeline -"),
        "#required" => TRUE,
        "#ajax" => [
          "callback" => "::loadPipelineCallback",
          "wrapper" => "pipeline-operations-wrapper",
          "event" => "change",
        ],
      ];

      $form["load_pipeline"] = [
        "#type" => "submit",
        "#value" => $this->t("Load Pipeline"),
        "#submit" => ["::loadPipelineSubmit"],
      ];

      $form["operations"] = [
        "#type" => "container",
        "#prefix" => '<div id="pipeline-operations-wrapper">',
        "#suffix" => '</div>',
      ];

      return $form;
    }

    // Pipeline information display.
    $this->buildPipelineInfo($form, $pipeline);

    // Pipeline operations based on current status.
    $this->buildPipelineOperations($form, $form_state, $pipeline);

    // Pipeline monitoring.
    $this->buildPipelineMonitoring($form, $pipeline);

    return $form;
  }

  /**
   * Builds the pipeline information section.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline entity.
   */
  protected function buildPipelineInfo(array &$form, FlowDropPipelineInterface $pipeline): void {
    $job_counts = $pipeline->calculateJobCounts();
    $status = $pipeline->getStatus();

    $form["pipeline_info"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Pipeline Information"),
      "#collapsible" => FALSE,
    ];

    $form["pipeline_info"]["info_table"] = [
      "#type" => "table",
      "#header" => [$this->t("Property"), $this->t("Value")],
      "#rows" => [
        [$this->t("ID"), $pipeline->id()],
        [$this->t("Label"), $pipeline->label()],
        [$this->t("Status"), ucfirst($status)],
        [$this->t("Total Jobs"), $job_counts["total"]],
        [$this->t("Pending Jobs"), $job_counts["pending"]],
        [$this->t("Running Jobs"), $job_counts["running"]],
        [$this->t("Completed Jobs"), $job_counts["completed"]],
        [$this->t("Failed Jobs"), $job_counts["failed"]],
        [$this->t("Cancelled Jobs"), $job_counts["cancelled"]],
        [$this->t("Max Concurrent Jobs"), $pipeline->getMaxConcurrentJobs()],
        [$this->t("Retry Strategy"), ucfirst($pipeline->getRetryStrategy())],
        [
          $this->t("Started"),
          $pipeline->getStarted() ?
          $this->dateFormatter->format($pipeline->getStarted(), "short") :
          $this->t("Not started"),
        ],
        [
          $this->t("Completed"),
          $pipeline->getCompleted() ?
          $this->dateFormatter->format($pipeline->getCompleted(), "short") :
          $this->t("Not completed"),
        ],
      ],
    ];

    if ($pipeline->getErrorMessage()) {
      $form["pipeline_info"]["error_message"] = [
        "#type" => "details",
        "#title" => $this->t("Error Information"),
        "#open" => TRUE,
        "message" => [
          "#type" => "textarea",
          "#value" => $pipeline->getErrorMessage(),
          "#disabled" => TRUE,
          "#rows" => 3,
        ],
      ];
    }
  }

  /**
   * Builds the pipeline operations section.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline entity.
   */
  protected function buildPipelineOperations(array &$form, FormStateInterface $form_state, FlowDropPipelineInterface $pipeline): void {
    $status = $pipeline->getStatus();

    $form["operations"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Pipeline Operations"),
      "#collapsible" => FALSE,
    ];

    // Store pipeline in form state for operations.
    $form_state->set("pipeline", $pipeline);

    // Available operations based on status.
    $operations = [];

    switch ($status) {
      case "pending":
        $operations["start"] = [
          "label" => $this->t("Start Pipeline"),
          "description" => $this->t("Begin pipeline execution"),
          "class" => "button--primary",
        ];
        $operations["cancel"] = [
          "label" => $this->t("Cancel Pipeline"),
          "description" => $this->t("Cancel pipeline before execution"),
          "class" => "button--danger",
        ];
        break;

      case "running":
        $operations["pause"] = [
          "label" => $this->t("Pause Pipeline"),
          "description" => $this->t("Pause pipeline execution"),
          "class" => "button--warning",
        ];
        $operations["cancel"] = [
          "label" => $this->t("Cancel Pipeline"),
          "description" => $this->t("Cancel running pipeline"),
          "class" => "button--danger",
        ];
        $operations["reevaluate"] = [
          "label" => $this->t("Reevaluate Pipeline"),
          "description" => $this->t("Force reevaluation of job dependencies"),
          "class" => "button",
        ];
        break;

      case "paused":
        $operations["resume"] = [
          "label" => $this->t("Resume Pipeline"),
          "description" => $this->t("Resume paused pipeline"),
          "class" => "button--primary",
        ];
        $operations["cancel"] = [
          "label" => $this->t("Cancel Pipeline"),
          "description" => $this->t("Cancel paused pipeline"),
          "class" => "button--danger",
        ];
        break;

      case "failed":
        $operations["retry"] = [
          "label" => $this->t("Retry Pipeline"),
          "description" => $this->t("Retry failed pipeline from last checkpoint"),
          "class" => "button--primary",
        ];
        $operations["reset"] = [
          "label" => $this->t("Reset Pipeline"),
          "description" => $this->t("Reset pipeline to pending state"),
          "class" => "button",
        ];
        break;

      case "completed":
        $operations["restart"] = [
          "label" => $this->t("Restart Pipeline"),
          "description" => $this->t("Start pipeline execution from beginning"),
          "class" => "button",
        ];
        break;

      case "cancelled":
        $operations["restart"] = [
          "label" => $this->t("Restart Pipeline"),
          "description" => $this->t("Start pipeline execution from beginning"),
          "class" => "button",
        ];
        break;
    }

    // Generate jobs operation (always available for debugging).
    $operations["generate_jobs"] = [
      "label" => $this->t("Generate Jobs"),
      "description" => $this->t("(Re)generate jobs from workflow definition"),
      "class" => "button--secondary",
    ];

    // Clear jobs operation (always available for debugging).
    $operations["clear_jobs"] = [
      "label" => $this->t("Clear Jobs"),
      "description" => $this->t("Remove all jobs from this pipeline"),
      "class" => "button--danger",
    ];

    foreach ($operations as $operation => $config) {
      $form["operations"][$operation] = [
        "#type" => "submit",
        "#value" => $config["label"],
        "#name" => $operation,
        "#submit" => ["::operationSubmit"],
        "#attributes" => [
          "class" => [$config["class"]],
          "title" => $config["description"],
        ],
      ];

      // Add description as help text.
      $form["operations"][$operation . "_help"] = [
        "#type" => "item",
        "#markup" => '<small>' . $config["description"] . '</small>',
      ];
    }

    // Execution mode selection for start/restart operations.
    if (in_array($status, ["pending", "failed", "completed", "cancelled"], TRUE)) {
      $form["operations"]["execution_mode"] = [
        "#type" => "radios",
        "#title" => $this->t("Execution Mode"),
        "#description" => $this->t("Choose how to execute the pipeline."),
        "#options" => [
          "asynchronous" => $this->t("Asynchronous (Queue-based, recommended)"),
          "synchronous" => $this->t("Synchronous (Direct execution, for testing)"),
        ],
        "#default_value" => "asynchronous",
        "#weight" => -10,
      ];
    }
  }

  /**
   * Builds the pipeline monitoring section.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline entity.
   */
  protected function buildPipelineMonitoring(array &$form, FlowDropPipelineInterface $pipeline): void {
    $form["monitoring"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Pipeline Monitoring"),
      "#collapsible" => TRUE,
      "#collapsed" => FALSE,
    ];

    // Jobs status table.
    $jobs = $pipeline->getJobs();
    if (!empty($jobs)) {
      $form["monitoring"]["jobs_table"] = [
        "#type" => "table",
        "#header" => [
          $this->t("Job"),
          $this->t("Node ID"),
          $this->t("Status"),
          $this->t("Priority"),
          $this->t("Started"),
          $this->t("Completed"),
          $this->t("Error"),
        ],
        "#empty" => $this->t("No jobs found."),
      ];

      foreach ($jobs as $job) {
        $row = [];
        $row["label"] = ["#markup" => $job->label()];
        $row["node_id"] = ["#markup" => $job->getNodeId()];
        $row["status"] = ["#markup" => ucfirst($job->getStatus())];
        $row["priority"] = ["#markup" => $job->getPriority()];
        $row["started"] = [
          "#markup" => $job->getStarted() ?
          $this->dateFormatter->format($job->getStarted(), "short") :
          "-",
        ];
        $row["completed"] = [
          "#markup" => $job->getCompleted() ?
          $this->dateFormatter->format($job->getCompleted(), "short") :
          "-",
        ];
        $row["error"] = [
          "#markup" => $job->getErrorMessage() ?
          substr($job->getErrorMessage(), 0, 50) . "..." :
          "-",
        ];

        $form["monitoring"]["jobs_table"][$job->id()] = $row;
      }
    }

    // Quick actions.
    $form["monitoring"]["actions"] = [
      "#type" => "actions",
    ];

    $form["monitoring"]["actions"]["refresh"] = [
      "#type" => "submit",
      "#value" => $this->t("Refresh"),
      "#submit" => ["::refreshSubmit"],
      "#attributes" => ["class" => ["button--small"]],
    ];

    $form["monitoring"]["actions"]["view_jobs"] = [
      "#type" => "link",
      "#title" => $this->t("View All Jobs"),
      "#url" => Url::fromRoute("entity.flowdrop_pipeline.jobs", [
        "flowdrop_pipeline" => $pipeline->id(),
      ]),
      "#attributes" => ["class" => ["button", "button--small"]],
    ];

    // Auto-refresh checkbox.
    $form["monitoring"]["auto_refresh"] = [
      "#type" => "checkbox",
      "#title" => $this->t("Auto-refresh every 30 seconds"),
      "#default_value" => FALSE,
      "#description" => $this->t("Automatically refresh this page to show updates."),
      "#attached" => [
        "library" => ["flowdrop_runtime/pipeline-monitor"],
      ],
    ];
  }

  /**
   * AJAX callback for loading pipeline operations.
   */
  public function loadPipelineCallback(array &$form, FormStateInterface $form_state): array {
    return $form["operations"];
  }

  /**
   * Submit handler for loading a pipeline.
   */
  public function loadPipelineSubmit(array &$form, FormStateInterface $form_state): void {
    $pipeline_id = $form_state->getValue("pipeline_id");
    if ($pipeline_id) {
      $form_state->setRedirect(
        "flowdrop_runtime.pipeline_interaction",
        [],
        ["query" => ["pipeline_id" => $pipeline_id]]
      );
    }
  }

  /**
   * Submit handler for pipeline operations.
   */
  public function operationSubmit(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getTriggeringElement()["#name"];
    $pipeline = $form_state->get("pipeline");

    if (!$pipeline instanceof FlowDropPipelineInterface) {
      $this->messenger->addError($this->t("Pipeline not found."));
      return;
    }

    $execution_mode = $form_state->getValue("execution_mode", "asynchronous");

    try {
      switch ($operation) {
        case "start":
          $this->startPipeline($pipeline, $execution_mode);
          break;

        case "pause":
          $this->pausePipeline($pipeline);
          break;

        case "resume":
          $this->resumePipeline($pipeline);
          break;

        case "cancel":
          $this->cancelPipeline($pipeline);
          break;

        case "retry":
        case "restart":
          $this->restartPipeline($pipeline, $execution_mode);
          break;

        case "reset":
          $this->resetPipeline($pipeline);
          break;

        case "reevaluate":
          $this->reevaluatePipeline($pipeline);
          break;

        case "generate_jobs":
          $this->generateJobs($pipeline);
          break;

        case "clear_jobs":
          $this->clearJobs($pipeline);
          break;

        default:
          $this->messenger->addError($this->t("Unknown operation: @op", ["@op" => $operation]));
          return;
      }

      $this->logger->info("Pipeline operation @op completed for pipeline @id", [
        "@op" => $operation,
        "@id" => $pipeline->id(),
      ]);

    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t("Operation failed: @message", [
        "@message" => $e->getMessage(),
      ]));
      $this->logger->error("Pipeline operation @op failed for pipeline @id: @message", [
        "@op" => $operation,
        "@id" => $pipeline->id(),
        "@message" => $e->getMessage(),
      ]);
    }

    // Rebuild form to show updated status.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for refreshing the form.
   */
  public function refreshSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Main submit handler - most operations use operationSubmit instead.
  }

  /**
   * Starts a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to start.
   * @param string $execution_mode
   *   The execution mode (asynchronous or synchronous).
   */
  protected function startPipeline(FlowDropPipelineInterface $pipeline, string $execution_mode): void {
    // Check if pipeline has jobs.
    $jobs = $pipeline->getJobs();
    if (empty($jobs)) {
      throw new \RuntimeException("Pipeline has no jobs. Please generate jobs first using the 'Generate Jobs' button.");
    }

    if ($execution_mode === "asynchronous") {
      if ($this->asynchronousOrchestrator->startPipeline($pipeline)) {
        $this->messenger->addStatus($this->t("Pipeline @label started successfully in asynchronous mode with @job_count jobs.", [
          "@label" => $pipeline->label(),
          "@job_count" => count($jobs),
        ]));
      }
      else {
        throw new \RuntimeException("Failed to start pipeline asynchronously");
      }
    }
    else {
      // Synchronous execution.
      try {
        $response = $this->synchronousOrchestrator->executePipeline($pipeline);

        $this->messenger->addStatus($this->t("Pipeline @label executed successfully in @time seconds. Status: @status", [
          "@label" => $pipeline->label(),
          "@time" => round($response->getExecutionTime(), 2),
          "@status" => $response->getStatus(),
        ]));

        if ($response->getStatus() === 'failed') {
          $this->messenger->addWarning($this->t("Some jobs may have failed. Check the job details below."));
        }
      }
      catch (\Exception $e) {
        throw new \RuntimeException("Synchronous execution failed: " . $e->getMessage());
      }
    }
  }

  /**
   * Pauses a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to pause.
   */
  protected function pausePipeline(FlowDropPipelineInterface $pipeline): void {
    $pipeline->pause();
    $pipeline->save();
    $this->messenger->addStatus($this->t("Pipeline @label paused.", [
      "@label" => $pipeline->label(),
    ]));
  }

  /**
   * Resumes a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to resume.
   */
  protected function resumePipeline(FlowDropPipelineInterface $pipeline): void {
    $pipeline->resume();
    $pipeline->save();

    // Queue for execution.
    $queue = $this->queueFactory->get("flowdrop_runtime_pipeline_execution");
    $queue->createItem([
      "pipeline_id" => $pipeline->id(),
      "action" => "execute",
    ]);

    $this->messenger->addStatus($this->t("Pipeline @label resumed and queued for execution.", [
      "@label" => $pipeline->label(),
    ]));
  }

  /**
   * Cancels a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to cancel.
   */
  protected function cancelPipeline(FlowDropPipelineInterface $pipeline): void {
    $pipeline->markAsCancelled();
    $pipeline->save();
    $this->messenger->addStatus($this->t("Pipeline @label cancelled.", [
      "@label" => $pipeline->label(),
    ]));
  }

  /**
   * Restarts a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to restart.
   * @param string $execution_mode
   *   The execution mode.
   */
  protected function restartPipeline(FlowDropPipelineInterface $pipeline, string $execution_mode): void {
    // Reset pipeline to pending state.
    $this->resetPipeline($pipeline);

    // Start the pipeline.
    $this->startPipeline($pipeline, $execution_mode);
  }

  /**
   * Resets a pipeline to pending state.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to reset.
   */
  protected function resetPipeline(FlowDropPipelineInterface $pipeline): void {
    $pipeline->setStatus("pending");
    $pipeline->setStarted(0);
    $pipeline->setCompleted(0);
    $pipeline->setErrorMessage("");
    $pipeline->save();

    // Reset all jobs to pending.
    $jobs = $pipeline->getJobs();
    foreach ($jobs as $job) {
      $job->setStatus("pending");
      $job->setStarted(0);
      $job->setCompleted(0);
      $job->setErrorMessage("");
      $job->setRetryCount(0);
      $job->save();
    }

    $this->messenger->addStatus($this->t("Pipeline @label reset to pending state.", [
      "@label" => $pipeline->label(),
    ]));
  }

  /**
   * Reevaluates a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to reevaluate.
   */
  protected function reevaluatePipeline(FlowDropPipelineInterface $pipeline): void {
    $queue = $this->queueFactory->get("flowdrop_runtime_pipeline_execution");
    $queue->createItem([
      "pipeline_id" => $pipeline->id(),
      "action" => "reevaluate",
    ]);

    $this->messenger->addStatus($this->t("Pipeline @label queued for reevaluation.", [
      "@label" => $pipeline->label(),
    ]));
  }

  /**
   * Generates jobs for a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to generate jobs for.
   */
  protected function generateJobs(FlowDropPipelineInterface $pipeline): void {
    $this->jobGenerationService->generateJobs($pipeline);

    $this->messenger->addStatus($this->t("Jobs generated for pipeline @label.", [
      "@label" => $pipeline->label(),
    ]));
  }

  /**
   * Clears jobs for a pipeline.
   *
   * @param \Drupal\flowdrop_pipeline\Entity\FlowDropPipelineInterface $pipeline
   *   The pipeline to clear jobs for.
   */
  protected function clearJobs(FlowDropPipelineInterface $pipeline): void {
    $this->jobGenerationService->clearJobs($pipeline);

    $this->messenger->addStatus($this->t("Jobs cleared for pipeline @label.", [
      "@label" => $pipeline->label(),
    ]));
  }

}
