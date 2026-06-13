=== GiftedPay for WooCommerce ===
Contributors: giftedpay
Tags: mpesa, m-pesa, kenya, woocommerce, payment gateway, stk push, mobile money
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept M-Pesa STK Push payments on your WooCommerce store via GiftedPay.

== Description ==

**GiftedPay for WooCommerce** lets Kenyan businesses accept M-Pesa payments directly at WooCommerce checkout using the GiftedPay STK Push gateway.

= Features =

* M-Pesa STK Push sent automatically when customer places order
* Real-time payment status polling on the thank-you page (no page refresh needed)
* Automatic order status update on payment confirmation
* Webhook callback support for instant updates
* Downloadable M-Pesa receipt link on thank-you page and in order emails
* Admin settings: API key, email, polling interval, timeout
* Live connection test in admin — confirms your API key works
* Supports Till, Paybill, Bank, and B2C app types (configured in your GiftedPay dashboard)
* Compatible with WooCommerce High Performance Order Storage (HPOS)

= How It Works =

1. Customer selects "M-Pesa (via GiftedPay)" at checkout
2. Enters their Safaricom phone number
3. Places order → STK Push arrives on their phone
4. They enter M-Pesa PIN → payment confirmed
5. Order is automatically marked as paid in WooCommerce

== Installation ==

1. Upload the `giftedpay-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. Go to **WooCommerce → Settings → Payments → GiftedPay M-Pesa**
4. Enter your GiftedPay API Key and Account Email
5. Set your app's Callback URL in GiftedPay dashboard to:
   `https://yourstore.com/?wc-api=giftedpay_callback`
6. Save settings — the connection test will confirm everything works

= Getting Your API Key =

1. Sign up at https://pay.gifted.co.ke
2. Go to Dashboard → Apps → create or select an app
3. Copy the API key (starts with `gifted_mpesa_stk_`)
4. Paste it in the plugin settings

= Requirements =

* WooCommerce 6.0+
* GiftedPay account (free to create, sandbox is always free)
* For live payments: GiftedPay production plan (KES 350/month or KES 3,000 lifetime)
* Your WooCommerce store currency must be set to **KES (Kenyan Shilling)**
* Store must be accessible over HTTPS

== Frequently Asked Questions ==

= What is GiftedPay? =
GiftedPay is a Kenyan M-Pesa payment gateway. Sign up free at https://pay.gifted.co.ke.

= Does this work in sandbox/test mode? =
Yes. In sandbox mode, only your registered GiftedPay phone number can make test payments. Real money is charged — sandbox is a live integration restricted to the owner's number.

= What currency should my store use? =
Set WooCommerce currency to KES (Kenyan Shilling) under WooCommerce → Settings → General.

= What if a payment is not confirmed? =
The thank-you page polls every few seconds for up to ~2.5 minutes. If still unconfirmed, a timeout message is shown. GiftedPay's callback will still update the order if payment completes later.

= How do I handle refunds? =
M-Pesa refunds are processed manually by contacting GiftedPay support at payments@gifted.co.ke.

== Changelog ==

= 1.0.0 =
* Initial release

== Support ==

* Documentation: https://pay.gifted.co.ke/api-guide
* Support email: payments@gifted.co.ke
* Website: https://pay.gifted.co.ke
