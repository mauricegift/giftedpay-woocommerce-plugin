# GiftedPay for WooCommerce

> Accept M-Pesa STK Push payments on your WooCommerce store — one API key, no complex setup.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588a)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://php.net)
[![GiftedPay](https://img.shields.io/badge/Powered%20by-GiftedPay-00a651)](https://pay.gifted.co.ke)

---

## What It Does

When a customer checks out, they enter their Safaricom number. An **M-Pesa STK Push** is sent to their phone instantly. They enter their PIN, and the order is marked as paid — automatically, in real time, with no page refresh.

```
Customer enters number → STK Push sent → Customer enters PIN → Order confirmed ✓
```

### Features

- **One credential** — only your GiftedPay API key is needed
- **Real-time status** — thank-you page polls every 4 seconds and updates live
- **Callback webhook** — GiftedPay also notifies your store when payment settles
- **Phone validation** — accepts `07xx`, `01xx`, or `254xxx` formats, validates live at checkout
- **Receipt links** — printable M-Pesa receipt linked on thank-you page and in order emails
- **Connection test** — admin settings page confirms your API key works after saving
- **HPOS compatible** — supports WooCommerce High Performance Order Storage
- **Dual confirmation** — both polling and webhook update the order, whichever arrives first

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.8+ |
| WooCommerce | 6.0+ |
| PHP | 7.4+ |
| Store currency | **KES (Kenyan Shilling)** |
| GiftedPay account | Free — [sign up here](https://pay.gifted.co.ke) |
| HTTPS | Required (M-Pesa mandates HTTPS callbacks) |

---

## Installation

### Option A — Upload ZIP (recommended)

1. [**Download the latest release ZIP**](https://github.com/mauricegift/giftedpay-woocommerce-plugin/releases/latest/download/giftedpay-woocommerce.zip)
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded ZIP file → click **Install Now**
4. Click **Activate Plugin**

### Option B — Manual (FTP / cPanel)

1. Download and unzip the release
2. Upload the `giftedpay-woocommerce/` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin → Plugins** and activate **GiftedPay for WooCommerce**

### Option C — Clone with Git

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/mauricegift/giftedpay-woocommerce-plugin.git giftedpay-woocommerce
```

Then activate via **Plugins → Installed Plugins**.

---

## Configuration

After activation, go to **WooCommerce → Settings → Payments → GiftedPay M-Pesa**.

### Step 1 — Get your API key

1. Log in to [pay.gifted.co.ke](https://pay.gifted.co.ke)
2. Go to **Dashboard → Apps**
3. Click your app (create one if you haven't yet)
4. Copy the **API Key** — it starts with `gifted_mpesa_stk_`

### Step 2 — Paste it in WordPress

![Settings screenshot placeholder](https://pay.gifted.co.ke/assets/wc-settings-preview.png)

Paste the key into the **GiftedPay API Key** field and click **Save changes**.

A green **"✓ API key is valid"** message appears immediately below the field if the connection works.

### Step 3 — That's it

No callback URL to configure. The plugin automatically sets the callback URL to:

```
https://yourstore.com/?wc-api=giftedpay_callback
```

This is sent with every payment request — GiftedPay will POST payment results back to this URL automatically.

---

## Settings Reference

| Setting | Default | Description |
|---|---|---|
| **Enable / Disable** | ✓ Enabled | Toggle the payment method on/off |
| **GiftedPay API Key** | _(empty)_ | Your only required credential |
| **Payment Method Title** | M-Pesa (via GiftedPay) | Label shown to customers at checkout |
| **Description** | _(preset)_ | Short text under the payment method |
| **Phone Field Label** | M-Pesa Phone Number | Label on the phone input |
| **Phone Field Placeholder** | e.g. 0712 345 678 | Placeholder text |
| **Show Receipt Link** | ✓ Yes | Adds receipt link to thank-you page & emails |
| **Polling Interval** | 4 seconds | How often the thank-you page checks status |
| **Polling Timeout** | 150 seconds | When to stop polling and show timeout message |

---

## How Payments Work (Technical Flow)

```
1. Customer selects M-Pesa at checkout
2. Enters Safaricom number (07xx / 01xx / 254xxx — auto-normalised)
3. Clicks "Place Order"
4. Plugin calls  POST https://mpesa.gifted.co.ke/api/payments/process
                 Authorization: Bearer <api_key>
                 { phone_number, amount, account_reference, transaction_desc, callback_url }
5. GiftedPay triggers Safaricom STK Push → customer receives PIN prompt
6. Plugin redirects to thank-you page
7. Thank-you page polls  POST /payments/verify  every 4s via WordPress AJAX
   (API key stays server-side — never exposed to browser)
8. In parallel, Safaricom posts callback to GiftedPay
   → GiftedPay posts to  /?wc-api=giftedpay_callback
   → Plugin finds order by _giftedpay_checkout_id meta, marks paid
9. Whichever arrives first (poll or callback) calls $order->payment_complete()
```

### Callback Payload

GiftedPay POSTs to your `/?wc-api=giftedpay_callback` endpoint:

```json
{
  "checkout_request_id": "ws_CO_31032026031749...",
  "status": "completed",
  "mpesa_receipt_number": "UCV6EAV0NH",
  "amount": 1500,
  "phone_number": "254712345678",
  "result_desc": "The service request is processed successfully."
}
```

The plugin also handles the raw Safaricom STK callback format as a fallback.

### Phone Number Normalisation

All of these are accepted and converted to `254XXXXXXXXX` automatically:

| Input | Stored as |
|---|---|
| `0712 345 678` | `254712345678` |
| `0712345678` | `254712345678` |
| `254712345678` | `254712345678` |
| `+254712345678` | `254712345678` |

---

## Order Meta

The plugin stores the following metadata on each order:

| Meta Key | Value |
|---|---|
| `_giftedpay_checkout_id` | M-Pesa checkout request ID (e.g. `ws_CO_...`) |
| `_giftedpay_phone` | Normalised phone number (`254...`) |
| `_giftedpay_amount` | Amount charged in KES |

---

## Receipt Link

After a successful payment, customers see a **View M-Pesa Receipt** link that opens:

```
https://mpesa.gifted.co.ke/api/payments/receipt/<checkout_request_id>
```

This page shows a printable receipt and includes a **Save as PDF** button. The same link appears in WooCommerce order confirmation emails.

---

## Sandbox vs Production

| Feature | Sandbox | Production |
|---|---|---|
| Cost | Free | KES 350/month or KES 3,000 once |
| STK Push | ✓ (owner's number only) | ✓ (any Safaricom number) |
| Real M-Pesa charge | ✓ Yes | ✓ Yes |
| Transaction history | ✓ | ✓ |

To go live, upgrade your GiftedPay account from the dashboard. No code changes needed.

---

## Troubleshooting

### "STK push not received"
- Confirm the phone number is a valid **Safaricom** number (Airtel/Faiba not supported for STK)
- Check your GiftedPay app is not disabled
- In sandbox mode, only the account owner's number works

### "Payment confirmed but order still shows Pending"
- Check WordPress error logs for callback delivery failures
- Ensure `/?wc-api=giftedpay_callback` is publicly accessible (not behind a login or firewall)
- The polling fallback should catch this within ~8 seconds anyway

### "API key is invalid" in settings
- Re-copy the key from **GiftedPay Dashboard → Apps → [your app]**
- Make sure there are no leading/trailing spaces

### "Could not reach GiftedPay API"
- Your server must be able to make outbound HTTPS requests to `mpesa.gifted.co.ke`
- Check if your host blocks outbound connections (common on some shared hosts)

### Store currency not KES
- Go to **WooCommerce → Settings → General → Currency → Kenyan Shilling (KES)**

---

## File Structure

```
giftedpay-woocommerce/
├── giftedpay-woocommerce.php          ← Main plugin file (entry point)
├── includes/
│   └── class-wc-giftedpay-gateway.php ← Payment gateway class
├── assets/
│   ├── js/
│   │   ├── checkout.js                ← Phone validation at checkout
│   │   └── thankyou.js               ← Real-time polling on thank-you page
│   ├── css/
│   │   └── giftedpay.css             ← Styles for checkout + thank-you page
│   └── images/
│       └── mpesa-badge.svg           ← M-Pesa badge shown at checkout
├── readme.txt                         ← WordPress plugin readme
└── README.md                          ← This file
```

---

## API Reference

This plugin integrates with the [GiftedPay API](https://pay.gifted.co.ke/api-guide). Key endpoints used:

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/payments/process` | Initiate STK Push |
| `POST` | `/payments/verify` | Poll transaction status |
| `GET` | `/payments/receipt/:id` | Printable receipt (no auth) |

Full API docs: **https://pay.gifted.co.ke/api-guide**

---

## Support

- **Documentation:** https://pay.gifted.co.ke/api-guide
- **Email:** payments@gifted.co.ke
- **Website:** https://pay.gifted.co.ke
- **Issues:** [GitHub Issues](https://github.com/mauricegift/giftedpay-woocommerce-plugin/issues)

---

## License

GPL-2.0+ — see [LICENSE](LICENSE) for details.

---

## Changelog

### 1.0.0
- Initial release
- STK Push initiation with `POST /payments/process`
- Real-time polling via WordPress AJAX (API key stays server-side)
- Webhook callback handler (`?wc-api=giftedpay_callback`)
- Live connection test in admin settings
- Receipt link on thank-you page and order emails
- HPOS compatibility declared
- Phone number auto-normalisation (07xx / 01xx / 254xxx)
