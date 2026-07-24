=== Open Form Builder ===
Contributors: openformbuilder
Tags: forms, form builder, multi-step, stripe, conditional logic
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A CF7-style form builder with 5 starter templates, multi-step wizards,
conditional logic, image-card choice fields, a capacity-aware session picker,
two pricing models (sessions or priced options/quantities), per-form branding,
Stripe Checkout, date/time fields, conditional emails, Mailchimp/MailerLite
sync, Google Sheet export and CF7 import. Forms are declarative JSON schemas.

== Description ==

Open Form Builder stores each form as a clean, declarative JSON schema rendered
to front-end HTML by a render engine. Drop a form anywhere with the shortcode:

`[open_form id="123"]`

Features:

* React admin builder using @wordpress/components (matches Gutenberg).
* Multi-step (wizard) forms with drag-and-drop fields.
* 5 starter templates (Contact, Lesson & Session Booking, Course & Certification
  Enrolment, Service Booking, Quote / Estimate Calculator) — pick one to prefill
  a working, on-brand form; every field, price and colour stays editable.
* Field types: text, email, tel, number, date, time, textarea, select, dropdown,
  radio, checkbox, HTML content block, and a capacity-aware session picker.
* Radio/checkbox fields can render as an image-card grid (with a WP media-library
  picker) instead of a plain list — for courses, services or products.
* Per-form branding: accent colour, text colour, background and corner radius,
  applied everywhere on the front end (buttons, active steps, selected cards).
* Per-field conditional show/hide.
* Personalisation tags ({field_name}) in confirmation content and emails.
* Two pricing models:
  * By sessions — base price + tiers with per-block discounts in $ or %.
  * By selected options — a base fee plus per-option prices (courses, services,
    add-ons) and number fields with a unit price (e.g. rooms x $30). Ideal for
    bookings, service quotes and course sign-ups.
* Stripe Checkout; session slots deplete on payment success only (first-pay-wins).
* Confirmation + receipt emails via wp_mail with CF7-style conditional routing.
* Mailchimp / MailerLite audience sync on completed submissions.
* Google Sheet export via a Google Apps Script web app (no OAuth).
* Import CF7 form fields + mail templates.

== Installation ==

1. Upload the `open-form-builder` folder to `/wp-content/plugins/`.
2. Activate it. Custom tables are created automatically.
3. Set Stripe keys under Form Builder → Settings (see docs/stripe-setup.md).
4. Build a form, copy its shortcode, and drop it on any page or post.

== Changelog ==

= 0.4.0 =
* 5 built-in starter templates covering the most common use cases: contact,
  lesson/session booking, course & certification enrolment, service booking,
  and a no-payment quote calculator. Pick one from the "Add new form" screen.
* Image-card layout for radio/checkbox fields (with a WP media picker per
  option) — built for course, product and service pickers.
* Per-form branding: accent/text/background colour and corner radius, set on
  the Settings tab and applied across the whole front-end form via CSS custom
  properties, with a live preview in the builder.
* General front-end visual pass: the form now renders as a shadowed surface
  card; refined buttons, chips, progress steps and pricing summary.

= 0.3.0 =
* New "priced options" pricing model: per-option prices on select/radio/checkbox
  fields, plus number x unit-price, with a live total and Stripe charge. Unlocks
  course sign-ups, service bookings and quote calculators.
* New field types: date and time.
* Mailchimp / MailerLite subscriber sync on completed submissions.
* Fixed a front-end error that broke live totals when a session picker had a
  maximum set.

= 0.2.1 =
* Stripe Checkout, conditional emails, Google Sheet export and CF7 import.
* Capacity-aware session picker and pricing-rules engine.

= 0.1.0 =
* Initial build.
