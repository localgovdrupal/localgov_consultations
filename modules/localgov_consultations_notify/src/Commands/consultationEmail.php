<?php

namespace Drupal\localgov_consultations_notify\Commands;

use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\localgov_consultations_notify\Commands
 */
class consultationEmail extends DrushCommands {

  /**
   * Drush command to do consultation email processing.
   *
   * @command localgov_consultations:process-email
   * @usage localgov_consultations:process-email
   */
  public function localgov_consultation_email() {
    \Drupal::service('localgov_consultations_notify.notifier')->processStateChanges();
  }

}
