=== Panelr for WooCommerce ===
Contributors: panelr
Tags: iptv, woocommerce, panelr, subscription, streaming
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your Panelr IPTV management platform to WooCommerce to sell, manage, and renew IPTV subscriptions.

== Description ==

Panelr for WooCommerce connects your [Panelr](https://panelr.app) IPTV management installation to your WooCommerce store, providing a complete customer-facing storefront for your IPTV service.

**This plugin requires an active Panelr installation.** Panelr is a paid SaaS platform for IPTV service management. Learn more at [panelr.app](https://panelr.app).

= Features =

* **New activations** — Customers purchase subscriptions through WooCommerce checkout
* **Renewals** — Customers renew existing lines from the customer portal
* **Free trials** — Customers request free trials via a shortcode form with built-in anti-abuse protection
* **Trial upgrades** — Trial customers upgrade to paid plans via a dedicated page
* **Customer portal** — Self-service page for credentials, connection details, channel management, and renewals
* **Manual payments** — Full support for Venmo, Zelle, Cash App, and other manual payment methods with payment instructions, QR codes, and transaction ID submission
* **Automatic payments** — Stripe, PayPal, and other automatic gateways trigger immediate provisioning
* **Channel management** — Allow customers to select their active channel groups (bouquets) from the portal
* **Themes** — Optional pre-built Light and Dark themes, fully customizable via CSS variables

= Third Party Services =

This plugin communicates with your Panelr installation via its REST API. All API calls are made to the URL you configure in the plugin settings — this is your own Panelr server, not a shared service.

By using this plugin, you agree to be bound by Panelr's [Terms of Service](https://panelr.app/terms) and [Privacy Policy](https://panelr.app/privacy).

= Shortcodes =

* `[panelr_portal]` — Customer self-service portal
* `[panelr_trial]` — Free trial request form
* `[panelr_upgrade]` — Trial-to-paid upgrade page
* `[panelr_order_status]` — Order status and payment submission page

= Bundled Libraries =

* [QRCode.js](https://github.com/davidshimjs/qrcodejs) by davidshimjs — MIT License

== Installation ==

1. Upload the `panelr-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Settings → Panelr** and enter your Panelr API URL and API key
4. Click **Test Connection** to verify the connection
5. Click **Sync Products** to import your Panelr products into WooCommerce
6. Click **Create Pages Automatically** to create the portal, trial, upgrade, and order status pages
7. Map your WooCommerce payment gateways to Panelr payment methods under **Payment Methods**

== Frequently Asked Questions ==

= Does this plugin work without Panelr? =

No. This plugin is an interface for the Panelr IPTV management platform. An active Panelr installation is required.

= Where do I find my API key? =

In your Panelr admin, go to Settings → API. Copy the API key and paste it into the plugin settings.

= What payment methods are supported? =

Any WooCommerce payment gateway can be mapped to a Panelr payment method. Automatic gateways (Stripe, PayPal) provision immediately after payment. Manual gateways (Venmo, Zelle, Cash App) display payment instructions and require the customer to submit a transaction ID.

= How do I style the portal to match my theme? =

Set **Frontend Theme** to **None** in the plugin settings and add your own CSS targeting the `.panelr-portal`, `.panelr-trial`, `.panelr-upgrade`, and `.panelr-thankyou` classes. Full class reference is in the plugin README.

= Is customer data stored in WordPress? =

No customer IPTV data is stored in WordPress. The plugin retrieves data from your Panelr installation on demand and stores it temporarily in the WooCommerce session. WooCommerce order meta stores order references needed to track manual payment status.

= How do I link customers to the upgrade page with their trial code pre-filled? =

Include the trial code as a URL parameter: `https://yoursite.com/upgrade-trial/?panelr_t=TRIALCODE`

== Screenshots ==

1. Plugin settings page
2. Customer portal — connection details
3. Customer portal — channel management
4. Thank you page with payment instructions
5. Free trial request form

== Changelog ==

= 1.0.0 =
* Initial release
* New activation, renewal, and trial upgrade order flows
* Customer portal with credentials, channel management, and renewals
* Free trial request shortcode with anti-abuse checks
* Trial upgrade shortcode with token-based auto-verification
* Manual and automatic payment gateway support
* Payment instructions with QR code on order confirmation page
* Panelr Light and Dark frontend themes

== Upgrade Notice ==

= 1.0.0 =
Initial release.