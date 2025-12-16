<?php

declare(strict_types=1);

namespace Drupal\tool_content_translation\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity translation get tool.
 */
#[Tool(
  id: 'entity_translation_get',
  label: new TranslatableMarkup('Get an entity translation'),
  description: new TranslatableMarkup('Load or create a translated version of an entity for a specific language.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity' => new InputDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      description: new TranslatableMarkup("The entity to get the translation for."),
    ),
    'langcode' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Language Code"),
      description: new TranslatableMarkup("The language code for the translation (e.g., 'es', 'fr', 'de')."),
    ),
  ],
  output_definitions: [
    'translated_entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Translated Entity"),
      description: new TranslatableMarkup("The entity in the requested language.")
    ),
  ],
)]
class EntityTranslationGet extends ToolBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The content translation manager service.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected ContentTranslationManagerInterface $contentTranslationManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->contentTranslationManager = $container->get('content_translation.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    ['entity' => $entity, 'langcode' => $langcode] = $values;

    // Validate that the entity is a content entity.
    if (!($entity instanceof ContentEntityInterface)) {
      return ExecutableResult::failure($this->t('The provided entity must be a content entity.'));
    }

    // Validate that the language code exists.
    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      return ExecutableResult::failure($this->t('Invalid language code "@langcode".', ['@langcode' => $langcode]));
    }

    // Check if the entity type supports translation.
    if (!$this->contentTranslationManager->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      return ExecutableResult::failure($this->t('Translation is not enabled for @type @bundle entities.', [
        '@type' => $entity->getEntityTypeId(),
        '@bundle' => $entity->bundle(),
      ]));
    }

    try {
      // Check if translation already exists.
      if ($entity->hasTranslation($langcode)) {
        $translated_entity = $entity->getTranslation($langcode);
        return ExecutableResult::success($this->t('Successfully loaded existing @langcode translation of @type entity @id.', [
          '@langcode' => $langcode,
          '@type' => $entity->getEntityTypeId(),
          '@id' => $entity->id(),
        ]), ['translated_entity' => $translated_entity]);
      }

      // Create a new translation.
      $translated_entity = $entity->addTranslation($langcode, $entity->toArray());

      // Save the entity to persist the new translation.
      $translated_entity->save();

      return ExecutableResult::success($this->t('Successfully created and loaded new @langcode translation of @type entity @id.', [
        '@langcode' => $langcode,
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]), ['translated_entity' => $translated_entity]);

    }
    catch (\Exception $e) {
      return ExecutableResult::failure($this->t('Error processing translation for @type entity @id: @message', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $entity = $values['entity'] ?? NULL;
    $langcode = $values['langcode'] ?? NULL;

    if (!$entity || !($entity instanceof ContentEntityInterface) || !$langcode) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    // Check if user can view the entity.
    $view_access = $entity->access('view', $account, TRUE);
    if (!$view_access->isAllowed()) {
      return $return_as_object ? $view_access : FALSE;
    }

    // Check if user can update the entity (needed for creating translations).
    $update_access = $entity->access('update', $account, TRUE);
    if (!$update_access->isAllowed()) {
      return $return_as_object ? $update_access : FALSE;
    }

    // Check translation permissions.
    $entity_type_id = $entity->getEntityTypeId();
    $translation_permission = "translate $entity_type_id entities";

    if ($account->hasPermission($translation_permission) || $account->hasPermission('administer content translation')) {
      return $return_as_object ? AccessResult::allowed() : TRUE;
    }

    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

}
