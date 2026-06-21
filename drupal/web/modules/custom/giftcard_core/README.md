# Gift Card Core

A standalone Drupal module for selling digital gift cards through a configurable
payment provider. Buyers fill in a purchase form, pay via a hosted checkout page,
and both the sender and recipient receive confirmation emails once the payment is
confirmed by a webhook.

This module is intentionally standalone — it does not depend on Drupal Commerce.
Install a gateway sub-module (e.g. **giftcard_rapyd**) alongside it to activate
checkout.

## Features

- Custom **GiftCard** content entity with admin list, add, edit, and delete views.
- **PaymentClientInterface** — swap the payment provider without touching this module.
- Flood control to limit checkout attempts per IP per hour.
- Configurable currency, minimum amount, and flood threshold.
- Confirmation emails to both sender and recipient on successful payment.

## Requirements

- Drupal 10 or 11
- PHP 8.1+
- A gateway sub-module that implements `PaymentClientInterface` (e.g. `giftcard_rapyd`)

## Installation

1. Place `giftcard_core` inside `web/modules/custom/`.
2. Choose a gateway sub-module. For Rapyd, also place `giftcard_rapyd` there.
3. Enable both modules:

   ```
   drush en giftcard_core giftcard_rapyd -y
   ```

4. If using `giftcard_rapyd`, create two **Key** entities at
   **Administration → Configuration → System → Keys** — one for the Rapyd access
   key and one for the secret key. Then open
   **Administration → Configuration → Gift Cards → Rapyd API Settings** and fill
   in the Key entity machine names, sandbox toggle, and country code.

5. Open **Administration → Configuration → Gift Cards → Settings** and configure
   the currency code, minimum amount, and flood threshold.

6. Point your payment provider's webhook to:

   ```
   https://your-site.example/gift-card/webhook
   ```

## Configuration

### `giftcard_core.settings`

| Key | Type | Description |
|-----|------|-------------|
| `currency` | string | ISO 4217 currency code for gift card amounts |
| `min_amount` | integer | Smallest purchase amount in the major unit (e.g. 1000 = 1000 ISK) |
| `flood_threshold` | integer | Max checkout attempts per IP per hour (default: 5) |

Gateway-specific settings (credentials, sandbox toggle, country) live in the
sub-module's own config object (e.g. `giftcard_rapyd.settings`).

## Permissions

| Permission | Description |
|------------|-------------|
| `administer gift cards` | Full CRUD access to gift card entities and module settings |

## Swapping the Payment Provider

Install any module that registers `giftcard_core.payment_client`. Implement
`\Drupal\giftcard_core\PaymentClientInterface`:

```php
interface PaymentClientInterface {
  // Create a hosted checkout session; return redirect_url + payment_id or null.
  public function createCheckout(int $amount, string $currency, string $completeUrl, string $cancelUrl): ?array;

  // Return true only when the inbound request is authentic (signature + replay).
  public function verifyWebhook(Request $request): bool;

  // Return the payment ID if this is a completed-payment event, else null.
  public function extractCompletedPaymentId(Request $request): ?string;
}
```

Wire your class as the service in your sub-module's `*.services.yml`:

```yaml
giftcard_core.payment_client:
  class: Drupal\my_gateway\MyGatewayClient
  arguments: ['@config.factory']
```

## Privacy / data retention

When a buyer submits the checkout form, the following PII is stored temporarily:

- **PrivateTempStore** (session-bound) — used to display the thank-you page after
  the buyer returns from the payment provider. Expires with the user session.
- **Expirable key-value store** (keyed by payment ID) — used by the webhook handler
  to create the GiftCard entity. Expires after **1 hour** (hardcoded in
  `GiftCardService::storeCheckoutDataByPaymentId()`).

Data stored includes: sender name, sender email, recipient name, recipient email,
personal message, amount, and currency. No payment card data is handled by this
module — that remains with the payment provider.

## Routes

| Path | Description |
|------|-------------|
| `/gift-card/buy` | Public purchase form |
| `/gift-card/buy/thank-you` | Confirmation page shown after redirect back from the provider |
| `/gift-card/buy/cancel` | Cancellation page |
| `/gift-card/webhook` | Webhook endpoint (POST only; signature verification replaces CSRF) |
| `/admin/config/giftcard-core/settings` | Core module settings (currency, flood threshold) |
| `/admin/config/giftcard-core/rapyd-settings` | Rapyd API credentials (provided by `giftcard_rapyd`) |

## Maintainers

- [Vigfús Hauksson](https://www.drupal.org/u/vigfushauksson)
