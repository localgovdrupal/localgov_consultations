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
 *   id = "localgov_consultations_landing_banner",
 *   admin_label = @Translation("LocalGov Consultations Landing Banner"),
 *   category = @Translation("LocalGov Consultations"),
 * )
 */
final class LandingBannerBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_fid = \Drupal::state()->get('localgov_consultations.default_image_fid', NULL);
    $default_alt = \Drupal::state()->get('localgov_consultations.default_alt_text', $this->t('Localgov Consultations banner'));

    return [
      'image_fid' => $default_fid,
      'alt_text' => $default_alt,
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
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Get values from form state.
    $image_values = $form_state->getValue('image');
    $new_fid = !empty($image_values[0]) ? (int) $image_values[0] : NULL;
    $old_fid = !empty($this->configuration['image_fid']) ? (int) $this->configuration['image_fid'] : NULL;

    // Use entity type manager for storage.
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $file_usage = \Drupal::service('file.usage');

    // Handle File Usage if the image has changed.
    if ($new_fid !== $old_fid) {

      // 1. Remove usage from the old file.
      if ($old_fid) {
        $old_file = $file_storage->load($old_fid);
        if ($old_file) {
          // decrement usage.
          $file_usage->delete($old_file, 'localgov_consultations', 'block', 1);
        }
      }

      // 2. Add usage to the new file.
      if ($new_fid) {
        /** @var \Drupal\file\FileInterface $new_file */
        $new_file = $file_storage->load($new_fid);
        if ($new_file) {
          // In 10.3+, files uploaded via managed_file are temporary by default.
          // Setting permanent and adding usage prevents auto-deletion.
          $new_file->setPermanent();
          $new_file->save();
          $file_usage->add($new_file, 'localgov_consultations', 'block', 1);
        }
      }
    }

    // Save updated configuration.
    $this->configuration['image_fid'] = $new_fid;
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
