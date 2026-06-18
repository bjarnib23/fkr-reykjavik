<?php

namespace Drupal\Tests\booking_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\booking_core\Form\BookingSettingsForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for BookingSettingsForm validation.
 *
 * @group booking_core
 */
#[CoversClass(BookingSettingsForm::class)]
#[Group('booking_core')]
#[RunTestsInSeparateProcesses]
class BookingSettingsFormValidationTest extends EntityKernelTestBase {

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
  }

  /**
   * Builds and validates the settings form with the given blocked period row.
   *
   * @param array $period
   *   The blocked period values for table row 0.
   *
   * @return string[]
   *   Flattened array of error message strings.
   */
  private function validateWithPeriod(array $period): array {
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(BookingSettingsForm::class);

    // Build with one period row so the form array contains the table elements.
    $build_state = new FormState();
    $build_state->set('blocked_periods', [$period]);
    $build_state->set('blocked_removed', []);
    $form = $this->container->get('form_builder')
      ->buildForm($form_object, $build_state);

    // Validate with the same period submitted as table values.
    $validate_state = new FormState();
    $validate_state->setValues([
      'open_time'     => '09:00',
      'close_time'    => '17:00',
      'table'         => [0 => $period + ['remove' => '']],
    ]);
    $validate_state->set('blocked_removed', []);

    $form_object->validateForm($form, $validate_state);

    return array_map(
      fn($msg) => (string) $msg,
      $validate_state->getErrors(),
    );
  }

  /**
   * An invalid open_time format triggers a validation error.
   */
  public function testInvalidOpenTimeFormatIsRejected(): void {
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(BookingSettingsForm::class);

    $build_state = new FormState();
    $build_state->set('blocked_periods', []);
    $build_state->set('blocked_removed', []);
    $form = $this->container->get('form_builder')
      ->buildForm($form_object, $build_state);

    $validate_state = new FormState();
    $validate_state->setValues([
      'open_time'  => '9am',
      'close_time' => '17:00',
      'table'      => [],
    ]);
    $validate_state->set('blocked_removed', []);

    $form_object->validateForm($form, $validate_state);
    $errors = $validate_state->getErrors();

    $this->assertArrayHasKey('open_time', $errors);
  }

  /**
   * A blocked period with no date triggers a validation error.
   */
  public function testBlockedPeriodRequiresDate(): void {
    $errors = $this->validateWithPeriod([
      'date'       => '',
      'all_day'    => FALSE,
      'start_time' => '09:00',
      'end_time'   => '10:00',
      'reason'     => '',
    ]);

    $this->assertNotEmpty($errors);
  }

  /**
   * A blocked period with an invalid date string triggers a validation error.
   */
  public function testBlockedPeriodInvalidDateIsRejected(): void {
    $errors = $this->validateWithPeriod([
      'date'       => 'not-a-date',
      'all_day'    => FALSE,
      'start_time' => '09:00',
      'end_time'   => '10:00',
      'reason'     => '',
    ]);

    $this->assertNotEmpty($errors);
  }

  /**
   * A blocked period with a past date triggers a validation error.
   */
  public function testBlockedPeriodPastDateIsRejected(): void {
    $errors = $this->validateWithPeriod([
      'date'       => '2020-01-06',
      'all_day'    => FALSE,
      'start_time' => '09:00',
      'end_time'   => '10:00',
      'reason'     => '',
    ]);

    $this->assertNotEmpty($errors);
  }

  /**
   * A blocked period where start_time >= end_time triggers a validation error.
   */
  public function testBlockedPeriodStartTimeMustBeBeforeEndTime(): void {
    $future = (new \DateTime('+1 week'))->format('Y-m-d');

    $errors = $this->validateWithPeriod([
      'date'       => $future,
      'all_day'    => FALSE,
      'start_time' => '12:00',
      'end_time'   => '10:00',
      'reason'     => '',
    ]);

    $this->assertNotEmpty($errors);
  }

  /**
   * A valid blocked period passes validation without errors.
   */
  public function testValidBlockedPeriodPassesValidation(): void {
    $future = (new \DateTime('+1 week'))->format('Y-m-d');

    $errors = $this->validateWithPeriod([
      'date'       => $future,
      'all_day'    => FALSE,
      'start_time' => '09:00',
      'end_time'   => '12:00',
      'reason'     => 'Staff training',
    ]);

    $this->assertEmpty($errors);
  }

  /**
   * A valid all-day blocked period passes validation without errors.
   */
  public function testValidAllDayBlockedPeriodPassesValidation(): void {
    $future = (new \DateTime('+2 weeks'))->format('Y-m-d');

    $errors = $this->validateWithPeriod([
      'date'       => $future,
      'all_day'    => TRUE,
      'start_time' => '',
      'end_time'   => '',
      'reason'     => 'Holiday',
    ]);

    $this->assertEmpty($errors);
  }

}
