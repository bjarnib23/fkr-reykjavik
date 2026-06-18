<?php

namespace Drupal\Tests\booking_core\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Core\Form\FormState;
use Drupal\booking_core\BookingInterface;
use Drupal\booking_core\Form\BookingForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for BookingForm: flood control and mail dispatch.
 *
 * @group booking_core
 */
#[Group('booking_core')]
#[RunTestsInSeparateProcesses]
class BookingFormKernelTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['booking_core', 'datetime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('booking');
    $this->installConfig(['booking_core']);
    $this->config('system.date')->set('timezone.default', 'UTC')->save();

    // Route mails to the state collector so we can inspect them.
    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();
    $this->container->get('state')->set('system.test_mail_collector', []);
  }

  /**
   * Returns the next Monday as a Y-m-d string.
   */
  private function nextMonday(): string {
    return (new \DateTime('next Monday'))->format('Y-m-d');
  }

  /**
   * Returns the next Sunday as a Y-m-d string.
   */
  private function nextSunday(): string {
    return (new \DateTime('next Sunday'))->format('Y-m-d');
  }

  /**
   * After five flood registrations the sixth attempt is blocked.
   */
  public function testFloodBlocksAfterFiveAttempts(): void {
    $flood = $this->container->get('flood');
    $ip    = '127.0.0.1';

    for ($i = 0; $i < 5; $i++) {
      $this->assertTrue($flood->isAllowed('booking_core_submit', 5, 3600, $ip));
      $flood->register('booking_core_submit', 3600, $ip);
    }

    $this->assertFalse($flood->isAllowed('booking_core_submit', 5, 3600, $ip));
  }

  /**
   * A valid submission creates a booking entity and dispatches two mails.
   */
  public function testValidSubmissionCreatesBookingAndSendsMail(): void {
    $date = $this->nextMonday();

    // Add a service so the select element has an option.
    $this->config('booking_core.settings')
      ->set('services', ['Test service'])
      ->set('admin_email', 'admin@example.com')
      ->save();

    $form_state = new FormState();
    $form_state->setValues([
      'name'    => 'Jane Doe',
      'email'   => 'jane@example.com',
      'phone'   => '555-9999',
      'service' => 'Test service',
      'date'    => $date,
      'time'    => '09:00',
      'notes'   => '',
    ]);

    $this->container->get('form_builder')
      ->submitForm(BookingForm::class, $form_state);

    // One booking entity should exist.
    $ids = $this->container->get('entity_type.manager')
      ->getStorage('booking')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertCount(1, $ids);

    $booking = $this->container->get('entity_type.manager')
      ->getStorage('booking')
      ->load(reset($ids));
    $this->assertInstanceOf(BookingInterface::class, $booking);
    $this->assertSame('Jane Doe', $booking->getName());
    $this->assertSame('jane@example.com', $booking->getEmail());

    // Two mails: confirmation to customer, notification to admin.
    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(2, $mails);

    $keys = array_column($mails, 'key');
    $this->assertContains('confirmation', $keys);
    $this->assertContains('notification', $keys);
  }

  /**
   * Submitting a closed day (Sunday) sets an error on the date field.
   */
  public function testClosedDaySetsDateError(): void {
    $this->config('booking_core.settings')
      ->set('services', ['Test service'])
      ->set('open_days', [1, 2, 3, 4, 5])
      ->save();

    $form_state = (new FormState())->setValues([
      'name'    => 'Test User',
      'email'   => 'test@example.com',
      'phone'   => '',
      'service' => 'Test service',
      'date'    => $this->nextSunday(),
      'time'    => '10:00',
      'notes'   => '',
    ]);

    $this->container->get('form_builder')->submitForm(BookingForm::class, $form_state);

    $this->assertArrayHasKey('date', $form_state->getErrors());
  }

  /**
   * Submitting a valid date with a time outside business hours sets a time error.
   */
  public function testUnavailableTimeSetsTimeError(): void {
    $this->config('booking_core.settings')
      ->set('services', ['Test service'])
      ->set('open_days', [1, 2, 3, 4, 5])
      ->set('open_time', '09:00')
      ->set('close_time', '17:00')
      ->save();

    $form_state = (new FormState())->setValues([
      'name'    => 'Test User',
      'email'   => 'test@example.com',
      'phone'   => '',
      'service' => 'Test service',
      'date'    => $this->nextMonday(),
      'time'    => '22:00',
      'notes'   => '',
    ]);

    $this->container->get('form_builder')->submitForm(BookingForm::class, $form_state);

    $this->assertArrayHasKey('time', $form_state->getErrors());
  }

  /**
   * Submitting the same slot twice results in the second attempt being rejected.
   */
  public function testDoubleBookingSameSlotIsRejected(): void {
    $date = $this->nextMonday();

    $this->config('booking_core.settings')
      ->set('services', ['Test service'])
      ->save();

    $values = [
      'name'    => 'Jane Doe',
      'email'   => 'jane@example.com',
      'phone'   => '',
      'service' => 'Test service',
      'date'    => $date,
      'time'    => '10:00',
      'notes'   => '',
    ];

    $form_state_1 = (new FormState())->setValues($values);
    $this->container->get('form_builder')->submitForm(BookingForm::class, $form_state_1);

    $form_state_2 = (new FormState())->setValues($values);
    $this->container->get('form_builder')->submitForm(BookingForm::class, $form_state_2);

    // Only one booking should exist; the second was rejected.
    $ids = $this->container->get('entity_type.manager')
      ->getStorage('booking')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertCount(1, $ids);
  }

}
