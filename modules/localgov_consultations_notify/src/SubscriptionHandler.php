<?php

namespace Drupal\localgov_consultations_notify;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\mailing_list\SubscriptionInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;

/**
 * Handles mailing list subscription events.
 */
class SubscriptionHandler {

  /**
   * Constructs a SubscriptionHandler object.
   *
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $emailFactory
   *   The email factory service.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The URL generator service.
   */
  public function __construct(
    private readonly EmailFactoryInterface $emailFactory,
    private readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * Sends a confirmation email when a user subscribes.
   *
   * @param \Drupal\mailing_list\SubscriptionInterface $subscription
   *   The subscription entity.
   */
  public function onSubscribe(SubscriptionInterface $subscription) {

    $unsubscribe = $this->urlGenerator->generateFromRoute('localgov_consultations_notify.unsubscribe', [
      'mailing_list_subscription' => $subscription->id(),
      'token' => $subscription->getAccessHash(),
    ], ['absolute' => TRUE]);

    $consultation = $subscription->hasField('field_node') ? $subscription->field_node->entity : NULL;

    $params = [
      'email_address' => $subscription->getEmail(),
      'consultation' => $consultation,
      'unsubscribe_url' => $unsubscribe,
    ];

    $this->emailFactory->sendTypedEmail('localgov_consultations_notify', 'subscribe_confirm', ...$params);
  }
}
