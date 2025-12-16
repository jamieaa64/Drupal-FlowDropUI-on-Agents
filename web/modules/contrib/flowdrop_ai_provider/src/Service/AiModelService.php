<?php

declare(strict_types=1);

namespace Drupal\flowdrop_ai_provider\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai\AiProviderPluginManager;

/**
 * Service for managing AI models.
 */
class AiModelService {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Available AI models configuration.
   *
   * @var array
   */
  protected array $models;

  public function __construct(LoggerChannelFactoryInterface $logger_factory, AiProviderPluginManager $ai_provider_manager) {
    $this->loggerFactory = $logger_factory;
    $this->aiProviderManager = $ai_provider_manager;
    $this->initializeModels();
  }

  /**
   * Initialize available AI models by querying all providers.
   */
  protected function initializeModels(): void {
    $this->models = [];

    // Get all available provider definitions.
    $provider_definitions = $this->aiProviderManager->getDefinitions();

    foreach ($provider_definitions as $provider_id => $provider_definition) {
      try {
        // Create provider instance.
        $provider = $this->aiProviderManager->createInstance($provider_id);

        // Check if provider supports chat operation type.
        $supported_operations = $provider->getSupportedOperationTypes();
        if (!in_array('chat', $supported_operations)) {
          continue;
        }

        // Get configured models for chat.
        $models = $provider->getConfiguredModels('chat');

        foreach ($models as $model_id => $model_name) {
          // Build model configuration.
          $this->models[$model_id] = [
            'id' => $model_id,
            'name' => $model_name,
            'provider' => $provider_id,
            'max_tokens' => 4096,
            'temperature_range' => [0.0, 1.0],
            'default_temperature' => 0.7,
            'supports_chat' => TRUE,
            'supports_completion' => TRUE,
          ];
        }
      }
      catch (\Exception $e) {
        // Log error but continue with other providers.
        $this->loggerFactory->get('flowdrop_ai_provider')->error(
          'Failed to load models from provider @provider: @error',
          ['@provider' => $provider_id, '@error' => $e->getMessage()]
        );
      }
    }
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

  /**
   * Get the default model ID for a given operation type.
   *
   * @param string $operation_type
   *   The operation type (e.g., 'chat', 'completion').
   *
   * @return string|null
   *   The default model ID or null if not configured.
   */
  public function getDefaultModelForOperationType(string $operation_type): ?string {
    $default_config = $this->aiProviderManager->getDefaultProviderForOperationType($operation_type);
    return $default_config['model_id'] ?? NULL;
  }

}
