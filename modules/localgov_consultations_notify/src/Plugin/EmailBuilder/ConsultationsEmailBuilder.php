<?php

namespace Drupal\localgov_consultations_notify\Plugin\EmailBuilder;

use Drupal\node\NodeInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the Email Builder plug-in for consultations mails.
 *
 * @EmailBuilder(
 *   id = "localgov_consultations_notify",
 *   sub_types = {
 *     "subscribe_confirm" = @Translation("Subscription confirmed"),
 *     "consultation_closed_please_provide_result" = @Translation("Consultation provide results reminder"),
 *     "consultation_opened" = @Translation("Consultation opened"),
 *     "consultation_closed" = @Translation("Consultation closed"),
 *     "consultation_dates_changed" = @Translation("Consultation dates changed")
 *   },
 *   common_adjusters = {},
 * )
 */
class ConsultationsEmailBuilder extends EmailBuilderBase {

  use TokenProcessorTrait;

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   Entities to notify about.
   */
  public function createParams(EmailInterface $email, ?string $email_address = NULL, ?NodeInterface $consultation = NULL, ?string $unsubscribe_url = NULL): void {
    $email->setParam('email_address', $email_address);
    $email->setParam('consultation', $consultation);
    $email->setParam('unsubscribe_url', $unsubscribe_url);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {

    /** @var \Drupal\node\NodeInterface $consultation */
    $consultation = $email->getParam('consultation');

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');

    $consultation_open_date = NULL;
    $consultation_close_date = NULL;

    if ($consultation) {
      $consultation_open_date = !$consultation->get('localgov_consultation_start_date')->isEmpty()
        ? $date_formatter->format($consultation->get('localgov_consultation_start_date')->date->getTimestamp())
        : "TBD";

      $consultation_close_date = !$consultation->get('localgov_consultation_end_date')->isEmpty()
        ? $date_formatter->format($consultation->get('localgov_consultation_end_date')->date->getTimestamp())
        : "TBD";
    }

    $email->setTo($email->getParam('email_address'))
      ->setVariable('consultation', $consultation)
      ->setVariable('consultation_name', $consultation ? $consultation->getTitle() : "All consultations")
      ->setVariable('consultation_url', $consultation ? $consultation->toUrl()->toString() : "")
      ->setVariable('consultation_open_date', $consultation_open_date)
      ->setVariable('consultation_close_date', $consultation_close_date)
      ->setVariable('unsubscribe_url', $email->getParam('unsubscribe_url'))
      ->setVariable('site_name', \Drupal::config('system.site')->get('name'));
  }

}
