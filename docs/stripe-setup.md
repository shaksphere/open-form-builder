# Stripe setup

Open Form Builder uses **Stripe Checkout** (dynamic sessions, not static payment
links). Each session carries the submission id in metadata; a webhook confirms
payment, which is when slots are depleted and emails/sheet export fire.

## 1. API keys (site-wide, once)

**Form Builder → Settings → Stripe**:

- Set **Mode** to Test while developing.
- Paste your **secret** and **publishable** keys (test and/or live) from
  Stripe → Developers → API keys.

Keys are stored once for the whole site, never inside individual form data.

## 2. Webhook

1. In Stripe → **Developers → Webhooks → Add endpoint**.
2. Endpoint URL — copy it from the Settings page (shown there):

   ```
   https://YOUR-SITE/wp-json/ofb/v1/webhook
   ```

3. Subscribe to the event: **`checkout.session.completed`**.
4. Copy the endpoint's **Signing secret** (`whsec_…`) and paste it into
   **Settings → Webhook signing secret**.

The webhook signature is verified on every call (HMAC-SHA256, 5-minute replay
window). Finalization is idempotent, so Stripe retries are safe.

## 3. Per-form

On a form's **Settings → Payments**, enable *Collect payment via Stripe
Checkout* and set the currency + line-item label. The amount charged is computed
by the Pricing rules engine (a single Stripe line item).

## Flow

1. Visitor submits → submission saved as `pending`.
2. Plugin creates a Checkout Session and redirects the visitor to Stripe.
3. On payment, Stripe calls the webhook → submission marked `paid`, slots
   depleted (first-pay-wins; over-capacity bookings flag the submission for
   staff), confirmation + receipt emails sent, Google Sheet row appended.
4. Visitor is returned to the per-form thank-you URL.

### Testing

Use Stripe test card `4242 4242 4242 4242`, any future expiry/CVC. To deliver
webhooks to a local site, use the Stripe CLI:

```
stripe listen --forward-to https://YOUR-LOCAL-SITE/wp-json/ofb/v1/webhook
```
