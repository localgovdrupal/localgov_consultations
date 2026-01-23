<?php

namespace Drupal\localgov_consultations_notify;

use Drupal\mailing_list\SubscriptionInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;

/**
 *
 */
class SubscriptionHandler {

  public function __construct(
    private readonly EmailFactoryInterface $emailFactory,
  ) {}

  /**
   *
   */
  public function onSubscribe(SubscriptionInterface $subscription) {

    $unsubscribe = \Drupal::urlGenerator()->generateFromRoute('localgov_consultations_notify.unsubscribe', [
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
