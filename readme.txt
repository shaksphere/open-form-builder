=== Open Form Builder ===
Contributors: openformbuilder
Tags: forms, form builder, multi-step, stripe, conditional logic
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A CF7-style form builder with multi-step wizards, conditional logic, a
capacity-aware session picker, a pricing-rules engine, Stripe Checkout,
conditional emails, Google Sheet export and CF7 import. Forms are declarative
JSON schemas.

== Description ==

Open Form Builder stores each form as a clean, declarative JSON schema rendered
to front-end HTML by a render engine. Drop a form anywhere with the shortcode:

`[open_form id="123"]`

Features:

* React admin builder using @wordpress/components (matches Gutenberg).
* Multi-step (wizard) forms with drag-and-drop fields.
* Field types: text, email, tel, number, textarea, select, dropdown, radio,
  checkbox, HTML content block, and a capacity-aware session picker.
* Per-field conditional show/hide.
* Personalisation tags ({field_name}) in confirmation content and emails.
* Pricing-rules engine (base price + sessions, per-block discounts in $ or %).
* Stripe Checkout (dynamic sessions); slots deplete on payment success only
  (first-pay-wins).
* Confirmation + receipt emails via wp_mail with CF7-style conditional routing.
* Google Sheet export via a Google Apps Script web app (no OAuth).
* Import CF7 form fields + mail templates.

== Installation ==

1. Upload the `open-form-builder` folder to `/wp-content/plugins/`.
2. Activate it. Custom tables are created automatically.
3. Set Stripe keys under Form Builder → Settings (see docs/stripe-setup.md).
4. Build a form, copy its shortcode, and drop it on any page or post.

== Changelog ==

= 0.2.1 =
* Stripe Checkout, conditional emails, Google Sheet export and CF7 import.
* Capacity-aware session picker and pricing-rules engine.

= 0.1.0 =
* Initial build.
