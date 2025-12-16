<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for managing AI models.
 */
class AiModelService {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Available AI models configuration.
   *
   * @var array
   */
  protected $models;

  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
    $this->initializeModels();
  }

  /**
   * Initialize available AI models.
   */
  protected function initializeModels(): void {
    $this->models = [
      'gpt-3.5-turbo' => [
        'id' => 'gpt-3.5-turbo',
        'name' => 'GPT-3.5 Turbo',
        'provider' => 'openai',
        'max_tokens' => 4096,
        'temperature_range' => [0.0, 2.0],
        'default_temperature' => 0.7,
        'supports_chat' => TRUE,
        'supports_completion' => TRUE,
      ],
      'gpt-4' => [
        'id' => 'gpt-4',
        'name' => 'GPT-4',
        'provider' => 'openai',
        'max_tokens' => 8192,
        'temperature_range' => [0.0, 2.0],
        'default_temperature' => 0.7,
        'supports_chat' => TRUE,
        'supports_completion' => TRUE,
      ],
      'claude-3-sonnet' => [
        'id' => 'claude-3-sonnet',
        'name' => 'Claude 3 Sonnet',
        'provider' => 'anthropic',
        'max_tokens' => 4096,
        'temperature_range' => [0.0, 1.0],
        'default_temperature' => 0.7,
        'supports_chat' => TRUE,
        'supports_completion' => FALSE,
      ],
    ];
  }

  /**
   * Get all available AI models.
   *
   * @return array
   *   Array of available AI models.
   */
  public function getAvailableModels(): array {
    return $this->models;
  }

  /**
   * Get a specific AI model by ID.
   *
   * @param string $model_id
   *   The model ID.
   *
   * @return array|null
   *   The model configuration or null if not found.
   */
  public function getModel(string $model_id): ?array {
    return $this->models[$model_id] ?? NULL;
  }

  /**
   * Check if a model supports chat functionality.
   *
   * @param string $model_id
   *   The model ID.
   *
   * @return bool
   *   TRUE if the model supports chat, FALSE otherwise.
   */
  public function supportsChat(string $model_id): bool {
    $model = $this->getModel($model_id);
    return $model ? $model['supports_chat'] : FALSE;
  }

  /**
   * Check if a model supports completion functionality.
   *
   * @param string $model_id
   *   The model ID.
   *
   * @return bool
   *   TRUE if the model supports completion, FALSE otherwise.
   */
  public function supportsCompletion(string $model_id): bool {
    $model = $this->getModel($model_id);
    return $model ? $model['supports_completion'] : FALSE;
  }

  /**
   * Get models by provider.
   *
   * @param string $provider
   *   The provider name.
   *
   * @return array
   *   Array of models from the specified provider.
   */
  public function getModelsByProvider(string $provider): array {
    return array_filter($this->models, function ($model) use ($provider) {
      return $model['provider'] === $provider;
    });
  }

  /**
   * Validate model configuration.
   *
   * @param array $config
   *   The model configuration to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validateModelConfig(array $config): bool {
    if (empty($config['model'])) {
      return FALSE;
    }

    $model = $this->getModel($config['model']);
    if (!$model) {
      return FALSE;
    }

    // Validate temperature.
    if (isset($config['temperature'])) {
      $temp_range = $model['temperature_range'];
      if ($config['temperature'] < $temp_range[0] || $config['temperature'] > $temp_range[1]) {
        return FALSE;
      }
    }

    // Validate max tokens.
    if (isset($config['max_tokens'])) {
      if ($config['max_tokens'] > $model['max_tokens']) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get default configuration for a model.
   *
   * @param string $model_id
   *   The model ID.
   *
   * @return array
   *   Default configuration for the model.
   */
  public function getDefaultConfig(string $model_id): array {
    $model = $this->getModel($model_id);
    if (!$model) {
      return [];
    }

    return [
      'model' => $model_id,
      'temperature' => $model['default_temperature'],
      'max_tokens' => $model['max_tokens'],
      'provider' => $model['provider'],
    ];
  }

}
