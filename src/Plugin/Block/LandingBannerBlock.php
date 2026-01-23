<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\views\Views;

/**
 * Provides a consultations banner image block.
 *
 * @Block(
 * id = "localgov_consultations_landing_banner",
 * admin_label = @Translation("LocalGov Consultations Landing Banner"),
 * category = @Translation("LocalGov Consultations"),
 * )
 */
final class LandingBannerBlock extends BlockBase {

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

    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $file_usage = \Drupal::service('file.usage');

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
    $file = !empty($this->configuration['image_fid']) ? File::load($this->configuration['image_fid']) : NULL;

    if ($file) {
      $image_uri = $file->getFileUri();
      $cache_tags = $file->getCacheTags();
    }
    else {
      // Fallback to module's internal image.
      $module_path = \Drupal::service('extension.list.module')->getPath('localgov_consultations');
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
