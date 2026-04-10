# Panelr for WooCommerce

Connect your [Panelr](https://panelr.app) IPTV management platform to WooCommerce. This plugin provides a complete customer-facing storefront for your IPTV service — handling new activations, renewals, free trials, trial upgrades, and customer self-service.

---

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 8.0+
- An active Panelr installation with API access

---

## Installation

1. Upload the `panelr-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Settings → Panelr** and enter your API URL and API key
4. Click **Test Connection** to verify
5. Click **Sync Products** to import your Panelr products into WooCommerce
6. Click **Create Pages Automatically** to create the required pages
7. Map your WooCommerce payment gateways to Panelr payment methods

---

## Settings

All settings are found under **Settings → Panelr** in the WordPress admin.

### Connection
| Setting | Description |
|---------|-------------|
| API URL | Base URL of your Panelr installation. No trailing slash. |
| API Key | Found in your Panelr admin under Settings → API. |

### Products
Syncs Panelr products to WooCommerce as simple virtual products. Run sync any time products change in Panelr. Existing products are matched by Panelr ID and updated in place.

### Pages
Assign WordPress pages to each shortcode. Use **Create Pages Automatically** to generate all three pages with the correct shortcodes pre-inserted.

| Page | Shortcode | Purpose |
|------|-----------|---------|
| Customer Portal | `[panelr_portal]` | Customer login, credentials, channels, renewals |
| Free Trial | `[panelr_trial]` | Free trial request form |
| Trial Upgrade | `[panelr_upgrade]` | Trial-to-paid upgrade page |
| Order Status | `[panelr_order_status]` | Order status and payment submission page |

### Payment Methods
Maps each WooCommerce payment gateway to a Panelr payment method. This determines how orders are processed — automatic gateways (Stripe, PayPal) trigger immediate provisioning; manual gateways (Venmo, Zelle, Cash App) create a pending work order and display payment instructions on the order confirmation page.

### Free Trials
| Setting | Description |
|---------|-------------|
| Enable Free Trials | Show/hide the trial request form. When disabled, `[panelr_trial]` displays a "not available" message. |
| Trial Product | The WooCommerce product used for free trial activations. This product is hidden from the shop. |

### Customer Portal
| Setting | Description |
|---------|-------------|
| Frontend Theme | Apply a pre-built stylesheet. Options: None, Panelr Light, Panelr Dark. |
| Channel Management | Allow customers to manage their bouquet/channel selection from the portal. |

---

## Shortcodes

### `[panelr_portal]`
The main customer self-service portal. Customers log in with their IPTV credentials (xtream or editor username/password).

**Features:**
- View account status, expiration date, name, email
- Edit name and email
- View connection details: host URL, username, password (toggle), M3U URL, EPG URL
- Manage channel selections (if enabled in settings)
- Renew service — adds product to cart with renewal intent
- Sign out

**Note:** Editor credentials are always shown when configured. Xtream credentials are never exposed if editor credentials exist.

---

### `[panelr_trial]`
Free trial request form. Customers enter their name and email. The plugin collects their IP address and user agent server-side for Panelr's anti-abuse checks.

**Responses:**
- **Approved** — Trial is active immediately. Customer sees confirmation message.
- **Pending** — Trial requires manual approval in Panelr admin. Customer sees pending message.

Requires **Enable Free Trials** to be on in settings.

---

### `[panelr_upgrade]`
Trial-to-paid upgrade page. Accepts a trial code via form entry or `?panelr_t=TRIALCODE` URL parameter (auto-submits).

**Flow:**
1. Customer enters or arrives with trial code
2. Portal verifies the code and displays their account info
3. Customer selects a plan and is sent to checkout
4. Order is processed with `trial_upgrade` intent

---

## Order Flow

### Automatic Payment Gateways (Stripe, PayPal, etc.)
1. Customer places order
2. Payment is collected by WooCommerce
3. On payment confirmation, plugin calls Panelr `complete_order`
4. Panelr provisions the activation/renewal asynchronously via webhook

### Manual Payment Gateways (Venmo, Zelle, Cash App, etc.)
1. Customer places order
2. Plugin calls Panelr `create_work_order` to create a pending work order
3. Plugin calls Panelr `send_payment_instructions` to email the customer their payment details
4. Order confirmation page shows payment instructions, reference code, QR code (if applicable), and a transaction ID submission form
5. Customer sends payment and submits their transaction ID
6. Admin receives email notification
7. Admin verifies and activates the order in Panelr

---

## Order Types

The plugin supports three order intents, set automatically based on context:

| Intent | Source | Description |
|--------|--------|-------------|
| `new_activation` | Shop checkout | Standard new subscription |
| `renewal` | Portal → Renew tab | Renews an existing activation |
| `trial_upgrade` | Upgrade page | Converts trial to paid |

Order type and account username are shown in the WooCommerce order summary and order received page.

---

## Theming

### Using a Pre-built Theme
Select **Panelr Light** or **Panelr Dark** under Settings → Panelr → Customer Portal.

### Custom Styling
Set theme to **None** and target the following CSS classes in your theme's stylesheet:

**Portal**
```
.panelr-portal                  Outer wrapper
.panelr-portal__section         Card sections
.panelr-portal__table           Data tables
.panelr-portal__code            Monospace credential display
.panelr-portal__tabs            Tab navigation
.panelr-portal__status          Status badge
.panelr-portal__status--active
.panelr-portal__status--trial_active
.panelr-portal__status--expired
.panelr-portal__status--suspended
.panelr-portal__status--canceled
.panelr-portal__login           Login form wrapper
.panelr-portal__actions         Edit/Sign Out button row
.panelr-portal__bouquet-list    Channel list
.panelr-wizard-step             Bouquet wizard step
.panelr-wizard-review           Bouquet review step
```

**Trial**
```
.panelr-trial                   Outer wrapper
.panelr-trial__field            Form field wrapper
.panelr-trial__error            Error message
```

**Upgrade**
```
.panelr-upgrade                 Outer wrapper
.panelr-upgrade__account        Account info section
.panelr-upgrade__products       Plan selection section
.panelr-upgrade__table          Plans table
```

**Thank You Page**
```
.panelr-thankyou                Outer wrapper
.panelr-reference-box           Order reference display
.panelr-reference-code          Reference code (monospace)
.panelr-payment-instructions    Payment instructions block
.panelr-instructions-list       Instruction bullet list
.panelr-copy-list               Copyable fields list
.panelr-copy-item               Single copyable row
.panelr-amount-due              Amount due display
.panelr-payment-form            Transaction ID form
.panelr-payment-success         Success confirmation
```

### CSS Custom Properties (when using a pre-built theme)
Override variables in your theme's CSS after the plugin stylesheet loads:

```css
:root {
    --panelr-accent:        #your-brand-color;
    --panelr-accent-hover:  #your-brand-color-dark;
    --panelr-radius:        4px;  /* sharper corners */
}
```

---

## Frequently Asked Questions

**Why aren't my payment gateways showing in the mapping?**
Ensure your WooCommerce payment gateways are installed and enabled. Load the mapping by clicking **Load / Refresh Payment Methods** on the settings page.

**Why is the trial product showing in my shop?**
Set the trial product under Settings → Panelr → Free Trials → Trial Product and save. The plugin will automatically hide it from shop archives.

**Customers are seeing "all channels selected" when some are deselected — why?**
If a customer has no entries in `activation_bouquets`, Panelr treats this as all channels active. This is correct behaviour for editor-managed lines.

**How do I link customers to the upgrade page with their trial code pre-filled?**
Include the trial code as a URL parameter: `https://yoursite.com/upgrade-trial/?t=TRIALCODE`

**What happens if a manual payment order is never paid?**
The work order remains in `pending_payment` status in Panelr. You can cancel or delete it from the Panelr admin.

---

## Changelog

### 1.0.0
- Initial release
- New activation, renewal, trial upgrade order flows
- Customer portal with credentials, channel management, renewal
- Free trial request shortcode
- Trial upgrade shortcode
- Manual and automatic payment gateway support
- Panelr Light and Dark themes