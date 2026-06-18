# Rapyd Checkout

A standalone Drupal Commerce payment gateway module that integrates the
[Rapyd Hosted Checkout](https://docs.rapyd.net/en/rapyd-collect/rapyd-checkout.html)
API. Customers are redirected to a Rapyd-hosted payment page and returned to the
site after payment. Webhooks confirm the payment and update the Commerce order.

## Features

- Implements `OffsitePaymentGatewayBase` — appears in Commerce as a standard
  payment gateway with full checkout flow support.
- API credentials stored via the **Key** module — never in plain Drupal config.
- HMAC-SHA256 webhook signature verification (always enforced, including sandbox).
- Configurable sandbox / live mode toggle.

## Requirements

- Drupal 10 or 11
- PHP 8.1+
- [Drupal Commerce](https://www.drupal.org/project/commerce) 2.36+
- [Key](https://www.drupal.org/project/key) module — stores API credentials securely

## Installation

1. Place the module folder inside `web/modules/custom/rapyd_checkout`.
2. Enable the module:

   ```
   drush en rapyd_checkout -y
   ```

3. Create two **Key** entities at **Administration → Configuration → System → Keys**:
   - One for the Rapyd *access key*.
   - One for the Rapyd *secret key*.

4. Add a payment gateway at **Commerce → Configuration → Payment gateways → Add gateway**:
   - Plugin: **Rapyd Checkout**
   - Fill in the machine names of the two Key entities.
   - Check **Sandbox mode** during development.

5. Configure Rapyd to send webhooks to:

   ```
   https://your-site.example/payment/notify/rapyd_checkout
   ```

## Configuration

Settings are stored per Commerce payment gateway entity under the
`commerce_payment_gateway.plugin.rapyd_checkout` schema.

| Key | Type | Description |
|-----|------|-------------|
| `access_key_id` | string | Machine name of the Key entity holding the Rapyd access key |
| `secret_key_id` | string | Machine name of the Key entity holding the Rapyd secret key |

Sandbox vs. live mode is controlled by the standard Commerce gateway **Mode**
field (test / live).

## Webhook Verification

Every inbound webhook is verified with the full Rapyd HMAC formula:

```
BASE64( HMAC-SHA256(
  lowercase(method) + path + salt + timestamp + access_key + secret_key + body,
  secret_key
) )
```

Requests with an invalid signature are rejected with HTTP 403.

## Routes

| Path | Description |
|------|-------------|
| `/payment/notify/rapyd_checkout` | Webhook endpoint (handled by Commerce) |

## Running Tests

```
php vendor/bin/phpunit -c web/core/phpunit.xml \
  web/modules/custom/rapyd_checkout/tests/ --testdox
```

## Maintainers

- [bjarnib23](https://github.com/bjarnib23)
