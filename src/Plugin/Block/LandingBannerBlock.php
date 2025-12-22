<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations\Plugin\Block;

use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Provides a consultations banner image block.
 *
 * @Block(
 *   id = "localgov_consutlations_landing_banner",
 *   admin_label = @Translation("LocalGov Consultations Landing Banner"),
 *   category = @Translation("LocalGov Consultations"),
 * )
 */
final class LandingBannerBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'image_fid' => NULL,
      'alt_text' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Banner image'),
      '#description' => $this->t('Upload an image file. Maximum size: 2MB. Allowed types: PNG, JPG, JPEG, GIF, WebP.'),
      '#upload_location' => 'public://banner-images/',
      '#default_value' => !empty($this->configuration['image_fid']) ? [$this->configuration['image_fid']] : NULL,
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif webp'],
        'file_validate_size' => [2097152], // 2MB in bytes
        'file_validate_is_image' => [],
      ],
      '#required' => TRUE,
    ];

    $form['alt_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative text'),
      '#description' => $this->t('Alternative text for the image (for accessibility).'),
      '#default_value' => $this->configuration['alt_text'] ?? '',
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $image = $form_state->getValue('image');

    // Remove old file usage if exists.
    if (!empty($this->configuration['image_fid'])) {
      $old_file = File::load($this->configuration['image_fid']);
      if ($old_file) {
        \Drupal::service('file.usage')->delete($old_file, 'localgov_consultations', 'block', 1);
      }
    }

    if (!empty($image[0])) {
      $file = File::load($image[0]);
      if ($file) {
        // Sanitize filename to prevent security issues.
        $filename = $file->getFilename();
        $sanitized_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        if ($filename !== $sanitized_filename) {
          $file->setFilename($sanitized_filename);
        }

        // Make file permanent and track usage.
        $file->setPermanent();
        $file->save();

        // Track file usage to prevent deletion.
        \Drupal::service('file.usage')->add($file, 'localgov_consultations', 'block', 1);

        $this->configuration['image_fid'] = $image[0];
      }
    }

    $this->configuration['alt_text'] = $form_state->getValue('alt_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    if (empty($this->configuration['image_fid'])) {
      return [];
    }

    $file = File::load($this->configuration['image_fid']);
    if (!$file) {
      return [];
    }

    // Create wrapper container for banner and filter.
    $build['#type'] = 'container';
    $build['#attributes'] = [
      'class' => ['localgov-consultations--banner-block-wrapper'],
    ];

    // Banner image.
    $build['banner_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['localgov-consultations--banner-image'],
      ],
      'banner_image' => [
        '#theme' => 'image',
        '#uri' => $file->getFileUri(),
        '#alt' => $this->configuration['alt_text'] ?? '',
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ],
    ];

    // Add exposed filter block below the image.
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('views_exposed_filter_block:consultations-open_consultations', []);

    if ($plugin_block) {
      $access_result = $plugin_block->access(\Drupal::currentUser(), TRUE);
      if ($access_result->isAllowed()) {
        $build['filter_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['localgov-consultations--filter-block', 'lgd-container', 'padding-horizontal'],
          ],
          'exposed_filter' => $plugin_block->build(),
        ];
      }
    }

    return $build;
  }
}
