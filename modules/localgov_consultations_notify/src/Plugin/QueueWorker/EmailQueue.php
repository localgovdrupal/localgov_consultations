<?php

/**
 * @file
 * Contains \Drupal\localgov_consultations\Plugin\QueueWorker\EmailQueue.
 */

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

  /**
   * The name of the queue
   */
  const QUEUE_NAME = 'localgov_consultations_email_queue';

  /**
   * Constructs a new EmailQueue object
   *
   * @param array $configuration
   *  A configuration array containing information about the plugin instance.
   * @param $plugin_id
   *  The plugin_id for the plugin instance.
   * @param $plugin_definition
   *  The plugin implementation definition.
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $emailFactory
   *  The email factory service
   */
  public function __construct(array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EmailFactoryInterface $emailFactory)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates an instance of the plugin
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *  The container to pull services from.
   * @param array $configuration
   *  A configuration array containing information about the plugin instance.
   * @param $plugin_id
   *  The plugin_id for the plugin instance.
   * @param $plugin_definition
   *  The plugin implementation definition
   *
   * @return self
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
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
      'unsubscribe_url' => $data['unsubscribe_url']
    ];

    $this->emailFactory->sendTypedEmail('localgov_consultations_notify', $data['email_id'], ...$params);
  }

}
