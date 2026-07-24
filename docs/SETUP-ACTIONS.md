# Setup actions & things you need to provide

This release (0.3.0) is fully working in the local test site. The items below are
things **only you can do** (they need your accounts/keys) to run it in production
or to switch on the optional integrations. Nothing here blocks the code — it all
ships working with sensible defaults.

## Required for taking live payments

- [ ] **Stripe live keys.** Test-mode keys are already validated (Checkout returns
      the correct amount). For production: Form Builder → Settings → set **Mode =
      Live** and paste your **live** secret + publishable keys.
- [ ] **Stripe webhook (production).** Add `https://YOUR-SITE/wp-json/ofb/v1/webhook`
      in Stripe → Developers → Webhooks, subscribe to `checkout.session.completed`,
      and paste the signing secret into Settings. Without it, paid submissions are
      never finalized (no slot depletion, no emails). See `docs/stripe-setup.md`.
- [ ] **Transactional email / SMTP.** Confirmation + receipt emails go through
      `wp_mail`. On a real host, install an SMTP plugin (e.g. an SES/Postmark/SMTP
      connector) so mail actually delivers. (Local by Flywheel captures mail in
      Mailpit, so nothing leaves the machine during testing.)

## Optional integrations (switch on when you want them)

- [ ] **Mailchimp / MailerLite.** Add the API key in Form Builder → Settings, then
      per form: Settings → Email marketing → enable, choose provider, enter the
      audience/group ID, map the email/name fields. See `docs/pricing-and-marketing.md`.
- [ ] **Google Sheet export.** Deploy the Apps Script web app and paste its URL per
      form. See `docs/apps-script.md`.

## Security / SOC 2 posture (already implemented)

- Secrets (Stripe + marketing API keys) live in a single site-wide option, never in
  per-form JSON, so exporting/importing a form never leaks keys.
- The `.env` with local test keys is git-ignored; only `.env.example` (placeholders)
  is committed. Stripe keys are entered in the WP admin, not read from `.env`.
- Every payment amount is recomputed server-side from the stored schema; a price
  posted by the browser is ignored.
- The public submit endpoint is nonce-protected; the Stripe webhook verifies the
  HMAC-SHA256 signature with a 5-minute replay window; all DB writes use
  `$wpdb->prepare`; the field set is always re-derived from the stored schema.
- Marketing/sheet calls are fire-and-forget (non-blocking) so a third-party outage
  can never fail or stall a submission.

### Recommended before a real launch

- [ ] Put the site behind HTTPS (Stripe + webhooks require it in live mode).
- [ ] Decide on a data-retention policy for `wp_ofb_submissions` (it stores the
      submitted field values). Add a periodic purge if you don't need them long-term.
- [ ] If EU/UK visitors are in scope, add a consent checkbox field before the
      marketing opt-in and/or use Mailchimp double opt-in (a toggle on the form).
