=== Panelr for WooCommerce ===
Contributors: panelr
Tags: iptv, woocommerce, panelr, subscription, streaming
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.0.7
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your Panelr installation to WooCommerce: plans from every service, member accounts, renewals, trials, credits, coupons, support and apps.

== Description ==

Panelr for WooCommerce connects your [Panelr](https://panelr.app) installation to your WooCommerce store.

**This plugin requires a Panelr installation.** Panelr is a paid platform for subscription management. Learn more at [panelr.app](https://panelr.app).

= Features =

* **Several services** — every plan on every service becomes a product, in a category per service; customers can add another service from a product page
* **Member area** — sign in with email and password, see every connection, rename it, renew it, choose channel packages, reveal connection details, see orders, use credits, open support tickets, download apps
* **Orders** — created in Panelr the moment WooCommerce creates them; automatic methods provision at once, manual methods show Panelr's instructions and an "I've paid" form; held and retried when Panelr is unreachable
* **Credits and invite codes** — invite links, sign-up credits, "Pay with credits"
* **Coupons** — WooCommerce's or Panelr's, never both
* **Free trials** — per service, screened by Panelr, with Cloudflare Turnstile
* **Themes** — optional light and dark looks, or your own styles on stable class names; every block is a template you can override

= Third Party Services =

This plugin talks only to the Panelr installation you configure. When Cloudflare Turnstile keys are set, trial requests are also checked with Cloudflare (https://challenges.cloudflare.com), subject to Cloudflare's terms and privacy policy.

= Shortcodes =

* `[panelr_portal]` — member area
* `[panelr_trial]` — free trial form
* `[panelr_upgrade]` — trial upgrade
* `[panelr_order_status]` — order status, payment confirmation, bot hand-off
* `[panelr_support]` — support tickets
* `[panelr_apps]` — app downloads

= Bundled Libraries =

* [QRCode.js](https://github.com/davidshimjs/qrcodejs) by davidshimjs — MIT License

== Installation ==

1. Upload the `panelr-for-woocommerce` folder to `/wp-content/plugins/` and activate it
2. Go to **Panelr → Connection**, enter your Panelr address and API key, press **Test connection**
3. **Panelr → Services & Products**: press **Sync from Panelr**
4. **Panelr → Pages**: press **Create the missing pages**
5. **Panelr → Payments**: map your WooCommerce payment methods to Panelr's

== Frequently Asked Questions ==

= Does this plugin work without Panelr? =

No. It is an interface for the Panelr platform.

= Where do I find my API key? =

In your Panelr admin under Settings → API.

= What payment methods are supported? =

Any WooCommerce payment method can be mapped to a Panelr one. Automatic methods provision as soon as payment is confirmed; manual methods show Panelr's payment instructions and wait for the customer's confirmation.

= Is customer data stored in WordPress? =

Account details are held in the WooCommerce session while a member is signed in. Connection passwords are fetched only when a member asks to see them and are never stored. WooCommerce orders keep a reference to the matching Panelr order.

= I am upgrading from 1.x. What changes? =

Everything keeps working. Members now sign in with their email and password (connection details still work as a door and offer account set-up); manual orders reach Panelr before the thank-you page; payment instructions come from Panelr. Full list in README.md.

== Changelog ==

= 2.0.7 =
* Bundle discounts and service-limited codes from Panelr 2.0.9 show and charge correctly: the store asks Panelr what the cart costs and adds one line per bundle, then the code, then the payment method's fee, so the customer pays exactly what Panelr records.
* The store's own product prices are what Panelr's rules apply to, so a price you edited in WooCommerce stays the price.
* Orders handed over from Panelr show their bundle lines too. Bundle rows appear on the order status page and in the member area's orders.
* If Panelr cannot be reached to price the cart, checkout waits with a notice instead of charging the wrong amount. On a Panelr older than 2.0.9 the store prices carts the old way and says so under Panelr settings.
* Channel groups from Panelr show in the member area's Channels panel as one choice each, with the group's icon, name and package count, under a Groups heading. Packages in a group are not listed on their own; the rest sit under Individual. Ticking a group gives the customer everything in it. Both headings can be renamed under Wording.

= 2.0.6 =
* New Wording tab: type your own word for connection, service, plan, host, M3U link, EPG link and the rest, and it is used everywhere the store shows it: the shop, the cart, order emails, receipts and the member area. A "Use neutral wording" button fills them all in one click. Leave a box empty to keep the plugin's word.
* Switches to leave the order type, the connection name or the service off order lines, and the connection count off plans.
* "Copy each plan's description from Panelr" can be switched off so plan descriptions stay the store's own.

= 2.0.5 =
* The Services and Products tables stay inside their box on narrower screens.

= 2.0.4 =
* An order handed over from Panelr's checkout is paid as it was priced there: its lines cannot be removed, resized or added to in the store's cart.
* Orders paid by an offline method (bank transfer, cheque, cash on delivery) reach Panelr when they are marked paid in WooCommerce, with no need to press Send.

= 2.0.3 =
* Each service can be sold as one product with its plans as options (a Plan dropdown), or as a product for each plan as before. Choose under Panelr → Services, "Sold as". Switching refiles the plans on the spot; the products you had are kept as drafts and come back if you switch back.
* The name a plan is shown under on this store is edited in the Products table and saved as you type. It overrides Panelr's name in the shop, the cart, order emails and the member area, and no sync changes it. Clear it to go back to Panelr's name.
* A service's setup steps in the member area are shown exactly as written in Panelr, line breaks kept, instead of being renumbered as a list.
* Renaming a service under "Shown on this store as" no longer creates a second product category for it.

= 2.0.2 =
* Connection details in the member area follow what Panelr says about each service: an app sign-in shows a server address, username and password with no playlist links; Xtream lines keep their links; the service's own steps and apps are shown underneath. Older Panelr versions still show the old rows.

= 2.0.1 =
* Credits work with the block checkout. They only worked with the classic checkout before.
* Orders check their status with Panelr using the account email instead of the WooCommerce billing email.
* A renewal shows on the account page right away instead of up to five minutes later.
* An API address ending in a folder called `api` is no longer trimmed.
* Orders are filed under the Panelr account signed in at checkout, never under WooCommerce's prefilled billing email.
* An invite code can be typed when creating an account (optional unless the store requires one), not only carried from a link.

= 2.0.0 =
* Rebuilt for Panelr 2.0: several services, member accounts, credits and invite codes, coupons, support tickets, apps, per-service trials, channel packages, connection labels, bot hand-off confirm page, order polling, held-order retries, templates, top-level settings menu. See CHANGELOG.md.

= 1.0.1 =
* Replaced inline scripts with enqueued files; `Requires Plugins: woocommerce`.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Members sign in with email and password now; anyone signed in with connection details will sign in again. Old manual orders that never reached Panelr are listed under Panelr → Orders with a Send button.
