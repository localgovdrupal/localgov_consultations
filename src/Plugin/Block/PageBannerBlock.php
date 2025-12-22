<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations\Plugin\Block;

use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a localgov consultations page banner block.
 *
 * @Block(
 *   id = "localgov_consultations_page_banner",
 *   admin_label = @Translation("LocalGov Consultations Page Banner"),
 *   category = @Translation("LocalGov Consultations"),
 * )
 */
final class PageBannerBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new LandingBannerBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    // Get the current node from route.
    $node = $this->routeMatch->getParameter('node');

    // Check if node exists, has the field, and field is not empty.
    if (!$node ||
      !$node->hasField('field_landing_banner') ||
      $node->get('field_landing_banner')->isEmpty()) {
      return $build;
    }

    // Load the media entity referenced by the banner media field.
    $media_entities = $node->get('field_landing_banner')->referencedEntities();

    // Check if there are any media entities.
    if (empty($media_entities)) {
      return $build;
    }

    // Get the first media entity (assuming only one media entity per node).
    $media_entity = reset($media_entities);

    // Render the media entity with the responsive_banner view mode.
    $view_builder = $this->entityTypeManager->getViewBuilder('media');
    $landing_banner_media = $view_builder->view($media_entity, 'responsive_banner');

    // Build the output with wrapper.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['banner-placeholder__image'],
      ],
      'media' => $landing_banner_media,
      '#cache' => [
        'tags' => Cache::mergeTags(
          $node->getCacheTags(),
          $media_entity->getCacheTags()
        ),
        'contexts' => ['route'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Cache per URL (since content depends on which node is being viewed).
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();

    // Add the current node's cache tags.
    $node = $this->routeMatch->getParameter('node');
    if ($node) {
      $tags = Cache::mergeTags($tags, $node->getCacheTags());
    }

    return $tags;
  }
}
