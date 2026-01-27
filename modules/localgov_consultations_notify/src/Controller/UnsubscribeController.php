<?php

namespace Drupal\localgov_consultations_notify\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller for the unsubscribe form.
 */
class UnsubscribeController extends ControllerBase {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs an UnsubscribeController object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
    );
  }

  /**
   * Check that the hash in the email matches the subscription hash.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\mailing_list\Entity\Subscription|null $subscription */
    $subscription = $this->routeMatch->getParameter('mailing_list_subscription');
    if (!$subscription) {
      return AccessResult::forbidden();
    }

    $hash = $subscription->getAccessHash();
    $token = $this->routeMatch->getParameter('token');

    if ($hash === $token) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
