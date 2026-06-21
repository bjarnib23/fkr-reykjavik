<?php

namespace Drupal\booking_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\booking_core\BookingSlotService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Multi-step booking form with AJAX time slot selection.
 */
class BookingForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * The lock backend.
   */
  protected LockBackendInterface $lock;

  /**
   * The flood service.
   */
  protected FloodInterface $flood;

  /**
   * The booking slot service.
   */
  protected BookingSlotService $slotService;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a BookingForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\booking_core\BookingSlotService $slot_service
   *   The booking slot service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory,
    LockBackendInterface $lock,
    FloodInterface $flood,
    BookingSlotService $slot_service,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager       = $mail_manager;
    $this->setConfigFactory($config_factory);
    $this->lock        = $lock;
    $this->flood       = $flood;
    $this->slotService = $slot_service;
    $this->logger      = $logger_factory->get('booking_core');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('lock'),
      $container->get('flood'),
      $container->get('booking_core.slot_service'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'booking_core_booking_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config   = $this->configFactory()->get('booking_core.settings');
    $services = $config->get('services');
    $options  = array_combine($services, $services);

    $site_tz  = $this->configFactory()->get('system.date')->get('timezone.default') ?: 'UTC';
    $tomorrow = new DrupalDateTime('+1 day', new \DateTimeZone($site_tz));

    $form['name'] = [
      '#type'      => 'textfield',
      '#title'     => $this->t('Full name'),
      '#required'  => TRUE,
      '#maxlength' => 255,
    ];

    $form['email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Email address'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type'      => 'tel',
      '#title'     => $this->t('Phone number'),
      '#maxlength' => 50,
    ];

    $form['service'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Service'),
      '#options'      => $options,
      '#required'     => TRUE,
      '#empty_option' => $this->t('- Select a service -'),
    ];

    $form['date'] = [
      '#type'       => 'date',
      '#title'      => $this->t('Date'),
      '#required'   => TRUE,
      '#attributes' => ['min' => $tomorrow->format('Y-m-d')],
      '#ajax'       => [
        'callback'        => '::updateTimeSlots',
        'wrapper'         => 'time-wrapper',
        'event'           => 'change',
        'progress'        => ['type' => 'throbber', 'message' => NULL],
        'disable_refocus' => TRUE,
      ],
    ];

    $input         = $form_state->getUserInput();
    $selected_date = $form_state->getValue('date') ?? ($input['date'] ?? NULL);
    $slots         = $selected_date ? $this->slotService->getAvailableSlots($selected_date) : [];

    $form['time'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Time'),
      '#options'      => $slots,
      '#required'     => TRUE,
      '#empty_option' => $selected_date
        ? ($slots ? $this->t('- Select a time -') : $this->t('No slots available for this date'))
        : $this->t('- Select a date first -'),
      '#prefix'       => '<div id="time-wrapper">',
      '#suffix'       => '</div>',
    ];

    $form['notes'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows'  => 4,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Book appointment'),
    ];

    return $form;
  }

  /**
   * AJAX callback to refresh the time slot select element.
   */
  public function updateTimeSlots(array &$form, FormStateInterface $form_state): array {
    return $form['time'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $config       = $this->configFactory()->get('booking_core.settings');
    $flood_limit  = (int) $config->get('flood_limit');
    $flood_window = (int) $config->get('flood_window');
    $ip           = $this->getRequest()->getClientIp();
    if (!$this->flood->isAllowed('booking_core_submit', $flood_limit, $flood_window, $ip)) {
      $form_state->setErrorByName('', $this->t('Too many booking attempts. Please try again later.'));
      return;
    }

    $date = $form_state->getValue('date');
    $time = $form_state->getValue('time');

    if ($date) {
      $slots = $this->slotService->getAvailableSlots($date);
      if (empty($slots)) {
        $form_state->setErrorByName('date', $this->t('No appointments are available on this date.'));
      }
      elseif (!$time || !isset($slots[$time])) {
        $form_state->setErrorByName('time', $this->t('Please select an available time slot.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config       = $this->configFactory()->get('booking_core.settings');
    $flood_window = (int) $config->get('flood_window');
    $ip           = $this->getRequest()->getClientIp();
    $this->flood->register('booking_core_submit', $flood_window, $ip);

    $date    = $form_state->getValue('date');
    $time    = $form_state->getValue('time');
    $site_tz = $this->configFactory()->get('system.date')->get('timezone.default') ?: 'UTC';
    $dt      = DrupalDateTime::createFromFormat(
      'Y-m-d\TH:i:s',
      $date . 'T' . $time . ':00',
      new \DateTimeZone($site_tz),
    );
    $dt->setTimezone(new \DateTimeZone('UTC'));
    $iso      = $dt->format('Y-m-d\TH:i:s');
    $lock_key = 'booking_core_slot_' . $iso;

    if (!$this->lock->acquire($lock_key, 15)) {
      $this->messenger()->addError($this->t('That slot is currently being booked. Please try again.'));
      return;
    }

    // Re-check availability inside the lock to prevent race conditions.
    $slots = $this->slotService->getAvailableSlots($date);
    if (!isset($slots[$time])) {
      $this->lock->release($lock_key);
      $this->messenger()->addError($this->t('That time slot was just taken. Please choose another.'));
      return;
    }

    try {
      $booking = $this->entityTypeManager->getStorage('booking')->create([
        'name'    => $form_state->getValue('name'),
        'email'   => $form_state->getValue('email'),
        'phone'   => $form_state->getValue('phone') ?? '',
        'service' => $form_state->getValue('service') ?? '',
        'date'    => $iso,
        'notes'   => $form_state->getValue('notes') ?? '',
      ]);
      $booking->save();
    }
    catch (\Exception $e) {
      $this->lock->release($lock_key);
      $this->messenger()->addError($this->t('Could not save your booking. Please try again.'));
      $this->logger->error('Booking save failed: @msg', ['@msg' => $e->getMessage()]);
      return;
    }

    $this->lock->release($lock_key);

    $config   = $this->configFactory()->get('booking_core.settings');
    $langcode = $this->configFactory()->get('system.site')->get('langcode');
    $params   = [
      'name'    => $form_state->getValue('name'),
      'date'    => $iso,
      'email'   => $form_state->getValue('email'),
      'phone'   => $form_state->getValue('phone') ?? '',
      'service' => $form_state->getValue('service') ?? '',
      'notes'   => $form_state->getValue('notes') ?? '',
    ];

    $this->mailManager->mail('booking_core', 'confirmation', $form_state->getValue('email'), $langcode, $params);

    $admin_email = $config->get('admin_email') ?: $this->configFactory()->get('system.site')->get('mail');
    $this->mailManager->mail('booking_core', 'notification', $admin_email, $langcode, $params);

    $form_state->setRedirectUrl(Url::fromRoute('booking_core.thank_you'));
  }

}
