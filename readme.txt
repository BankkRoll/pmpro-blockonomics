=== Paid Memberships Pro - Blockonomics Bitcoin Gateway ===
Contributors: BankkRoll
Tags: paid memberships pro, pmpro, bitcoin, cryptocurrency, blockonomics, payment gateway
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin payments on Paid Memberships Pro via Blockonomics direct-to-wallet settlement.

== Description ==

This plugin adds a Bitcoin payment gateway to [Paid Memberships Pro](https://www.paidmembershipspro.com/) using [Blockonomics](https://www.blockonomics.co/), a non-custodial, direct-to-wallet Bitcoin payment processor active since 2015.

= How it works =

1. A member reaches the PMPro checkout page and selects Bitcoin.
2. The plugin generates a unique Bitcoin address via the Blockonomics API.
3. The member is shown a payment page with the BTC amount, QR code, and address.
4. Blockonomics calls the plugin's webhook when payment is received.
5. The membership is activated once the configured number of confirmations is reached.

= Features =

* Non-custodial — Bitcoin goes directly to your wallet.
* Configurable confirmation threshold (0 = instant, 1–2 = on-chain confirmation).
* Live-updating payment page with QR code.
* 5-minute BTC price cache to reduce API calls.
* 1% underpayment tolerance to accommodate exchange rate fluctuations at payment time.

= Requirements =

* Paid Memberships Pro 2.0 or higher.
* A Blockonomics account with an API key. Create one at https://www.blockonomics.co/#/merchants.

== Installation ==

1. Upload the `pmpro-blockonomics` folder to `/wp-content/plugins/`.
2. Activate the plugin through the *Plugins* menu in WordPress.
3. Go to **Memberships > Payment Settings**, select **Bitcoin (Blockonomics)** as the gateway.
4. Enter your Blockonomics API key.
5. In the Blockonomics dashboard, set the callback URL to the value shown in the settings page.
6. Save settings.

== Frequently Asked Questions ==

= Where do I get a Blockonomics API key? =

Sign up or log in at https://www.blockonomics.co/#/merchants. Create a new store and copy the API key.

= What is the callback URL? =

The callback URL is displayed on the PMPro payment settings page. It follows the pattern:
`https://yoursite.com/?pmpro-blockonomics=callback`

Enter this URL in the Blockonomics dashboard under your store's callback settings.

= How many confirmations should I require? =

For low-value memberships, 0 confirmations (instant) is acceptable. For higher-value memberships, 1–2 confirmations is recommended.

== Changelog ==

= 1.0.0 =
* Initial release.
