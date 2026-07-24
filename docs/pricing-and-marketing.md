# Pricing models & email-marketing sync

## Pricing models

Open Form Builder has two pricing models, chosen per form on the **Pricing** tab
(the total is always recomputed server-side from the stored schema — a price sent
by the browser is never trusted).

### 1. By sessions

Count-based tiers for the session picker: a base price covering N base sessions,
an extra-session price, and per-block discounts (in `$` or `%`). Best for the
lesson/booking use case the session picker was built for.

### 2. By selected options

The total is:

```
base fee
  + price of each selected option (select / radio / checkbox)
  + each number field value × its unit price
```

Set it up:

1. **Pricing → model → "By selected options"**, and set a **base fee** (0 for none).
2. On each **choice field** (select/dropdown/radio/checkbox) in the **Build** tab,
   give priced options a **Price**. Priced options show a `(+$X)` hint on the form.
3. On a **Number** field, set a **Unit price** under "Number pricing" to multiply
   the entered quantity (e.g. *Number of rooms* × `$30`).

This unlocks course sign-ups (priced courses), service bookings (priced services +
add-ons), and quote calculators (add a number × unit price).

**Quotes without immediate payment:** enable pricing but leave **Payments** off.
The live total still shows, the button reads "Submit", and the computed total is
available in emails as `{amount}` — a quote-by-email flow.

## Email-marketing sync (Mailchimp / MailerLite)

On a completed (paid or free) submission, the submitter can be added to a
Mailchimp audience or a MailerLite group. It is fire-and-forget: a marketing
outage never blocks or fails a submission.

1. **Form Builder → Settings** (site-wide): paste your **Mailchimp API key**
   and/or **MailerLite API key**. Keys are stored once for the whole site, never
   inside per-form data (so exported/imported forms carry no secrets).
2. On a form's **Settings → Email marketing**: enable it, pick the provider, enter
   the **Audience/List ID** (Mailchimp) or **Group ID** (MailerLite), and map the
   **email** and (optional) **name** fields. Add comma-separated **tags** if you like.

### Where to find the IDs

- **Mailchimp** — the API key looks like `xxxxxxxx-usXX` (the `usXX` datacenter
  suffix is used automatically). Audience ID: Mailchimp → Audience → Settings →
  *Audience name and defaults* → **Audience ID**.
- **MailerLite** — create an API token under Integrations → API. Group ID: open the
  group in the dashboard; the ID is in the URL.
