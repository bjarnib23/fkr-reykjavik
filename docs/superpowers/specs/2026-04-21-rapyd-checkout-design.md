# Rapyd Hosted Checkout — Gift Card Payment Integration

**Date:** 2026-04-21  
**Branch:** feature/rapyd-checkout

---

## Overview

Replace the existing Valitor/Commerce checkout flow for gift cards with Rapyd Hosted Checkout. The Valitor module was built for virtual card creation (Eldum rétt use case) and is not suited for a general gift card payment page. Rapyd Hosted Checkout is the recommended replacement.

---

## User Flow

1. Customer visits the gift card page
2. Selects an amount (highlighted button) and fills in name, phone, email, confirm email, and optional notes — all on one page
3. Clicks "Proceed to payment" — browser redirects to Rapyd's hosted payment page
4. Customer pays on Rapyd's page
5. Rapyd redirects back to `/giftcard/thank-you` (success) or `/giftcard/cancel` (cancelled)
6. Rapyd sends a webhook to `/api/fkr/rapyd/webhook`
7. Drupal verifies the webhook signature, marks the Commerce order as completed, sends confirmation email

---

## Architecture

### New Drupal module: `fkr_rapyd`

**`RapydClient.php`**
- Responsible for signing and sending API requests to Rapyd
- Signs requests with HMAC-SHA256 using access key + secret key
- Single public method: `createCheckout(array $params): array`
- Reads credentials from `fkr_rapyd.settings` Drupal config (sandbox/live toggle)
- Sandbox base URL: `https://sandboxapi.rapyd.net/v1/`
- Live base URL: `https://api.rapyd.net/v1/`

**`WebhookController.php`**
- Endpoint: `POST /api/fkr/rapyd/webhook`
- Verifies Rapyd HMAC-SHA256 signature from request headers before processing
- On `PAYMENT_COMPLETED` event: loads Commerce order by ID from webhook metadata, generates a unique gift card code (random alphanumeric string), stores the code in the order's `field_giftcard_code` field, transitions order to `completed` state, sends confirmation email including the gift card code
- Returns 200 in all cases to prevent Rapyd retrying (logs errors instead of failing)

**`fkr_rapyd.routing.yml`**
- Registers `/api/fkr/rapyd/webhook` as publicly accessible POST endpoint

**`fkr_rapyd.settings.yml`** (default config)
- `access_key: ''`
- `secret_key: ''`
- `sandbox: true`

---

### Updated module: `fkr_giftcard`

**`GiftCardController::checkout()`**
- After creating the Commerce order (draft), calls `RapydClient::createCheckout()` with order ID, amount, currency (ISK), and redirect URLs
- Returns `{ checkout_url }` pointing to Rapyd instead of the old `/checkout/giftcard/start/` URL

**Removed:**
- `GiftCardController::startCheckout()` — no longer needed (Commerce cart session logic goes away)
- Corresponding route in `fkr_giftcard.routing.yml`

---

### React changes

**`GiftCard.jsx`** — single page form:
- Amount picker: buttons loaded from `GET /api/fkr/giftcard/amounts`, clicking one selects/highlights it
- Form fields: Name, Phone, Email, Confirm Email, Notes (optional)
- Selected amount shown clearly above the form fields (fixed once picked)
- Submit: "Proceed to payment" → POST `/api/fkr/giftcard/checkout` → redirect to `checkout_url`
- Loading state while fetching amounts or submitting
- Inline validation (required fields, email match)
- Error state if API is unavailable

**New pages:**
- `/giftcard/thank-you` — "Payment successful. A confirmation email has been sent." + home button
- `/giftcard/cancel` — "Payment was cancelled." + back to gift card page button

---

## Error Handling

| Scenario | Behaviour |
|---|---|
| Rapyd API down during checkout creation | 503 returned to React, user sees "try again" message |
| Customer cancels on Rapyd page | Redirect to `/giftcard/cancel` |
| Webhook signature invalid | 401, request ignored |
| Webhook for unknown order | Log, return 200 |
| Webhook for already-completed order | Skip silently, return 200 |
| Email sending fails | Log error, order still marked complete |

---

## API Credentials

Stored in Drupal config (`fkr_rapyd.settings`). No code changes required to switch from sandbox to live — only config values need updating. Credentials are left blank until provided by Rapyd.

---

## What Is Not Changing

- Commerce product variations and SKUs for gift card amounts (`GJAFABREF-*`) remain as-is
- `GET /api/fkr/giftcard/amounts` endpoint remains as-is
- Commerce Orders admin view — completed orders will continue to appear there
- The `commerce_valitor` module is left in place (used for other purposes or can be removed separately)
