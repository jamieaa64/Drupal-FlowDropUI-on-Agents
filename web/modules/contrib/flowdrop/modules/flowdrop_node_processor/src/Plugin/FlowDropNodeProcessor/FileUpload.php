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
 * Executor for File Upload nodes.
 */
#[FlowDropNodeProcessor(
  id: "file_upload",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("File Upload"),
  type: "default",
  supportedTypes: ["default"],
  category: "input",
  description: "File upload handling",
  version: "1.0.0",
  tags: ["file", "upload", "input"]
)]
class FileUpload extends AbstractFlowDropNodeProcessor {

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
    $directory = $config->getConfig('directory', 'public://flowdrop/uploads/');
    $allowedExtensions = $config->getConfig('allowedExtensions', ['txt', 'pdf', 'doc', 'docx']);
    // 10MB
    $maxFileSize = $config->getConfig('maxFileSize', 10485760);
    $overwrite = $config->getConfig('overwrite', FALSE);

    $uploadedFiles = [];
    $errors = [];

    // Get the file data from inputs.
    $files = $inputs->get('files') ?: [];

    foreach ($files as $fileData) {
      if (!is_array($fileData) || !isset($fileData['name']) || !isset($fileData['content'])) {
        $errors[] = 'Invalid file data format';
        continue;
      }

      $filename = $fileData['name'];
      $content = $fileData['content'];
      $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

      // Validate file extension.
      if (!in_array($extension, $allowedExtensions)) {
        $errors[] = "File extension '{$extension}' not allowed";
        continue;
      }

      // Validate file size.
      if (strlen($content) > $maxFileSize) {
        $errors[] = "File '{$filename}' exceeds maximum size";
        continue;
      }

      // Ensure directory exists.
      $directory = rtrim($directory, '/') . '/';
      if (!is_dir($directory)) {
        mkdir($directory, 0755, TRUE);
      }

      // Generate unique filename if overwrite is disabled.
      $filepath = $directory . $filename;
      if (!$overwrite && file_exists($filepath)) {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 1;
        do {
          $newFilename = $name . '_' . $counter . '.' . $extension;
          $filepath = $directory . $newFilename;
          $counter++;
        } while (file_exists($filepath));
      }

      // Save the file.
      if (file_put_contents($filepath, $content) !== FALSE) {
        $uploadedFiles[] = [
          'filename' => basename($filepath),
          'filepath' => $filepath,
          'size' => strlen($content),
          'extension' => $extension,
        ];
      }
      else {
        $errors[] = "Failed to save file '{$filename}'";
      }
    }

    $this->getLogger()->info('File upload executed successfully', [
      'files_uploaded' => count($uploadedFiles),
      'errors_count' => count($errors),
      'directory' => $directory,
    ]);

    return [
      'uploaded_files' => $uploadedFiles,
      'errors' => $errors,
      'directory' => $directory,
      'total_files' => count($uploadedFiles),
      'total_errors' => count($errors),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateInputs(array $inputs): bool {
    // File upload nodes can accept any inputs or none.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'uploaded_files' => [
          'type' => 'array',
          'description' => 'The uploaded files information',
        ],
        'errors' => [
          'type' => 'array',
          'description' => 'Any errors that occurred',
        ],
        'directory' => [
          'type' => 'string',
          'description' => 'The upload directory',
        ],
        'total_files' => [
          'type' => 'integer',
          'description' => 'Total number of files uploaded',
        ],
        'total_errors' => [
          'type' => 'integer',
          'description' => 'Total number of errors',
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
        'directory' => [
          'type' => 'string',
          'title' => 'Directory',
          'description' => 'Directory to upload files to',
          'default' => 'public://flowdrop/uploads/',
        ],
        'allowedExtensions' => [
          'type' => 'array',
          'title' => 'Allowed Extensions',
          'description' => 'Allowed file extensions',
          'default' => ['txt', 'pdf', 'doc', 'docx'],
        ],
        'maxFileSize' => [
          'type' => 'integer',
          'title' => 'Max File Size',
          'description' => 'Maximum file size in bytes',
          'default' => 10485760,
        ],
        'overwrite' => [
          'type' => 'boolean',
          'title' => 'Overwrite',
          'description' => 'Whether to overwrite existing files',
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
        'files' => [
          'type' => 'array',
          'title' => 'Files',
          'description' => 'Array of files to upload with name and content',
          'required' => FALSE,
        ],
      ],
    ];
  }

}
