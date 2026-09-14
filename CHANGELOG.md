# Changelog

## 2.0.3

No database change.

- **Sold as.** Under Panelr → Services each service has a "Sold as" choice: "A product for each plan" (the original way, still the default) or "One product, plans as options". The second gives the service a single variable product with a `Plan` attribute, one variation per Panelr plan, carrying the same `_panelr_*` meta as a plan product does; carts, checkout, renewals, credits, the plans grid and the member area resolve a variation the same way they resolve a product. Saving the choice runs a sync: plans sold the other way are parked (their `_panelr_product_id` moves to `_panelr_product_id_was`, status draft / disabled, never deleted) and brought back if the service is switched back. Trial plans stay private products of their own.
- **Store name per plan.** The Products table's Plan column is an input, saved as you type (`panelr_rename_product`). It is kept in `_panelr_store_name`, sets the product name (or the option label), is never touched by a sync whatever the "Replace my own edits" switch says, follows the plan across a "Sold as" switch, and is what `Panelr_Helpers::plan_name()` answers for a line's plan in the member area. Empty means Panelr's name.
- Options that are switched off are left out of the Plan dropdown instead of showing as unavailable. Two plans with the same name get told apart by their connections and term.
- The member area shows a service's setup steps as written in Panelr (line breaks kept, never renumbered).
- Service categories are looked up through `meta_query`; the old `meta_key`/`meta_value` term query matched nothing, so a renamed service got a second category on the next sync.

## 2.0.2

Follows Panelr's connection details change of Sep 11 2026 (`get_lines` and `verify_login` now carry a `connection` object). No database change.

- The member area's connection details are rendered from Panelr's `connection` object when it is there: the kind of sign-in decides the fields and their labels, playlist links appear only for services that have them, and the service's setup steps and suggested apps follow underneath. Without the object (an older Panelr) the old host / username / password / MAC / M3U / EPG rows are shown as before.

## 2.0.1

Follows the Panelr API changes of Sep 6 2026 (see Panelr's API handoff, section 6). No database change.

- Credits work with the block checkout. They only worked with the classic checkout before, so on the block checkout the credits option did not appear and a credits-only cart had no way to pay.
- Orders check their status with Panelr using the account email instead of the WooCommerce billing email. When the two were different, orders never reached Completed, connection details did not appear on the thank-you page, and balance payments were not recorded.
- A renewal shows on the account page right away instead of up to five minutes later.
- An API address ending in a folder called `api` is no longer trimmed.
- Orders are filed under the Panelr account signed in at checkout (`_panelr_customer_email`), never under WooCommerce's prefilled billing email. A WordPress profile holding someone else's billing details no longer sends the lines to the wrong account.
- Registration: an email that already has an account is asked to sign in; one on file without a password is sent a link to choose one (`register_customer` now answers 409 in both cases).
- Connection rename sends the signed-in customer with the request, which `update_customer` now requires.
- "Resend confirmation" calls `update_customer_account` with `resend_verification` instead of re-registering.
- Both sign-in calls pass the visitor's address so Panelr throttles the guesser, not every visitor of the store; a 429 shows "Too many tries".
- New `processing` order status ("Being set up") while Panelr provisions.
- Welcome-credit copy: credits arrive once the email is confirmed.

## 2.0.0

Rebuilt for Panelr 2.0. Everything a store on 1.0.1 relied on keeps working; see "What changed from version 1" in README.md for the six places behaviour differs.

### Added
- Several services per store: plans synced per service with a product category each, service names on products, cart lines and orders, "Add another service" on product pages.
- Member accounts: sign in with email and password, create account, forgot / reset password, confirm email, name and email changes, password change, contact preference, sign out everywhere.
- Connections tab: every line grouped by service, rename in place, days left, trial badge, Renew (plans on the line's own service), Channels (checkboxes for editor and panel services), Details fetched on demand with reveal and copy.
- Orders tab from Panelr, Credits tab (balance, invite link with share buttons, invited people, history), Support tab and `[panelr_support]` (tickets: open, reply, close, reopen), Apps tab and `[panelr_apps]` (sections per device, downloader codes).
- Invite codes: `?ref=CODE` kept for 30 days, attached to sign-up and orders; "Invited by a member" on the cart; sign-up credits shown on the form.
- Credits: "Pay with N credits" on product pages and in renewals; a "Pay with credits" payment method for all-credits carts; mixed carts send per-item credits.
- Coupons: choose WooCommerce or Panelr as the source of discount codes; Panelr codes are checked in the cart and sent with the order.
- Order creation when WooCommerce creates the order; held orders retried every 5 minutes; polling of every sent order; Completed on provisioning; Check now on the order screen; Panelr → Orders page with "Never sent" and Send.
- Trials per service with a service picker, Cloudflare Turnstile, Panelr's screening messages, approved and pending outcomes.
- Bot hand-off confirm page; trial-upgrade page accepts `?t=` tokens and never auto-submits.
- Templates under `templates/panelr/` with theme overrides; top-level Panelr menu with tabs; API key never echoed; `wp-config.php` constants; caching, rate-limit awareness and logging in the API client; upgrade runner with `panelr_db_version`.

### Changed
- Member sign-in is the account, not a line (line credentials still work as a door).
- Manual orders reach Panelr before the thank-you page loads.
- Payment instructions come from Panelr; deep links are no longer built by the plugin.
- Passwords are passed through unchanged; visitor addresses use REMOTE_ADDR unless "behind a proxy" is on.

### Removed
- `panelr_payment_mode_map`, the "Trial product" setting, `create_activation` / `create_renewal` wrappers, cleartext credentials in the session.

## 1.0.1
- Replaced inline scripts with enqueued files; `Requires Plugins: woocommerce`.

## 1.0.0
- Initial release.
