<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Manages automatic status updates for consultation nodes.
 */
final class StatusManager {

  /**
   * Constructs a StatusManager object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Update consultation statuses based on their dates.
   *
   * This method checks all consultations and updates their status field:
   * - 'upcoming' -> 'open' if the start date has passed
   * - 'open' -> 'closed' if the end date has passed
   *
   * @return array
   *   An array containing counts of opened and closed consultations.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function updateConsultationStatuses(): array {
    $opened_count = 0;
    $closed_count = 0;
    $logger = $this->loggerFactory->get('localgov_consultations');

    $now = new DrupalDateTime('now', \Drupal::config('system.date')->get('timezone')['default']);
    $now_formatted = $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Find consultations that should be opened (status=upcoming and start date has passed).
    $to_open_query = $this->entityTypeManager->getStorage('node')->getQuery();
    $to_open_query
      ->accessCheck(FALSE)
      ->condition('type', 'consultation')
      ->condition('status', 1)
      ->condition('localgov_consultation_status', 'upcoming')
      ->condition('localgov_consultation_date.value', $now_formatted, '<=');

    $to_open_ids = $to_open_query->execute();

    if (!empty($to_open_ids)) {
      $consultations_to_open = $this->entityTypeManager->getStorage('node')->loadMultiple($to_open_ids);
      foreach ($consultations_to_open as $consultation) {
        $consultation->set('localgov_consultation_status', 'open');
        $consultation->save();
        $opened_count++;
        $logger->info('Consultation @nid "@title" automatically opened.', [
          '@nid' => $consultation->id(),
          '@title' => $consultation->label(),
        ]);
      }
    }

    // Find consultations that should be closed (status=open and end date has passed).
    $to_close_query = $this->entityTypeManager->getStorage('node')->getQuery();
    $to_close_query
      ->accessCheck(FALSE)
      ->condition('type', 'consultation')
      ->condition('status', 1)
      ->condition('localgov_consultation_status', 'open')
      ->condition('localgov_consultation_date.end_value', $now_formatted, '<=');

    $to_close_ids = $to_close_query->execute();

    if (!empty($to_close_ids)) {
      $consultations_to_close = $this->entityTypeManager->getStorage('node')->loadMultiple($to_close_ids);
      foreach ($consultations_to_close as $consultation) {
        $consultation->set('localgov_consultation_status', 'closed');
        $consultation->save();
        $closed_count++;
        $logger->info('Consultation @nid "@title" automatically closed.', [
          '@nid' => $consultation->id(),
          '@title' => $consultation->label(),
        ]);
      }
    }

    return [
      'opened' => $opened_count,
      'closed' => $closed_count,
    ];
  }

}
