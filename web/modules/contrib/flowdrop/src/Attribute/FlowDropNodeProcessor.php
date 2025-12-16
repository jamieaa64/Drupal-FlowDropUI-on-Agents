<?php

declare(strict_types=1);

namespace Drupal\flowdrop\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Attribute for FlowDropNode plugins.
 *
 * This attribute provides LangflowComponent-equivalent metadata
 * for FlowDrop node processors.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class FlowDropNodeProcessor extends Plugin {

  /**
   * Constructs a new FlowDropNode attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable label.
   * @param string $type
   *   The node type for frontend rendering.
   * @param array $supportedTypes
   *   All supported node types.
   * @param string $category
   *   The component category (e.g., "inputs", "models", "tools").
   * @param string $description
   *   The component description.
   * @param string $version
   *   The component version.
   * @param array $inputs
   *   The input ports configuration.
   * @param array $outputs
   *   The output ports configuration.
   * @param array $config
   *   The component configuration schema.
   * @param array $tags
   *   The component tags.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $type,
    public readonly array $supportedTypes,
    public readonly string $category,
    public readonly string $description = "",
    public readonly string $version = "1.0.0",
    public readonly array $inputs = [],
    public readonly array $outputs = [],
    public readonly array $config = [],
    public readonly array $tags = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
