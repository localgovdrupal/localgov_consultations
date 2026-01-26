<?php

namespace Drupal\localgov_consultations\Commands;

use Drupal\localgov_consultations\StatusManager;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for consultation management.
 *
 * @package Drupal\localgov_consultations\Commands
 */
class ConsultationCommands extends DrushCommands {

  /**
   * The status manager service.
   *
   * @var \Drupal\localgov_consultations\StatusManager
   */
  protected $statusManager;

  /**
   * Constructs a ConsultationCommands object.
   *
   * @param \Drupal\localgov_consultations\StatusManager $status_manager
   *   The status manager service.
   */
  public function __construct(StatusManager $status_manager) {
    parent::__construct();
    $this->statusManager = $status_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('localgov_consultations.status_manager')
    );
  }

  /**
   * Update consultation statuses based on their dates.
   *
   * @command localgov_consultations:update-statuses
   * @aliases lgc-update-statuses
   * @usage localgov_consultations:update-statuses
   *   Updates consultation statuses based on their start and end dates.
   */
  public function updateStatuses() {
    $this->output()->writeln('Checking consultation statuses...');

    $results = $this->statusManager->updateConsultationStatuses();

    $this->output()->writeln(sprintf(
      'Consultations opened: %d',
      $results['opened']
    ));
    $this->output()->writeln(sprintf(
      'Consultations closed: %d',
      $results['closed']
    ));

    if ($results['opened'] > 0 || $results['closed'] > 0) {
      $this->output()->writeln('Status updates complete.');
    }
    else {
      $this->output()->writeln('No status updates required.');
    }
  }

}
