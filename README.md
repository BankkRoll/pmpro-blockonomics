# Paid Memberships Pro — Blockonomics Bitcoin Gateway

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b)
![PMPro](https://img.shields.io/badge/PMPro-2.0%2B-orange)

> **Disclaimer:** This plugin is provided as-is, without warranty of any kind. Use at your own risk. The author is not responsible for any financial loss, missed payments, or membership activation issues. Always test thoroughly in a staging environment before deploying to production. This is not financial advice.

Accept Bitcoin payments on [Paid Memberships Pro](https://www.paidmembershipspro.com/) using [Blockonomics](https://www.blockonomics.co/) — non-custodial, direct-to-wallet. No intermediaries, no custodial risk, Bitcoin goes straight to your wallet.

---

## Features

- **Non-custodial** — funds go directly to your Bitcoin wallet, zero exposure
- **Unique address per order** — fresh address generated per checkout via the Blockonomics API
- **Configurable confirmation threshold** — instant (0) or wait for 1–2 on-chain confirmations
- **Live payment page** — QR code, exact BTC amount, one-click copy, real-time status polling
- **BTC price cache** — exchange rate cached for 5 minutes to cut API calls
- **Underpayment tolerance** — 1% grace margin covers exchange rate drift at payment time
- **Idempotency safe** — replay-proof webhook handling, memberships never double-activated
- **Fully translatable** — all strings use WordPress i18n

---

## Requirements

| | Version |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| Paid Memberships Pro | 2.0+ |
| Blockonomics account | [Sign up free](https://www.blockonomics.co/#/merchants) |

---

## Installation

1. Clone or download this repo and upload the `pmpro-blockonomics` folder to `/wp-content/plugins/`
2. Activate through **Plugins** in the WordPress admin

```bash
git clone https://github.com/BankkRoll/pmpro-blockonomics.git
```

---

## Configuration

**1. Get your API key**
Go to [blockonomics.co/#/merchants](https://www.blockonomics.co/#/merchants), create a store, and copy the API key.

**2. Set up the plugin**
In WordPress go to **Memberships > Payment Settings**, select **Bitcoin (Blockonomics)**, and paste your API key.

**3. Set the callback URL in Blockonomics**
Copy the callback URL shown in PMPro settings and paste it into your Blockonomics store dashboard:
```
https://yoursite.com/?pmpro-blockonomics=callback
```

**4. Choose a confirmation threshold**

| Value | Risk | Recommended for |
|---|---|---|
| 0 — Instant | Higher | Low-value memberships |
| 1 — Low | Medium | Standard memberships |
| 2 — Standard | Lower | Higher-value access |

---

## How It Works

1. Member selects **Bitcoin (Blockonomics)** at checkout
2. Plugin fetches the live BTC rate and generates a unique address via Blockonomics
3. Member is redirected to a payment page: QR code, exact BTC amount, copy-to-clipboard address
4. Blockonomics POSTs to `/?pmpro-blockonomics=callback` on transaction detect/confirm
5. Plugin verifies the amount and activates the membership at the configured threshold

**Callback parameters from Blockonomics:**

| Param | Type | Values |
|---|---|---|
| `addr` | string | Bitcoin address |
| `status` | int | `0` unconfirmed, `1` partial, `2` confirmed |
| `value` | int | Satoshis received |
| `txid` | string | Transaction ID |

---

## FAQ

**Where does the Bitcoin go?**
Directly to the wallet linked to your Blockonomics account. The plugin never holds funds.

**What if a member underpays?**
Up to 1% shortfall is tolerated. Below that the order is flagged `underpaid` and not activated.

**Does this support recurring billing?**
No — Bitcoin payments are one-time. Recurring requires manual renewal.

---

## Contributing

PRs welcome. Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) and test against the latest PMPro stable release.

---

## License

[GPL-2.0-or-later](LICENSE)
