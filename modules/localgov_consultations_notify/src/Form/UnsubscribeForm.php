<?php

namespace Drupal\localgov_consultations_notify\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Removes a user's mailing_list_subscription.
 * Via special URL access.
 * So they don't have to go via the dodgy mailing_list interface.
 */
class UnsubscribeForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $subscription = \Drupal::routeMatch()->getParameter('mailing_list_subscription');

    $label = 'these emails';

    switch ($subscription->bundle()) {
      case 'consultations':
        $consultation_id = $subscription->get('field_node')->getValue()[0]['target_id'];
        // SHOULD be set, but just in case...
        if ($consultation_id) {
          $consultation = Node::load($consultation_id);
          $label = 'updates on consultation: ' . $consultation->label();
        }
        break;

      case 'all_consultations':
        // FIXME: council name hardcoded.
        $label = 'updates on all Localgov consultations';
        break;
    }

    return $this->t('Are you sure you want to unsubscribe from %label?', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unsubscribe');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subscription = \Drupal::routeMatch()->getParameter('mailing_list_subscription');
    $subscription->delete();
    $this->messenger()->addMessage($this->t('You have been unsubscribed!'));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   *
   */
  public function getFormId() {
    return 'localgov_consultations_unsubscribe';
  }

}
