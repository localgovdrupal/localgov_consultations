<?php

namespace Drupal\localgov_consultations_notify\Plugin\EmailBuilder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class ConsultationsEmailBuilder extends EmailBuilderBase implements ContainerFactoryPluginInterface {

  use TokenProcessorTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a ConsultationsEmailBuilder object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    DateFormatterInterface $date_formatter,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('config.factory'),
    );
  }

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param string|null $email_address
   *   The recipient email address.
   * @param \Drupal\node\NodeInterface|null $consultation
   *   The consultation node entity.
   * @param string|null $unsubscribe_url
   *   The unsubscribe URL.
   */
  public function createParams(EmailInterface $email, string $email_address = NULL, NodeInterface $consultation = NULL, string $unsubscribe_url = NULL): void {
    $email->setParam('email_address', $email_address);
    $email->setParam('consultation', $consultation);
    $email->setParam('unsubscribe_url', $unsubscribe_url);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    /** @var \Drupal\node\NodeInterface|null $consultation */
    $consultation = $email->getParam('consultation');

    $consultation_open_date = NULL;
    $consultation_close_date = NULL;

    if ($consultation) {
      $consultation_open_date = !$consultation->get('localgov_consultation_date')->isEmpty()
        ? $this->dateFormatter->format($consultation->get('localgov_consultation_date')->start_date->getTimestamp())
        : "TBD";

      $consultation_close_date = !$consultation->get('localgov_consultation_date')->isEmpty()
        ? $this->dateFormatter->format($consultation->get('localgov_consultation_date')->end_date->getTimestamp())
        : "TBD";
    }

    $email->setTo($email->getParam('email_address'))
      ->setVariable('consultation', $consultation)
      ->setVariable('consultation_name', $consultation ? $consultation->getTitle() : "All consultations")
      ->setVariable('consultation_url', $consultation ? $consultation->toUrl()->toString() : "")
      ->setVariable('consultation_open_date', $consultation_open_date)
      ->setVariable('consultation_close_date', $consultation_close_date)
      ->setVariable('unsubscribe_url', $email->getParam('unsubscribe_url'))
      ->setVariable('site_name', $this->configFactory->get('system.site')->get('name'));
  }

}
