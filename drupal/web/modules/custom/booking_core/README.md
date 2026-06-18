# Booking Core

A standalone Drupal 10/11 module that provides appointment booking with a custom entity, configurable availability, admin management, and email notifications.

## Features

- Custom `Booking` content entity — no dependency on content types or fields outside the module
- Public booking form with AJAX time slot selection
- Configurable open days, opening/closing hours, slot duration, and booking window
- Flood protection and distributed locking to prevent double-bookings
- Admin interface for listing, viewing, and deleting bookings
- Confirmation email to the customer and notification email to the admin
- Injectable `BookingSlotService` for slot generation logic
- Unit tests and kernel tests with GitHub Actions CI

## Requirements

- Drupal 10 or 11
- PHP 8.1+
- Core modules: `datetime`, `user` (both enabled by default in standard Drupal installs)
- No contributed module dependencies

## Installation

1. Place the module folder in `web/modules/custom/booking_core/`
2. Enable the module:
   ```
   drush en booking_core
   ```
3. The `booking` database table is created automatically on install.

## Configuration

Go to **Administration → Configuration → Booking Core Settings** (`/admin/config/booking-core/settings`) and configure:

| Setting | Description | Default |
|---|---|---|
| Admin notification email | Where new booking notifications are sent | Site email |
| Available services | One service per line, shown as a dropdown on the booking form | — |
| Open days | Which days of the week accept bookings | Mon–Fri |
| Opening time | First available slot (HH:MM) | 09:00 |
| Closing time | No slots at or after this time (HH:MM) | 17:00 |
| Slot duration | Length of each appointment slot in minutes | 30 |
| Weeks ahead | How far in advance customers can book | 4 |

## Usage

### Public booking form

Available at `/book-appointment`. Customers:

1. Fill in their name, email, optional phone number, and select a service
2. Pick a date — only open days within the booking window are selectable
3. A time slot dropdown loads automatically via AJAX showing only available slots
4. After submitting they are redirected to `/book-appointment/thank-you`

### Admin interface

Requires the **Administer booking** or **Manage booking** permission.

| Path | Description |
|---|---|
| `/admin/bookings` | All bookings sorted by date |
| `/admin/bookings/{id}` | Full details for a single booking |
| `/admin/bookings/{id}/delete` | Confirmation form before deletion |

## Emails

Two emails are sent on each successful booking:

- **Confirmation** (to the customer) — includes service, date/time, and notes if provided
- **Notification** (to the admin email) — includes all booking details

The company name in the email footer is taken from the site name at `/admin/config/system/site-information`.

Dates are formatted using the site's configured timezone.

## Permissions

| Permission | Description |
|---|---|
| `administer booking` | Configure booking settings and fully manage all bookings |
| `manage booking` | View the booking calendar and list, and delete individual bookings |

## Running tests

Unit tests (no database required):

```
vendor/bin/phpunit -c web/core/phpunit.xml web/modules/custom/booking_core/tests/src/Unit/ --testdox
```

Kernel tests (requires `SIMPLETEST_DB` environment variable pointing to a database):

```
vendor/bin/phpunit -c web/core/phpunit.xml web/modules/custom/booking_core/tests/src/Kernel/ --testdox
```
