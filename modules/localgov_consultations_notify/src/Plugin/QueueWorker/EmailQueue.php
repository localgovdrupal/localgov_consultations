<?php

namespace Drupal\localgov_consultations_notify\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Tasks for Emailing Subscribers.
 *
 * @QueueWorker(
 *   id = "localgov_consultations_email_queue",
 *   title = @Translation("Localgov Consultations: send email queue items"),
 *   cron = {"time" = 30}
 * )
 */
class EmailQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  const QUEUE_NAME = 'localgov_consultations_email_queue';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EmailFactoryInterface $emailFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('email_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    if (!$data['consultation_id']) {
      return;
    }

    $consultation = Node::load($data['consultation_id']);

    $params = [
      'consultation' => $consultation,
      'email_address' => $data['email'],
      'unsubscribe_url' => $data['unsubscribe_url'],
    ];

    $this->emailFactory->sendTypedEmail('localgov_consultations_notify', $data['email_id'], ...$params);
  }

}
