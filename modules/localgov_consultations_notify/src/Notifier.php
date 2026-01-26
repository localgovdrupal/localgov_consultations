<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations_notify;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\localgov_consultations_notify\Plugin\QueueWorker\EmailQueue;
use Drupal\node\Entity\Node;

/**
 *
 */
enum NotificationReason {
  case ConsultationClosing;
  case ConsulationClosed;
  case ConsultationDatesChanged;
  case ConsultationOpened;
}


/**
 * Queue notifications based on consultations events.
 */
final class Notifier {

  /**
   * Constructs a Notifier object.
   */
  public function __construct(
    private readonly QueueFactory $queue,
  ) {}

  /**
   * Find all consultations that have opened/closed since last run. Called from e.g. cron.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function processStateChanges() : void {
    $this->process_opens();
    $this->process_closes();
  }

  /**
   * Queue notification emails for consultations that have opened.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function process_opens() {
    $now = new DrupalDateTime('now', \Drupal::config('system.date')->get('timezone')['default']);
    $last_run_timestamp = \Drupal::state()->get('localgov_consultations_process_opens_last_run');
    $last_run = date_timestamp_set(new \DateTime(), $last_run_timestamp)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Find consultations that have opened since our last run.
    $open_query = \Drupal::entityQuery('node');
    $open_query
      ->accessCheck(TRUE)
      ->condition('type', 'consultation')
      ->condition('localgov_consultation_date.value', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=')
      ->condition('localgov_consultation_date.value', $last_run, '>=')
      ->condition('status', 1);

    $opened_consultations = $open_query->execute();
    $opened_consultation_nodes = Node::loadMultiple($opened_consultations);

    foreach ($opened_consultation_nodes as $consultation_node) {

      $this->notifySubscribers($consultation_node, NotificationReason::ConsultationOpened);
    }

    \Drupal::state()->set('localgov_consultations_process_opens_last_run', $now->getTimestamp());
  }

  /**
   * Queue notification emails for consultations that have closed.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function process_closes() {

    // This seems to be buggy - last run state var never gets updated, so keeps e-mailing people. Disabled for now.
    $now = new DrupalDateTime('now', \Drupal::config('system.date')->get('timezone')['default']);
    $last_run_timestamp = \Drupal::state()->get('localgov_consultations_process_closes_last_run');
    $last_run = date_timestamp_set(new \DateTime(), $last_run_timestamp)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Find queries that have opened since our last run.
    $closed_query = \Drupal::entityQuery('node');
    $closed_query
      ->accessCheck(TRUE)
      ->condition('type', 'consultation')
      ->condition('localgov_consultation_date.end_value', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=')
      ->condition('localgov_consultation_date.end_value', $last_run, '>=')
      ->condition('status', 1);
    $closed_consultations = $closed_query->execute();
    $closed_consultation_nodes = Node::loadMultiple($closed_consultations);

    foreach ($closed_consultation_nodes as $consultation_node) {
      $this->notifySubscribers($consultation_node, NotificationReason::ConsulationClosed);
      $this->notifyConsultationContact($consultation_node, NotificationReason::ConsulationClosed);
    }

    \Drupal::state()->set('localgov_consultations_process_closes_last_run', $now->getTimestamp());
  }

  /**
   * Helper function to get subscriptions to this consultation / all consultations.
   *
   * @param $consultation
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getSubscribers($consultation) : array {
    $subscriber_entity_query = \Drupal::entityQuery('mailing_list_subscription');

    $single_consultation = $subscriber_entity_query->andConditionGroup()
      ->condition('field_node', $consultation->id())
      ->condition('mailing_list', 'consultations');

    $or_multiple = $subscriber_entity_query->orConditionGroup()
      ->condition($single_consultation)
      ->condition('mailing_list', 'all_consultations');

    $subscriber_entity_query
      ->condition($or_multiple)
      ->accessCheck(FALSE)
      ->condition('status', 1);
    $subscribed_to_this_consultation = $subscriber_entity_query->execute();

    return \Drupal::entityTypeManager()->getStorage('mailing_list_subscription')->loadMultiple($subscribed_to_this_consultation);
  }

  /**
   * Send to those who subscribed to this consultation.
   */
  public function notifySubscribers(ContentEntityInterface $consultation, NotificationReason $reason) : void {
    $queue = $this->queue->get(EmailQueue::QUEUE_NAME);

    $subscriptions = $this->getSubscribers($consultation);

    /** @var \Drupal\mailing_list\Entity\Subscription $subscription */
    foreach ($subscriptions as $subscription) {
      $email['email'] = $subscription->email->value;

      $email['unsubscribe_url'] = \Drupal::urlGenerator()->generateFromRoute('localgov_consultations_notify.unsubscribe', [
        'mailing_list_subscription' => $subscription->id(),
        'token' => $subscription->getAccessHash(),
      ], ['absolute' => TRUE]);

      $email['consultation_id'] = $consultation->id();
      $email['email_id'] = match($reason) {
        NotificationReason::ConsultationOpened => "consultation_opened",
        NotificationReason::ConsultationDatesChanged => "consultation_dates_changed",
        NotificationReason::ConsultationClosing => NULL,
        NotificationReason::ConsulationClosed => NULL
      };

      if ($email['email_id'] != NULL) {
        $queue->createItem($email);
      }
    }
  }

  /**
   * Send to person responsible for this consultation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $consultation
   * @param NotificationReason $reason
   *
   * @return void
   */
  public function notifyConsultationContact(ContentEntityInterface $consultation, NotificationReason $reason) : void {
    if ($reason != NotificationReason::ConsulationClosed) {
      return;
    }

    // Send to the person responsible for updating this consultation.
    $queue = $this->queue->get(EmailQueue::QUEUE_NAME);

    // Quick check that there IS a responsible person.
    $email['email'] = !$consultation->get('field_email')->isEmpty()
      ? $consultation->get('field_email')->value
      : \Drupal::config('system.site')->get('mail');
    $email['consultation_id'] = $consultation->id();

    $email['email_id'] = "consultation_closed_please_provide_result";
    $queue->createItem($email);
  }

}
