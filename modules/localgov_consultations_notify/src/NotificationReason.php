<?php

namespace Drupal\localgov_consultations_notify;

/**
 * Reasons for sending a notification.
 */
enum NotificationReason {
  case ConsultationClosing;
  case ConsultationClosed;
  case ConsultationDatesChanged;
  case ConsultationOpened;
}