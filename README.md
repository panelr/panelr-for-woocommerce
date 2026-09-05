# Panelr for WooCommerce

Connect your [Panelr](https://panelr.app) installation to WooCommerce. Version 2 covers everything Panelr does: several services per store, member accounts, invite codes and credits, coupons, support tickets, the apps page, per-service trials, channel packages, connection labels, and the Telegram / Discord bots that hand customers to the store.

---

## Requirements

- WordPress 6.0+
- WooCommerce 7.2+ (Action Scheduler ships with it)
- PHP 8.0+
- A Panelr 2.0 installation with API access

---

## Setup

1. Upload the `panelr-for-woocommerce` folder to `/wp-content/plugins/` and activate it.
2. **Panelr → Connection**: enter your Panelr address and API key. Press **Test connection**.
3. **Panelr → Services & Products**: press **Sync from Panelr**. Every plan on every service becomes a WooCommerce product. Trial plans are kept private.
4. **Panelr → Pages**: press **Create the missing pages**.
5. **Panelr → Payments**: map each WooCommerce payment method to a Panelr one. Automatic methods (cards, PayPal, crypto gateways) provision at once; manual methods (Venmo, Zelle, Cash App, bank transfer) show payment instructions and wait for the customer's confirmation.
6. **Panelr → Trials**, **Member Area**, **Support**, **Bots**: switch on what you use.

The API key can also live in `wp-config.php`:

```php
define('PANELR_API_URL', 'https://your-panelr.example');
define('PANELR_API_KEY', 'your-key');
```

When defined there, the settings page shows the fields as read-only.

---

## Settings

| Tab | What it holds |
|-----|---------------|
| Connection | Panelr address, API key, Test connection, Refresh from Panelr, last error |
| Services & Products | The services Panelr offers, the synced plans, Sync, "Sync overwrites my edits", per-service product categories |
| Pages | Which page holds each shortcode, Create the missing pages |
| Payments | WooCommerce → Panelr payment method map, where discount codes come from (WooCommerce or Panelr), auto-complete orders |
| Trials | Show the trial form, Cloudflare Turnstile keys |
| Member Area | Look (theme), channel packages, sign customers in to WordPress too, session length, invite code on sign-up |
| Support | Support pages on Panelr or on this site |
| Apps | Where the apps page is offered (from Panelr) and which page holds it |
| Bots | Telegram bot link, Discord invite, footer buttons |
| Advanced | Behind a proxy or Cloudflare (and which header), draft plans on uninstall, log |

---

## Shortcodes

| Shortcode | Page |
|-----------|------|
| `[panelr_portal]` | Member area: sign in, connections, orders, credits, support, apps, account |
| `[panelr_trial]` | Free trial form (service picker when more than one service offers trials) |
| `[panelr_upgrade]` | Trial upgrade: `?panelr_t=<trial code>` or `?t=<token>` prefill the code |
| `[panelr_order_status]` | Order status by reference and email, Panelr's emailed links, and the bot hand-off confirm page |
| `[panelr_support]` | Support tickets (when "Support pages: This site") |
| `[panelr_apps]` | App downloads |
| `[panelr_checkout]` | Receives an order started on Panelr's own checkout and takes payment for it |
| `[panelr_plans service="Demo Service"]` | That service's plans as a pricing grid with Add-to-cart buttons; attributes `service` (name or id), `columns` (1–4), `heading` (yes/no), `ids` (Panelr plan ids). Without `service`, every service, grouped |
| `[panelr_services]` | The services as a list linking to each one's product category |

No attributes are needed.

---

## How orders flow

**When WooCommerce creates the order**, the mapped Panelr method's mode decides:

- **Manual** → the Panelr order is created right then, the customer gets Panelr's payment instructions by email, and the order-received page shows the reference, the instructions, the amount, and an "I've paid" form.
- **Automatic** → when WooCommerce confirms payment, the order is completed in Panelr and provisioned. The order-received page shows "Being set up" and updates to "Ready" on its own.
- **Credits** → a member whose credits cover the whole cart sees one payment method, "Pay with credits"; the order completes in Panelr with no money.

If Panelr cannot be reached at that moment the order is **held** and retried every 5 minutes for an hour; it never turns a card order into a manual one. Panelr never calls the site: the plugin checks each order at +1, +2, +5, +15 and +60 minutes until it is ready, then marks the WooCommerce order Completed (setting).

**Panelr → Orders** lists every order with Panelr plans, its Panelr status, anything never sent (with a Send button), and Check now.

---

## Orders that start on Panelr and are paid here

The plugin also works alongside Panelr's own storefront. In Panelr → Settings → Website, switch E-Commerce on and choose **Checkout only** (Full replacement is the mode where the store takes over every customer page); put the address of the store page holding `[panelr_checkout]` into Checkout. Then in Panelr → Settings → Payments, set a payment method's Checkout to "On the connected store". A customer who builds an order on Panelr and picks that method is sent to the store with the order's reference and token. The plugin rebuilds the cart from the order (same plan ids, Panelr's prices, its coupon and fee), fills in their details, offers only the WooCommerce methods mapped to the method they chose, and on payment marks the existing Panelr order paid. No sign-in is involved; the token opens that one order. Names and descriptions shown on the store are the store's own.

---

## Rules the plugin mirrors

- Every service is sold and renewed on its own. A renewal needs a plan on the connection's own service with at least as many connections; a trial upgrades to any plan on its service.
- Trials are per service, screened by Panelr (daily cap, one per email / network, disposable addresses, VPNs). Panelr's message is shown as it comes.
- One source of discount per order: WooCommerce coupons or Panelr coupons, never both.
- Credits belong to an email login. Plain renewals never earn credits. All-credits orders need no payment method.
- Dates from Panelr are UTC and shown in the site's timezone.

---

## Theming

Choose **Panelr light** or **Panelr dark** under Member Area → Look, or keep your theme's styles and target the class names below. Every block renders through `wc_get_template()`; copy any file from `templates/panelr/` to `your-theme/woocommerce/panelr/` to change the markup.

**Member area**
```
.panelr-portal                  Outer wrapper (adds --member, --signed-out, --line-only)
.panelr-portal__header          Name + Sign out
.panelr-portal__tabs            Tab links (.panelr-tab-btn, --active)
.panelr-portal__section         Card
.panelr-portal__table           Data table
.panelr-portal__code            Monospace value
.panelr-portal__status          Badge (--active, --trial_active, --expired, --suspended, --canceled,
                                --pending_payment, --completed, --open, --closed …)
.panelr-portal__login           Sign-in card (.panelr-portal__view per form)
.panelr-portal__field           Label + input
.panelr-portal__error           Error message
.panelr-portal__actions         Button row
.panelr-line                    One connection (.panelr-line__head, __title, __actions, __panel,
                                __details, __channels, __renew)
.panelr-portal__bouquet-list    Channel list
.panelr-wizard-step             Channel group (editor services)
.panelr-order                   One order in the Orders tab
.panelr-credits                 Credits tab blocks
.panelr-ticket                  One support ticket
```

**Trial / upgrade**
```
.panelr-trial  .panelr-trial__field  .panelr-trial__error  .panelr-trial__services
.panelr-upgrade  .panelr-upgrade__account  .panelr-upgrade__products  .panelr-upgrade__table
```

**Order received / order status**
```
.panelr-thankyou  .panelr-reference-box  .panelr-reference-code  .panelr-payment-instructions
.panelr-copy-list  .panelr-copy-item  .panelr-amount-due  .panelr-payment-form
.panelr-payment-success  .panelr-order-status  .panelr-handoff
```

**Product page / cart**
```
.panelr-product-service  .panelr-addons  .panelr-credits-buy  .panelr-coupon-row  .panelr-invited-row
```

**Apps**
```
.panelr-apps  .panelr-apps__section  .panelr-app  .panelr-app__logo  .panelr-app__download
```

CSS variables when a Panelr look is on: `--panelr-accent`, `--panelr-accent-hover`, `--panelr-radius`, and the rest listed at the top of `assets/css/theme-light.css`.

---

## Inbound links Panelr sends

| Link | Lands on |
|------|----------|
| `?panelr_ref=…&panelr_token=…` | Order status page: the order, instructions, "I've paid" |
| `?panelr_t=<trial code>` / `?t=<token>` | Trial upgrade page with the code filled in (press Continue) |
| `?panelr_product_id=…&panelr_intent=…&panelr_email=…&panelr_first_name=…&panelr_last_name=…` | Order status page: a confirm page with the plan and details, then Continue to checkout |
| `?ref=<invite code>` on any page | The code is kept for 30 days and attached to the account and orders |

---

## What changed from version 1

Backward compatibility was the rule: a store on 1.0.1 keeps working the moment 2.0 is activated. All options, product and order meta, shortcodes, pages, inbound links and CSS class names are kept. An upgrade routine runs once and:

1. reads the old options as they are;
2. removes the retired `panelr_payment_mode_map`;
3. writes the service and plan details onto every synced product from one Panelr call; plans Panelr no longer offers are set to draft (never deleted) with a notice;
4. creates nothing new — the support and apps pages are offered from Pages with one button;
5. records `panelr_db_version`.

Where compatibility could not hold:

1. **Portal sign-in changes from line credentials to the customer account.** v1 signed a customer in with a line's IPTV username and password. v2's member area is the customer account, because credits, invite codes, several lines, labels, orders and support all belong to the account. What holds: a customer who only has line credentials can still sign in with them; when they have no login yet the plugin offers "Set up your account". What changes: a WooCommerce session from v1 is not carried over; anyone signed in when the plugin updates signs in again.
2. **The manual-order flow moves off the thank-you page.** v1 created the Panelr work order when the customer reached the thank-you page. v2 creates it when WooCommerce creates the order. Old orders created under v1 that never reached Panelr cannot be repaired by the upgrade: the plugin lists them under Panelr → Orders as "Never sent to Panelr" with a Send button.
3. **Payment-method deep links and QR codes.** v1 built Venmo / Cash App / PayPal.me links itself. v2 shows Panelr's own instructions and the method's details, in the site's currency, and keeps the QR only for methods with a payable address. Class names are kept; the deep links may differ.
4. **The "Trial product" setting no longer decides anything.** Trials are per service in Panelr; the trial plan is Panelr's to hide from sale. v2 reads the old setting only during the upgrade to un-hide a product v1 made private, then ignores it, and the setting disappears from the page.
5. **The "Balance due" hidden product stays** for partial payments; nothing changes.
6. **Bot checkout links**: the same URL keeps working, but it no longer acts on a bare visit. The link lands on a page that shows the plan, the name and email it carries, and a Continue to checkout button; that button does what v1 did on arrival.

---

## Changelog

See `CHANGELOG.md`.
