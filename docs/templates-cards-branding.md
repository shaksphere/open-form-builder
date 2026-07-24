# Starter templates, image cards & branding

## Starter templates

"Form Builder → Add new form" opens a template picker instead of a blank canvas.
Each template is a complete, working form (fields, pricing, emails and a matching
brand colour) — pick the closest match, then edit anything.

| Template | Pricing model | Payment | Notable fields |
|---|---|---|---|
| Contact Form | off | off | name, email, phone, message |
| Lesson & Session Booking | sessions | on | session picker, sample teacher slots |
| Course & Certification Enrolment | options | on | course checkboxes as **image cards** |
| Service Booking (Home Services) | options | on | service radio cards + add-ons + quantity |
| Quote / Estimate Calculator | options | **off** | live total, emailed as a quote via `{amount}` |

Picking a template only fills the editor's local state — nothing is written
until you click **Save form**.

## Image-card choice fields

Any **radio** or **checkbox** field has a "Show as image cards" toggle (Build
tab → select the field). When on:

- Each option gets an optional image — click the thumbnail to open the WP media
  library. No image yet? The card shows a letter-monogram placeholder instead of
  a broken image, so it still looks intentional.
- If **Pricing → model = "By selected options"**, each card also shows its price.
- Selection state (border + checkmark) is pure CSS (`:has()`), so no extra JS ships.

Best for courses, services, or any small set of products where a photo helps
someone choose.

## Branding

Each form has its own colours, on the **Settings** tab:

- **Primary / accent colour** — buttons, active step, selected cards/chips, focus rings.
- **Heading & label text colour**.
- **Form background**.
- **Corner roundness** — applied to the form's outer card, inputs, buttons and chips.

These are written into the page as CSS custom properties on the form's wrapper
(`--ofb-accent`, `--ofb-text`, `--ofb-surface`, `--ofb-radius`), so a form's look
never depends on the active theme, and multiple differently-branded forms can sit
on the same site without colliding. A live preview panel next to the colour
pickers shows the effect before saving.
