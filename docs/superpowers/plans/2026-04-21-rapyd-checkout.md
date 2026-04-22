# Rapyd Hosted Checkout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Valitor/Commerce checkout flow for gift cards with Rapyd Hosted Checkout, including a new React gift card form, a new `fkr_rapyd` Drupal module, and a webhook handler that marks orders complete and emails a gift card code to the buyer.

**Architecture:** React `GiftCard.jsx` (single-page form) posts to the existing `/api/fkr/giftcard/checkout` endpoint, which creates a Commerce order and calls `RapydClient` to obtain a Rapyd-hosted checkout URL. The browser redirects to Rapyd. On payment, Rapyd POSTs a webhook to `/api/fkr/rapyd/webhook`; `WebhookController` verifies the HMAC signature, generates a unique gift card code, stores it on the order, transitions the order to `completed`, and sends a confirmation email to the buyer.

**Tech Stack:** PHP 8.1, Drupal 10/11, Drupal Commerce 3.x, React 18, react-router-dom, Rapyd REST API v1

---

## File Map

### New files
| File | Responsibility |
|---|---|
| `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.info.yml` | Module declaration |
| `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.routing.yml` | Webhook route |
| `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.services.yml` | RapydClient service |
| `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.module` | hook_mail for gift card email |
| `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.install` | Creates field_giftcard_code on commerce_order |
| `drupal/web/modules/custom/fkr_rapyd/config/install/fkr_rapyd.settings.yml` | Default config (blank keys, sandbox=true) |
| `drupal/web/modules/custom/fkr_rapyd/src/RapydClient.php` | Signs + sends Rapyd API requests |
| `drupal/web/modules/custom/fkr_rapyd/src/Controller/WebhookController.php` | Handles Rapyd webhook POSTs |
| `react/src/pages/GiftCard.jsx` | Gift card purchase form |
| `react/src/pages/GiftCard.css` | Styles for gift card page |
| `react/src/pages/GiftCardThankYou.jsx` | Post-payment success page |
| `react/src/pages/GiftCardCancel.jsx` | Post-payment cancel page |

### Modified files
| File | Change |
|---|---|
| `drupal/web/modules/custom/fkr_giftcard/src/Controller/GiftCardController.php` | Use RapydClient; remove startCheckout() |
| `drupal/web/modules/custom/fkr_giftcard/fkr_giftcard.routing.yml` | Remove start_checkout route |
| `react/src/App.jsx` | Add /gjafabref, /giftcard/thank-you, /giftcard/cancel routes |

---

## Task 1: Create `fkr_rapyd` module skeleton

**Files:**
- Create: `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.info.yml`
- Create: `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.routing.yml`
- Create: `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.services.yml`
- Create: `drupal/web/modules/custom/fkr_rapyd/config/install/fkr_rapyd.settings.yml`

- [ ] **Step 1: Create `fkr_rapyd.info.yml`**

```yaml
name: FKR Rapyd
description: Integrates Rapyd Hosted Checkout for gift card payments.
type: module
core_version_requirement: ^10 || ^11
package: Custom
dependencies:
  - commerce:commerce_order
```

- [ ] **Step 2: Create `fkr_rapyd.routing.yml`**

```yaml
fkr_rapyd.webhook:
  path: '/api/fkr/rapyd/webhook'
  defaults:
    _controller: '\Drupal\fkr_rapyd\Controller\WebhookController::handle'
  methods: [POST]
  requirements:
    _access: 'TRUE'
```

- [ ] **Step 3: Create `fkr_rapyd.services.yml`**

```yaml
services:
  fkr_rapyd.client:
    class: Drupal\fkr_rapyd\RapydClient
    arguments:
      - '@config.factory'
      - '@http_client'
      - '@logger.factory'
```

- [ ] **Step 4: Create `config/install/fkr_rapyd.settings.yml`**

```yaml
access_key: ''
secret_key: ''
sandbox: true
frontend_url: 'http://localhost:5173'
```

- [ ] **Step 5: Commit**

```bash
git add drupal/web/modules/custom/fkr_rapyd/
git commit -m "feat: scaffold fkr_rapyd module"
```

---

## Task 2: Implement `RapydClient`

**Files:**
- Create: `drupal/web/modules/custom/fkr_rapyd/src/RapydClient.php`

- [ ] **Step 1: Create `RapydClient.php`**

```php
<?php

namespace Drupal\fkr_rapyd;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class RapydClient {

  private string $accessKey;
  private string $secretKey;
  private bool $sandbox;
  private string $frontendUrl;
  private ClientInterface $http;
  private \Psr\Log\LoggerInterface $logger;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $config = $config_factory->get('fkr_rapyd.settings');
    $this->accessKey   = $config->get('access_key') ?? '';
    $this->secretKey   = $config->get('secret_key') ?? '';
    $this->sandbox     = (bool) ($config->get('sandbox') ?? TRUE);
    $this->frontendUrl = rtrim($config->get('frontend_url') ?? 'http://localhost:5173', '/');
    $this->http        = $http_client;
    $this->logger      = $logger_factory->get('fkr_rapyd');
  }

  /**
   * Creates a Rapyd Hosted Checkout session.
   *
   * @param int    $order_id  Commerce order ID
   * @param int    $amount    Amount in whole ISK (e.g. 10000)
   * @param string $email     Buyer email
   *
   * @return array{redirect_url: string}
   *
   * @throws \RuntimeException on API error or missing credentials
   */
  public function createCheckout(int $order_id, int $amount, string $email): array {
    if (empty($this->accessKey) || empty($this->secretKey)) {
      throw new \RuntimeException('Rapyd API credentials are not configured.');
    }

    $base    = $this->sandbox ? 'https://sandboxapi.rapyd.net' : 'https://api.rapyd.net';
    $path    = '/v1/checkout';
    $body    = [
      'amount'                => $amount,
      'currency'              => 'ISK',
      'country'               => 'IS',
      'complete_payment_url'  => $this->frontendUrl . '/giftcard/thank-you?order_id=' . $order_id,
      'error_payment_url'     => $this->frontendUrl . '/giftcard/cancel',
      'merchant_reference_id' => 'fkr-order-' . $order_id,
      'metadata'              => ['order_id' => $order_id],
      'requested_by'          => $email,
    ];

    $headers = $this->buildHeaders('post', $path, $body);

    try {
      $response = $this->http->request('POST', $base . $path, [
        'headers' => $headers,
        'json'    => $body,
      ]);

      $decoded = json_decode((string) $response->getBody(), TRUE);

      if (empty($decoded['data']['redirect_url'])) {
        throw new \RuntimeException('Rapyd response missing redirect_url.');
      }

      return ['redirect_url' => $decoded['data']['redirect_url']];
    }
    catch (GuzzleException $e) {
      $this->logger->error('Rapyd API error: @msg', ['@msg' => $e->getMessage()]);
      throw new \RuntimeException('Rapyd API request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Verifies a Rapyd webhook signature.
   *
   * @param string $raw_body   Raw POST body string
   * @param string $salt       From rapyd-idempotency header
   * @param string $timestamp  From rapyd-timestamp header
   * @param string $signature  From rapyd-signature header
   */
  public function verifyWebhook(string $raw_body, string $salt, string $timestamp, string $signature): bool {
    if (empty($this->secretKey)) {
      return FALSE;
    }
    $to_sign  = 'post' . '/api/fkr/rapyd/webhook' . $salt . $timestamp . $this->accessKey . $this->secretKey . $raw_body;
    $expected = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey, TRUE));
    return hash_equals($expected, $signature);
  }

  private function buildHeaders(string $method, string $path, array $body): array {
    $salt      = bin2hex(random_bytes(8));
    $timestamp = (string) time();
    $body_str  = empty($body) ? '' : json_encode($body);
    $to_sign   = strtolower($method) . $path . $salt . $timestamp . $this->accessKey . $this->secretKey . $body_str;
    $signature = base64_encode(hash_hmac('sha256', $to_sign, $this->secretKey, TRUE));

    return [
      'access_key'   => $this->accessKey,
      'salt'         => $salt,
      'timestamp'    => $timestamp,
      'signature'    => $signature,
      'Content-Type' => 'application/json',
    ];
  }

}
```

- [ ] **Step 2: Verify the file is syntactically valid**

```bash
cd /Users/bjarnianton/Projects/fkr-reykjavik && php -l drupal/web/modules/custom/fkr_rapyd/src/RapydClient.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add drupal/web/modules/custom/fkr_rapyd/src/RapydClient.php
git commit -m "feat: add RapydClient for checkout session creation and webhook verification"
```

---

## Task 3: Add `field_giftcard_code` to Commerce orders

**Files:**
- Create: `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.install`

- [ ] **Step 1: Create `fkr_rapyd.install`**

```php
<?php

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Implements hook_install().
 */
function fkr_rapyd_install() {
  if (!FieldStorageConfig::loadByName('commerce_order', 'field_giftcard_code')) {
    FieldStorageConfig::create([
      'field_name'  => 'field_giftcard_code',
      'entity_type' => 'commerce_order',
      'type'        => 'string',
      'cardinality' => 1,
    ])->save();
  }

  if (!FieldConfig::loadByName('commerce_order', 'default', 'field_giftcard_code')) {
    FieldConfig::create([
      'field_name'  => 'field_giftcard_code',
      'entity_type' => 'commerce_order',
      'bundle'      => 'default',
      'label'       => 'Gift Card Code',
    ])->save();
  }
}

/**
 * Implements hook_uninstall().
 */
function fkr_rapyd_uninstall() {
  $field = FieldConfig::loadByName('commerce_order', 'default', 'field_giftcard_code');
  if ($field) {
    $field->delete();
  }
  $storage = FieldStorageConfig::loadByName('commerce_order', 'field_giftcard_code');
  if ($storage) {
    $storage->delete();
  }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.install
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.install
git commit -m "feat: install/uninstall field_giftcard_code on commerce_order"
```

---

## Task 4: Implement `hook_mail` for gift card confirmation

**Files:**
- Create: `drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.module`

- [ ] **Step 1: Create `fkr_rapyd.module`**

```php
<?php

/**
 * Implements hook_mail().
 */
function fkr_rapyd_mail($key, &$message, $params) {
  if ($key === 'giftcard_confirmation') {
    $message['from']    = \Drupal::config('system.site')->get('mail');
    $message['subject'] = t('Gjafabréf frá FKR Reykjavík');
    $message['body'][]  = t('Hæ @name,', ['@name' => $params['name']]);
    $message['body'][]  = t('Takk fyrir kaupið! Hér er gjafabréfið þitt:');
    $message['body'][]  = t('Gjafabréfskóði: @code', ['@code' => $params['code']]);
    $message['body'][]  = t('Upphæð: @amount kr.', ['@amount' => number_format($params['amount'], 0, '.', '.')]);
    $message['body'][]  = t('Kveðja, FKR Reykjavík');
  }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.module
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add drupal/web/modules/custom/fkr_rapyd/fkr_rapyd.module
git commit -m "feat: add hook_mail for gift card confirmation email"
```

---

## Task 5: Implement `WebhookController`

**Files:**
- Create: `drupal/web/modules/custom/fkr_rapyd/src/Controller/WebhookController.php`

- [ ] **Step 1: Create `WebhookController.php`**

```php
<?php

namespace Drupal\fkr_rapyd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\fkr_rapyd\RapydClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WebhookController extends ControllerBase {

  public function __construct(
    private RapydClient $rapydClient,
    private EntityTypeManagerInterface $entityTypeManager,
    private MailManagerInterface $mailManager,
    private LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('fkr_rapyd.client'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory'),
    );
  }

  public function handle(Request $request): JsonResponse {
    $raw_body  = $request->getContent();
    $salt      = $request->headers->get('rapyd-idempotency', '');
    $timestamp = $request->headers->get('rapyd-timestamp', '');
    $signature = $request->headers->get('rapyd-signature', '');

    if (!$this->rapydClient->verifyWebhook($raw_body, $salt, $timestamp, $signature)) {
      $this->loggerFactory->get('fkr_rapyd')->warning('Webhook signature verification failed.');
      return new JsonResponse(['error' => 'Invalid signature'], 401);
    }

    $payload = json_decode($raw_body, TRUE);
    $type    = $payload['type'] ?? '';

    if ($type !== 'PAYMENT_COMPLETED') {
      return new JsonResponse(['status' => 'ignored']);
    }

    $order_id = $payload['data']['metadata']['order_id'] ?? NULL;
    if (!$order_id) {
      $this->loggerFactory->get('fkr_rapyd')->error('Webhook missing order_id in metadata.');
      return new JsonResponse(['status' => 'ok']);
    }

    $storage = $this->entityTypeManager->getStorage('commerce_order');
    $order   = $storage->load($order_id);

    if (!$order) {
      $this->loggerFactory->get('fkr_rapyd')->error('Webhook: order @id not found.', ['@id' => $order_id]);
      return new JsonResponse(['status' => 'ok']);
    }

    if ($order->getState()->getId() === 'completed') {
      return new JsonResponse(['status' => 'ok']);
    }

    $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $order->set('field_giftcard_code', $code);
    // Commerce default workflow: draft → place → pending → fulfill → completed
    $order->getState()->applyTransitionById('place');
    $order->getState()->applyTransitionById('fulfill');
    $order->save();

    $email    = $order->getEmail();
    $name     = $order->getBillingProfile()?->get('address')->given_name ?? $order->getEmail();
    $amount   = (int) $order->getTotalPrice()->getNumber();
    $langcode = \Drupal::config('system.site')->get('langcode');

    try {
      $this->mailManager->mail('fkr_rapyd', 'giftcard_confirmation', $email, $langcode, [
        'name'   => $name,
        'code'   => $code,
        'amount' => $amount,
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('fkr_rapyd')->error('Gift card email failed for order @id: @msg', [
        '@id'  => $order_id,
        '@msg' => $e->getMessage(),
      ]);
    }

    return new JsonResponse(['status' => 'ok']);
  }

}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l drupal/web/modules/custom/fkr_rapyd/src/Controller/WebhookController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add drupal/web/modules/custom/fkr_rapyd/src/Controller/WebhookController.php
git commit -m "feat: add WebhookController for Rapyd payment confirmation"
```

---

## Task 6: Enable `fkr_rapyd` and update `fkr_giftcard`

**Files:**
- Modify: `drupal/web/modules/custom/fkr_giftcard/src/Controller/GiftCardController.php`
- Modify: `drupal/web/modules/custom/fkr_giftcard/fkr_giftcard.routing.yml`

- [ ] **Step 1: Enable `fkr_rapyd` via drush**

```bash
cd /Users/bjarnianton/Projects/fkr-reykjavik/drupal && ddev drush en fkr_rapyd -y
```

Expected output includes: `fkr_rapyd was enabled successfully.`

- [ ] **Step 2: Verify `field_giftcard_code` was created**

```bash
ddev drush php-eval "print_r(\Drupal\field\Entity\FieldConfig::loadByName('commerce_order', 'default', 'field_giftcard_code')->label());"
```

Expected: `Gift Card Code`

- [ ] **Step 3: Update `GiftCardController.php`**

Replace the entire file with:

```php
<?php

namespace Drupal\fkr_giftcard\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\fkr_rapyd\RapydClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GiftCardController extends ControllerBase {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    private RapydClient $rapydClient,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('fkr_rapyd.client'),
    );
  }

  /**
   * GET /api/fkr/giftcard/amounts
   */
  public function amounts(): JsonResponse {
    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadByProperties(['type' => 'default', 'status' => 1]);

    $amounts = [];
    foreach ($variations as $variation) {
      $sku = $variation->getSku();
      if (strpos($sku, 'GJAFABREF-') === 0) {
        $amount    = (int) str_replace('GJAFABREF-', '', $sku);
        $amounts[] = [
          'sku'    => $sku,
          'amount' => $amount,
          'label'  => number_format($amount, 0, '.', '.') . ' kr',
        ];
      }
    }

    usort($amounts, fn($a, $b) => $a['amount'] - $b['amount']);

    return new JsonResponse($amounts, 200, $this->cors());
  }

  /**
   * POST /api/fkr/giftcard/checkout
   * Expected body: { sku, name, email, phone, notes }
   */
  public function checkout(Request $request): JsonResponse {
    if ($request->getMethod() === 'OPTIONS') {
      return new JsonResponse([], 200, $this->cors());
    }

    $data = json_decode($request->getContent(), TRUE);

    foreach (['sku', 'name', 'email', 'phone'] as $field) {
      if (empty($data[$field])) {
        return new JsonResponse(['error' => "Missing required field: $field"], 400, $this->cors());
      }
    }

    $variations = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->loadByProperties(['sku' => $data['sku'], 'status' => 1]);

    if (empty($variations)) {
      return new JsonResponse(['error' => 'Invalid gift card amount.'], 400, $this->cors());
    }

    $variation = reset($variations);
    $stores    = $this->entityTypeManager->getStorage('commerce_store')->loadMultiple();
    $store     = reset($stores);

    $order_item = OrderItem::create([
      'type'             => 'default',
      'purchased_entity' => $variation,
      'quantity'         => 1,
      'unit_price'       => $variation->getPrice(),
    ]);
    $order_item->save();

    $notes = sprintf(
      'Kaupandi: %s | Sími: %s | Athugasemd: %s',
      $data['name'],
      $data['phone'],
      $data['notes'] ?? ''
    );

    $order = Order::create([
      'type'        => 'default',
      'state'       => 'draft',
      'mail'        => $data['email'],
      'uid'         => 0,
      'store_id'    => $store->id(),
      'order_items' => [$order_item],
      'notes'       => $notes,
    ]);
    $order->save();

    try {
      $amount   = (int) $variation->getPrice()->getNumber();
      $result   = $this->rapydClient->createCheckout($order->id(), $amount, $data['email']);
      return new JsonResponse(['checkout_url' => $result['redirect_url']], 200, $this->cors());
    }
    catch (\RuntimeException $e) {
      $order->delete();
      return new JsonResponse(['error' => 'Payment service unavailable. Please try again.'], 503, $this->cors());
    }
  }

  private function cors(): array {
    return [
      'Access-Control-Allow-Origin'  => '*',
      'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type',
    ];
  }

}
```

- [ ] **Step 4: Verify syntax**

```bash
php -l drupal/web/modules/custom/fkr_giftcard/src/Controller/GiftCardController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Remove `startCheckout` route from `fkr_giftcard.routing.yml`**

Final file should be:

```yaml
fkr_giftcard.amounts:
  path: '/api/fkr/giftcard/amounts'
  defaults:
    _controller: '\Drupal\fkr_giftcard\Controller\GiftCardController::amounts'
  methods: [GET]
  requirements:
    _access: 'TRUE'

fkr_giftcard.checkout:
  path: '/api/fkr/giftcard/checkout'
  defaults:
    _controller: '\Drupal\fkr_giftcard\Controller\GiftCardController::checkout'
  methods: [POST, OPTIONS]
  requirements:
    _access: 'TRUE'
```

- [ ] **Step 6: Clear Drupal cache**

```bash
ddev drush cr
```

Expected: `Cache rebuild complete.`

- [ ] **Step 7: Commit**

```bash
git add drupal/web/modules/custom/fkr_giftcard/
git commit -m "feat: update fkr_giftcard to use RapydClient instead of Valitor"
```

---

## Task 7: Build `GiftCard.jsx`

**Files:**
- Modify: `react/src/pages/GiftCard.jsx`
- Create: `react/src/pages/GiftCard.css`

- [ ] **Step 1: Create `GiftCard.css`**

```css
.giftcard-wrapper {
  max-width: 600px;
  margin: 60px auto;
  padding: 0 24px;
}

.giftcard-wrapper h1 {
  text-align: center;
  font-size: 36px;
  letter-spacing: 4px;
  margin-bottom: 32px;
}

.amount-options {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 32px;
}

.amount-btn {
  padding: 12px 20px;
  border: 1px solid #ccc;
  background: white;
  cursor: pointer;
  border-radius: 8px;
  font-size: 15px;
  font-family: inherit;
}

.amount-btn.selected {
  background: #263A38;
  color: white;
  border-color: #263A38;
}

.selected-amount {
  font-size: 15px;
  color: #263A38;
  font-weight: 600;
  margin-bottom: 20px;
}

.giftcard-wrapper input,
.giftcard-wrapper textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #ccc;
  font-size: 15px;
  font-family: inherit;
  margin-bottom: 12px;
  background: white;
  box-sizing: border-box;
}

.giftcard-wrapper input.error {
  border-color: #c0392b;
}

.error-msg {
  color: #c0392b;
  font-size: 13px;
  margin-bottom: 12px;
  margin-top: -8px;
}

.submit-btn {
  width: 100%;
  background: #263A38;
  color: white;
  border: none;
  padding: 14px 24px;
  font-size: 16px;
  font-family: inherit;
  cursor: pointer;
  margin-top: 8px;
}

.submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.api-error {
  color: #c0392b;
  margin-top: 12px;
  font-size: 14px;
}
```

- [ ] **Step 2: Write `GiftCard.jsx`**

```jsx
import { useState, useEffect } from 'react'
import './GiftCard.css'

const API = 'http://fkr-reykjavik.ddev.site'

function GiftCard() {
  const [amounts, setAmounts]     = useState([])
  const [selected, setSelected]   = useState(null)
  const [form, setForm]           = useState({ name: '', email: '', confirmEmail: '', phone: '', notes: '' })
  const [errors, setErrors]       = useState({})
  const [apiError, setApiError]   = useState('')
  const [loading, setLoading]     = useState(false)

  useEffect(() => {
    fetch(`${API}/api/fkr/giftcard/amounts`)
      .then(r => r.json())
      .then(setAmounts)
  }, [])

  function update(field, value) {
    setForm(f => ({ ...f, [field]: value }))
    setErrors(e => ({ ...e, [field]: '' }))
  }

  function validate() {
    const e = {}
    if (!selected)              e.amount       = 'Veldu upphæð'
    if (!form.name.trim())      e.name         = 'Nafn vantar'
    if (!form.phone.trim())     e.phone        = 'Símanúmer vantar'
    if (!form.email.trim())     e.email        = 'Tölvupóstur vantar'
    else if (!/\S+@\S+\.\S+/.test(form.email)) e.email = 'Ógildur tölvupóstur'
    if (form.email !== form.confirmEmail)       e.confirmEmail = 'Tölvupóstar passa ekki'
    return e
  }

  async function handleSubmit(e) {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length) { setErrors(errs); return }

    setLoading(true)
    setApiError('')

    try {
      const res  = await fetch(`${API}/api/fkr/giftcard/checkout`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          sku:   selected.sku,
          name:  form.name,
          email: form.email,
          phone: form.phone,
          notes: form.notes,
        }),
      })
      const json = await res.json()
      if (!res.ok) { setApiError(json.error || 'Villa kom upp. Reyndu aftur.'); return }
      window.location.href = json.checkout_url
    }
    catch {
      setApiError('Greiðsluþjónusta ekki aðgengileg. Reyndu aftur.')
    }
    finally {
      setLoading(false)
    }
  }

  return (
    <main className="giftcard-wrapper">
      <h1>GJAFABRÉF</h1>

      <div className="amount-options">
        {amounts.map(a => (
          <button
            key={a.sku}
            className={`amount-btn${selected?.sku === a.sku ? ' selected' : ''}`}
            onClick={() => setSelected(a)}
            type="button"
          >
            {a.label}
          </button>
        ))}
      </div>
      {errors.amount && <p className="error-msg">{errors.amount}</p>}

      {selected && (
        <p className="selected-amount">Valin upphæð: {selected.label}</p>
      )}

      <form onSubmit={handleSubmit} noValidate>
        <input
          placeholder="Nafn *"
          value={form.name}
          onChange={e => update('name', e.target.value)}
          className={errors.name ? 'error' : ''}
        />
        {errors.name && <p className="error-msg">{errors.name}</p>}

        <input
          placeholder="Sími *"
          value={form.phone}
          onChange={e => update('phone', e.target.value)}
          className={errors.phone ? 'error' : ''}
        />
        {errors.phone && <p className="error-msg">{errors.phone}</p>}

        <input
          type="email"
          placeholder="Tölvupóstur *"
          value={form.email}
          onChange={e => update('email', e.target.value)}
          className={errors.email ? 'error' : ''}
        />
        {errors.email && <p className="error-msg">{errors.email}</p>}

        <input
          type="email"
          placeholder="Staðfesta tölvupóst *"
          value={form.confirmEmail}
          onChange={e => update('confirmEmail', e.target.value)}
          className={errors.confirmEmail ? 'error' : ''}
        />
        {errors.confirmEmail && <p className="error-msg">{errors.confirmEmail}</p>}

        <textarea
          placeholder="Athugasemd (valfrjálst)"
          value={form.notes}
          onChange={e => update('notes', e.target.value)}
          rows={3}
        />

        <button type="submit" className="submit-btn" disabled={loading}>
          {loading ? 'Hinkraðu...' : 'Greiða'}
        </button>

        {apiError && <p className="api-error">{apiError}</p>}
      </form>
    </main>
  )
}

export default GiftCard
```

- [ ] **Step 3: Commit**

```bash
git add react/src/pages/GiftCard.jsx react/src/pages/GiftCard.css
git commit -m "feat: build GiftCard page with amount picker and checkout form"
```

---

## Task 8: Add thank-you and cancel pages, update routing

**Files:**
- Create: `react/src/pages/GiftCardThankYou.jsx`
- Create: `react/src/pages/GiftCardCancel.jsx`
- Modify: `react/src/App.jsx`

- [ ] **Step 1: Create `GiftCardThankYou.jsx`**

```jsx
import { Link } from 'react-router-dom'

function GiftCardThankYou() {
  return (
    <main style={{ maxWidth: 600, margin: '80px auto', padding: '0 24px', textAlign: 'center' }}>
      <h1 style={{ fontSize: 36, letterSpacing: 4, marginBottom: 24 }}>TAKK FYRIR</h1>
      <p style={{ fontSize: 16, lineHeight: 1.6, marginBottom: 8 }}>
        Greiðslan tókst. Gjafabréfið þitt hefur verið sent á tölvupóstfangið þitt.
      </p>
      <p style={{ fontSize: 14, color: '#666', marginBottom: 40 }}>
        Ef þú færð ekki tölvupóst innan nokkrar mínútur skaltu athuga ruslpóstinn.
      </p>
      <Link
        to="/"
        style={{
          background: '#263A38',
          color: 'white',
          padding: '14px 32px',
          textDecoration: 'none',
          fontSize: 15,
          fontFamily: 'inherit',
        }}
      >
        Til baka á forsíðu
      </Link>
    </main>
  )
}

export default GiftCardThankYou
```

- [ ] **Step 2: Create `GiftCardCancel.jsx`**

```jsx
import { Link } from 'react-router-dom'

function GiftCardCancel() {
  return (
    <main style={{ maxWidth: 600, margin: '80px auto', padding: '0 24px', textAlign: 'center' }}>
      <h1 style={{ fontSize: 36, letterSpacing: 4, marginBottom: 24 }}>GREIÐSLU HÆTT VIÐ</h1>
      <p style={{ fontSize: 16, lineHeight: 1.6, marginBottom: 40 }}>
        Greiðslunni var hætt við. Ekkert var rukkað.
      </p>
      <Link
        to="/gjafabref"
        style={{
          background: '#263A38',
          color: 'white',
          padding: '14px 32px',
          textDecoration: 'none',
          fontSize: 15,
          fontFamily: 'inherit',
        }}
      >
        Reyna aftur
      </Link>
    </main>
  )
}

export default GiftCardCancel
```

- [ ] **Step 3: Update `App.jsx`**

```jsx
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import Home from './pages/Home'
import Booking from './pages/booking/Booking'
import FAQ from './pages/FAQ'
import PriceList from './pages/PriceList'
import GiftCard from './pages/GiftCard'
import GiftCardThankYou from './pages/GiftCardThankYou'
import GiftCardCancel from './pages/GiftCardCancel'

function App() {
  return (
    <BrowserRouter>
      <Navbar />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/boka-tima" element={<Booking />} />
        <Route path="/ferlid" element={<p>Ferlið</p>} />
        <Route path="/algengar-spurningar" element={<FAQ />} />
        <Route path="/gjafabref" element={<GiftCard />} />
        <Route path="/verdskra" element={<PriceList />} />
        <Route path="/giftcard/thank-you" element={<GiftCardThankYou />} />
        <Route path="/giftcard/cancel" element={<GiftCardCancel />} />
      </Routes>
      <Footer />
    </BrowserRouter>
  )
}

export default App
```

- [ ] **Step 4: Start the dev server and verify the gift card page loads**

```bash
cd /Users/bjarnianton/Projects/fkr-reykjavik/react && npm run dev
```

Open `http://localhost:5173/gjafabref` — you should see the gift card page with amount buttons loaded from the API.

Open `http://localhost:5173/giftcard/thank-you` — thank you page with home button.

Open `http://localhost:5173/giftcard/cancel` — cancel page with back button.

- [ ] **Step 5: Commit**

```bash
git add react/src/pages/GiftCardThankYou.jsx react/src/pages/GiftCardCancel.jsx react/src/App.jsx
git commit -m "feat: add gift card thank-you and cancel pages, wire up React routes"
```

---

## Task 9: Configure API credentials and end-to-end smoke test

- [ ] **Step 1: Once Rapyd sandbox credentials are received, set them in Drupal config**

```bash
ddev drush config-set fkr_rapyd.settings access_key "YOUR_ACCESS_KEY" -y
ddev drush config-set fkr_rapyd.settings secret_key "YOUR_SECRET_KEY" -y
ddev drush config-set fkr_rapyd.settings frontend_url "http://localhost:5173" -y
ddev drush cr
```

- [ ] **Step 2: Smoke test the checkout flow**

1. Open `http://localhost:5173/gjafabref`
2. Select an amount, fill in the form, click "Greiða"
3. Confirm browser redirects to `checkout.rapyd.net` (sandbox)
4. Complete payment using Rapyd test card: `4111 1111 1111 1111`, any future expiry, any CVV
5. Confirm redirect to `http://localhost:5173/giftcard/thank-you`

- [ ] **Step 3: Verify webhook via Rapyd dashboard**

In the Rapyd sandbox dashboard, check that the webhook was delivered to `/api/fkr/rapyd/webhook` with status 200.

- [ ] **Step 4: Verify order in Drupal Commerce**

Go to `http://fkr-reykjavik.ddev.site/admin/commerce/orders` — the order should show state `Completed`.

Click the order — `field_giftcard_code` should have a value (e.g. `A3F9B2C1D4`).

- [ ] **Step 5: Verify confirmation email**

Check the inbox for the email address used in the form — it should contain the gift card code and amount.

- [ ] **Step 6: Final commit**

```bash
git add -A
git commit -m "feat: Rapyd Hosted Checkout integration complete"
```
