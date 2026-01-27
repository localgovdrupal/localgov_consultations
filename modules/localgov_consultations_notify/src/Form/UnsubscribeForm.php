<?php

namespace Drupal\localgov_consultations_notify\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Removes a user's mailing_list_subscription.
 *
 * Via special URL access so they don't have to go via the dodgy
 * mailing_list interface.
 */
class UnsubscribeForm extends ConfirmFormBase {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an UnsubscribeForm object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $subscription = $this->routeMatch->getParameter('mailing_list_subscription');

    $label = 'these emails';

    switch ($subscription->bundle()) {
      case 'consultations':
        $consultation_id = $subscription->get('field_node')->getValue()[0]['target_id'];
        // SHOULD be set, but just in case...
        if ($consultation_id) {
          $consultation = $this->entityTypeManager->getStorage('node')->load($consultation_id);
          $label = 'updates on consultation: ' . $consultation->label();
        }
        break;

      case 'all_consultations':
        $label = 'updates on all consultations';
        break;
    }

    return $this->t('Are you sure you want to unsubscribe from %label?', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Unsubscribe');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $subscription = $this->routeMatch->getParameter('mailing_list_subscription');
    $subscription->delete();
    $this->messenger()->addMessage($this->t('You have been unsubscribed!'));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The form ID.
   */
  public function getFormId(): string {
    return 'localgov_consultations_unsubscribe';
  }

}
