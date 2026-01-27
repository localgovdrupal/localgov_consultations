<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a consultations banner image block.
 *
 * @Block(
 * id = "localgov_consultations_landing_banner",
 * admin_label = @Translation("LocalGov Consultations Landing Banner"),
 * category = @Translation("LocalGov Consultations"),
 * )
 */
final class LandingBannerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected FileUsageInterface $fileUsage;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Constructs a LandingBannerBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    FileUsageInterface $file_usage,
    ModuleExtensionList $module_extension_list,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUsage = $file_usage;
    $this->moduleExtensionList = $module_extension_list;
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
      $container->get('file.usage'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'image_fid' => NULL,
      'alt_text' => $this->t('LocalGov Consultations banner'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Banner image'),
      '#description' => $this->t('Upload an image file. If empty, the module default will be used. Allowed types: PNG, JPG, JPEG, GIF, WebP.'),
      '#upload_location' => 'public://banner-images/',
      '#default_value' => !empty($this->configuration['image_fid']) ? [$this->configuration['image_fid']] : NULL,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif webp'],
    // 2MB
        'file_validate_size' => [2097152],
        'file_validate_is_image' => [],
      ],
      '#required' => FALSE,
    ];

    $form['alt_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative text'),
      '#description' => $this->t('Alternative text for accessibility.'),
      '#default_value' => $this->configuration['alt_text'] ?? '',
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $image_values = $form_state->getValue('image');
    $new_fid = !empty($image_values[0]) ? (int) $image_values[0] : NULL;
    $old_fid = !empty($this->configuration['image_fid']) ? (int) $this->configuration['image_fid'] : NULL;

    $file_storage = $this->entityTypeManager->getStorage('file');
    $file_usage = $this->fileUsage;

    if ($new_fid !== $old_fid) {
      if ($old_fid) {
        $old_file = $file_storage->load($old_fid);
        if ($old_file) {
          $file_usage->delete($old_file, 'localgov_consultations', 'block', 1);
        }
      }

      if ($new_fid) {
        $new_file = $file_storage->load($new_fid);
        if ($new_file) {
          $new_file->setPermanent();
          $new_file->save();
          $file_usage->add($new_file, 'localgov_consultations', 'block', 1);
        }
      }
    }

    $this->configuration['image_fid'] = $new_fid;
    $this->configuration['alt_text'] = $form_state->getValue('alt_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $file = !empty($this->configuration['image_fid']) ? $this->entityTypeManager->getStorage('file')->load($this->configuration['image_fid']) : NULL;

    if ($file) {
      $image_uri = $file->getFileUri();
      $cache_tags = $file->getCacheTags();
    }
    else {
      // Fallback to module's internal image.
      $module_path = $this->moduleExtensionList->getPath('localgov_consultations');
      $image_uri = $module_path . '/images/localgov_consultations_demo_image.jpg';
      $cache_tags = [];
    }

    $alt_text = !empty($this->configuration['alt_text'])
      ? $this->configuration['alt_text']
      : $this->t('LocalGov Consultations banner');

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['localgov-consultations--banner-block-wrapper']],
    ];

    $build['banner_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['localgov-consultations--banner-image']],
      'banner_image' => [
        '#theme' => 'image',
        '#uri' => $image_uri,
        '#alt' => $alt_text,
        '#cache' => ['tags' => $cache_tags],
      ],
    ];

    $view = Views::getView('consultations');
    if ($view) {
      $view->setDisplay('open_consultations');
      $view->initHandlers();
      $exposed_form = $view->display_handler->viewExposedFormBlocks();

      if (!empty($exposed_form)) {
        $build['exposed_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['localgov-consultations--filter-block', 'lgd-container', 'padding-horizontal'],
          ],
          'exposed_filter' => $exposed_form,
        ];
      }
      $build['#cache']['contexts'][] = 'url.query_args';
    }

    return $build;
  }

}
