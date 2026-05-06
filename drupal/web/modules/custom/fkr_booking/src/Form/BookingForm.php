<?php

namespace Drupal\fkr_booking\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BookingForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected MailManagerInterface $mailManager;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager       = $mail_manager;
    $this->configFactory     = $config_factory;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
    );
  }

  public function getFormId(): string {
    return 'fkr_booking_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $booked_dates = [];
    $booking_ids  = $this->entityTypeManager->getStorage('fkr_booking')->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    foreach ($this->entityTypeManager->getStorage('fkr_booking')->loadMultiple($booking_ids) as $b) {
      $booked_dates[] = $b->get('date')->value;
    }

    $availability = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'fkr_availability',
      'status' => 1,
    ]);

    $slots = ['' => $this->t('— Select a time —')];
    foreach ($availability as $node) {
      $datetime = $node->get('field_available_time')->value;
      if ($datetime && !in_array($datetime, $booked_dates)) {
        $slots[$datetime] = date('D d M Y \a\t H:i', strtotime($datetime));
      }
    }
    ksort($slots);

    $booking_pages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type'   => 'booking_page',
      'status' => 1,
    ]);

    $services = ['' => $this->t('— Select a service —')];
    if (!empty($booking_pages)) {
      $page = reset($booking_pages);
      foreach ($page->get('field_services') as $item) {
        $paragraph = $item->entity;
        if ($paragraph) {
          $title            = $paragraph->get('field_service_title')->value;
          $services[$title] = $title;
        }
      }
    }

    $form['service'] = [
      '#type'     => 'select',
      '#title'    => $this->t('Service'),
      '#options'  => $services,
      '#required' => TRUE,
    ];

    $form['date'] = [
      '#type'     => 'select',
      '#title'    => $this->t('Date & Time'),
      '#options'  => $slots,
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type'     => 'textfield',
      '#title'    => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type'     => 'email',
      '#title'    => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['phone'] = [
      '#type'     => 'tel',
      '#title'    => $this->t('Phone'),
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Notes'),
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Book appointment'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name    = $form_state->getValue('name');
    $email   = $form_state->getValue('email');
    $phone   = $form_state->getValue('phone');
    $date    = $form_state->getValue('date');
    $service = $form_state->getValue('service');
    $notes   = $form_state->getValue('notes');

    $booking = $this->entityTypeManager->getStorage('fkr_booking')->create([
      'name'           => $name,
      'email'          => $email,
      'phone'          => $phone,
      'date'           => $date,
      'service'        => $service,
      'notes'          => $notes,
      'booking_status' => 'pending',
    ]);
    $booking->save();

    $langcode    = $this->configFactory->get('system.site')->get('langcode');
    $admin_email = $this->configFactory->get('system.site')->get('mail');

    $this->mailManager->mail('fkr_booking', 'booking_confirmation', $email, $langcode, [
      'name' => $name,
      'date' => $date,
    ]);

    $this->mailManager->mail('fkr_booking', 'booking_notification', $admin_email, $langcode, [
      'name'             => $name,
      'email'            => $email,
      'phone'            => $phone,
      'date'             => $date,
      'hvad_viltu_panta' => $service,
      'notes'            => $notes,
    ]);

    $this->messenger()->addStatus($this->t('Your booking has been received. We will be in touch shortly.'));
  }

}
