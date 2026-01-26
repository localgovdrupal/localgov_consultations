<?php

declare(strict_types=1);

namespace Drupal\localgov_consultations_notify;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\localgov_consultations_notify\Plugin\QueueWorker\EmailQueue;

/**
 *
 */
enum NotificationReason {
  case ConsultationClosing;
  case ConsultationClosed;
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
        NotificationReason::ConsultationClosed => NULL
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
    if ($reason != NotificationReason::ConsultationClosed) {
      return;
    }

    // Send to the service contacts associated with this consultation.
    $queue = $this->queue->get(EmailQueue::QUEUE_NAME);

    // Get service contacts from the consultation.
    $service_contacts = $consultation->get('localgov_service_contacts')->referencedEntities();

    if (!empty($service_contacts)) {
      foreach ($service_contacts as $contact) {
        // Skip disabled service contacts.
        if (!$contact->isEnabled()) {
          continue;
        }

        $email['email'] = $contact->getEmail();
        $email['consultation_id'] = $consultation->id();
        $email['email_id'] = "consultation_closed_please_provide_result";
        $queue->createItem($email);
      }
    }
  }

}
