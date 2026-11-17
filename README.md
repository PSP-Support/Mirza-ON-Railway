<div align="center">

# 🤖 Mirza Bot

### A Telegram bot for selling VPN services, with fully automated config creation.

<p>
  <a href="https://t.me/mirzapanel">
    <img src="https://img.shields.io/badge/Telegram-Channel-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white" alt="Telegram Channel"/>
  </a>
  <a href="https://t.me/mirzapanelgroup">
    <img src="https://img.shields.io/badge/Telegram-Group-229ED9?style=for-the-badge&logo=telegram&logoColor=white" alt="Telegram Group"/>
  </a>
  <a href="https://mirzabot.com/docs/">
    <img src="https://img.shields.io/badge/Docs-mirzabot.com-38BDF8?style=for-the-badge&logo=readthedocs&logoColor=white" alt="Documentation"/>
  </a>
</p>

<p>
  <a href="https://github.com/mahdiMGF2/mirzabot/stargazers">
    <img src="https://img.shields.io/github/stars/mahdiMGF2/mirzabot?style=flat-square&color=f5c518" alt="Stars"/>
  </a>
  <a href="https://github.com/mahdiMGF2/mirzabot/network/members">
    <img src="https://img.shields.io/github/forks/mahdiMGF2/mirzabot?style=flat-square" alt="Forks"/>
  </a>
  <a href="https://github.com/mahdiMGF2/mirzabot/issues">
    <img src="https://img.shields.io/github/issues/mahdiMGF2/mirzabot?style=flat-square" alt="Issues"/>
  </a>
  <a href="https://github.com/mahdiMGF2/mirzabot/blob/main/LICENSE">
    <img src="https://img.shields.io/github/license/mahdiMGF2/mirzabot?style=flat-square" alt="License"/>
  </a>
  <img src="https://img.shields.io/badge/PHP-8.4.27%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.4.27+"/>
</p>

</div>

---

## 📚 Contents

- [Overview](#-overview)
- [Documentation](#-documentation)
- [Supported Panels](#-supported-panels)
- [Payment Gateways](#-payment-gateways)
- [Features](#️-features)
- [Installation](#-installation)
- [Command Line](#-command-line)
- [Free vs. Pro](#-free-vs-pro)
- [Languages](#-languages)
- [Support the Project](#-support-the-project)
- [Contributors](#-contributors)

---

## ✨ Overview

**Mirza Bot** sells VPN subscriptions on Telegram and automates the whole flow: purchase, payment, config creation and service management.

It connects to your panels, creates configs automatically, accepts many payment methods, and comes with a **Telegram Mini App** for customers and a **web admin panel** for you.

---

## 📖 Documentation

The full manual is at **[mirzabot.com/docs](https://mirzabot.com/docs/)**: 61 pages in **Persian (فارسی)**. Every menu is documented with the steps the bot asks for, what each field accepts, and the errors you can get.

| Section | What it covers |
|---------|----------------|
| **شروع** (Getting started) | Requirements, server and shared-host install, CLI, updating and removing |
| **ساختار** (Structure) | Repository layout, database tables, which file does what |
| **پنل‌ها** (Panels) | Adding each panel type, protocols and inbounds, manual configs, nodes |
| **فروشگاه** (Shop) | Products, categories, trial accounts, On-Hold, bulk purchase, manual sale, renewals |
| **پرداخت** (Payments) | Card-to-card, online and crypto gateways, wallet and refunds |
| **مدیریت** (Administration) | Admin roles, feature switches, report channel, texts, forced join, web panel |
| **رشد و بازاریابی** (Growth) | Referrals, cashback, discount and gift codes, lottery, reseller system |
| **نگهداری** (Operations) | Cron jobs, backup and restore, security checklist, troubleshooting |

---

## 🧩 Supported Panels

| | |
|---|---|
| Marzban | Pasarguard |
| Marzneshin | 3x-ui (Sanaei) |
| Hiddify | Rebecca |
| Nexora | WGDashboard (WireGuard) |
| WG-Mate | MikroTik |
| IBSng | Mirza Agent |
| Manual sale | |

---

## 💳 Payment Gateways

| Gateway | Type |
|---------|------|
| Card-to-card | Manual (receipt + admin approval) |
| Variza | Automated card-to-card |
| AbanGateway | Automated card-to-card |
| Zarinpal | Online gateway |
| Aqayepardakht | Online gateway |
| IranPay | Online gateway |
| NOWPayments | Crypto |
| Plisio | Crypto |
| CubePay | Crypto |
| Offline crypto | Manual (admin approval) |
| Telegram Stars | Telegram |

---

## ⚙️ Features

**Sales**
- Automated config creation for every purchase, with QR codes
- Trial accounts, bulk purchase and On-Hold services
- Renewal, extra volume, extra time and location change

**Customers**
- Telegram Mini App
- Wallet, purchase history and service management
- Support, FAQ and tutorials
- Phone verification and forced channel join

**Growth**
- Referrals, cashback, discount and gift codes
- Lottery and wheel of fortune
- Agent / reseller system with their own bots

**Administration**
- Web admin panel in four languages
- Multiple admins with roles
- Editable bot texts and username formats
- Automatic backups, expiry reminders and reports

---

## 🚀 Installation

### Requirements

| | |
|---|---|
| **Server** | A clean Ubuntu 22.04 / 24.04 / 26.04 or Debian 12 / 13 |
| **Domain** | A domain pointed to the server's IP |
| **Stack** | PHP 8.5.12+ (or 8.4.27+), Apache, MySQL / MariaDB and SSL, installed by the script |

> 💡 Start from a fresh server with no web server, database or panel on it.

### Install, update, remove

Run this as **root**:

```bash
curl -o install.sh -L https://raw.githubusercontent.com/mahdiMGF2/mirzabot/main/install.sh && bash install.sh
```

After the first install, open the same menu any time with `mirza`:

```
1) Install          4) Update PHP       7) Migrate Free → Pro
2) Update           5) Renew SSL        8) Help
3) Remove           6) Backup database  9) Import database
                                        0) Exit
```

---

## 💻 Command Line

The menu items can also run directly, which is handy for scripts.

| Command | What it does |
|---------|--------------|
| `mirza install` | Install the bot |
| `mirza update` | Update the bot |
| `mirza remove` | Remove the bot and its services |
| `mirza update-php` | Upgrade PHP to the required version |
| `mirza renew` | Renew the SSL certificate |
| `mirza backup` | Back up the database and send it to Telegram |
| `mirza import` | Import a database from an SQL file (beta) |
| `mirza migrate` | Migrate Free → Pro |

| Option | Description |
|--------|-------------|
| `--token` | Telegram bot token |
| `--admin` | Admin chat ID |
| `--domain` | Domain, e.g. `bot.example.com` |
| `--db-user` / `--db-pass` | Database username and password |
| `--version` | A specific release, e.g. `0.9.1` |
| `--channel` | `release`, `beta` or `auto` |
| `-h`, `--help` | Show help |

```bash
mirza install --token 123:ABC --admin 111 --domain bot.example.com --version 0.9.1
mirza update --channel release
```

---

## 💎 Free vs. Pro

| | Free | Pro |
|---|:---:|:---:|
| Automated sales and config creation | ✅ | ✅ |
| Trial accounts, wallet and service management | ✅ | ✅ |
| All supported panels and payment gateways | ✅ | ✅ |
| Advanced customization and analytics | — | ✅ |
| Extra management modules | — | ✅ |

📌 **How to buy Pro:** [see on Telegram](https://t.me/mirzaperimium/4)

---

## 🌍 Languages

The bot and the web panel are fully translated into:

🇮🇷 Persian (فارسی) · 🇬🇧 English · 🇷🇺 Russian (Русский) · 🇨🇳 Chinese (中文)

---

## 💵 Support the Project

If Mirza Bot helps your business, you can support its development with a crypto donation:

<a href="https://nowpayments.io/donation/mahdi">
  <img src="https://img.shields.io/badge/Donate-NowPayments-1A1A2E?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate"/>
</a>

---

## 👥 Contributors

<a href="https://github.com/mahdiMGF2/mirzabot/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=mahdiMGF2/mirzabot" alt="Contributors"/>
</a>

---

<div align="center">

📖 [Documentation](https://mirzabot.com/docs/) · 💬 [Channel](https://t.me/mirzapanel) · 👥 [Group](https://t.me/mirzapanelgroup) · ⭐ [Star on GitHub](https://github.com/mahdiMGF2/mirzabot)

</div>


## Deploy on Railway (Docker)

This is the supplied Mirza Bot source with a Railway deployment layer added—not a reduced demo or a UI-only clone. It keeps the original PHP bot, Telegram Mini App, admin panel, API routes, payment adapters, panel integrations and cron scripts. Railway runs Apache for HTTP requests and Mirza's existing cron dispatcher in the same service.

### 1. Create the Railway project

1. Create a Railway project and add a **MySQL** service.
2. Add this repository as a service (or upload the repository to your own GitHub repository and connect it). Railway detects the root `Dockerfile`.
3. In the web service, set `PORT=8080` and add the variables below. Use Railway reference variables for the MySQL service so credentials stay synchronized.
4. Under Networking, generate a public domain. Set `APP_URL` to that full `https://` URL.
5. Attach a Railway Volume to the web service at `/app/storage` so cache and runtime status files survive redeploys. The MySQL service stores the actual application records.

### 2. Required Variables

| Variable | Value |
|---|---|
| `TELEGRAM_BOT_TOKEN` | Token from BotFather |
| `TELEGRAM_ADMIN_ID` | Numeric Telegram ID of the primary administrator |
| `TELEGRAM_BOT_USERNAME` | Bot username without `@` |
| `PANEL_ADMIN_USERNAME` | Initial web-panel username (default `admin`; only used when the admin table is first created) |
| `PANEL_ADMIN_PASSWORD` | Strong initial web-panel password (required for first setup; only used when the admin table is first created) |
| `APP_URL` | Public Railway URL, e.g. `https://name.up.railway.app` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` reference |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` reference |
| `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` reference |
| `DB_USER` | `${{MySQL.MYSQLUSER}}` reference |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` reference |
| `PORT` | `8080` |
| `AUTO_SETUP` | `true` |
| `RAILWAY_PROXY_MODE` | `true` (required for Telegram webhook requests through Railway proxy) |

The configuration also recognizes Railway's `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`, and `MYSQL_URL`/`DATABASE_URL` variables. Do not publish your bot token or database password.

### 3. What happens at startup

- Waits for MySQL, applies the original Mirza schema and migrations via `table.php`, and registers the Telegram webhook against `APP_URL`.
- Starts Apache and the original `cronbot/run.php` dispatcher (every 60 seconds).
- `/healthz.php` is the Railway health-check endpoint.
- The original admin panel remains at `/panel/login.php`; the customer Mini App remains under `/app/`. On a brand-new database, the first web-panel login is the configured `PANEL_ADMIN_USERNAME` (default `admin`) and `PANEL_ADMIN_PASSWORD`. Changing these Railway variables later does not reset an already-created admin account; change the password from the panel settings instead.

### 4. Important limitations and setup still required

- Configure VPN panel credentials, products, payment gateway keys, report channels and other business settings from the Mirza admin interface after the first successful deployment. Those external services cannot be inferred from this source archive.
- Each VPN panel must be reachable from Railway over the network and must allow the required API/SSH access.
- Payment providers may require you to register callback URLs using your Railway public domain.
- Telegram webhook delivery is protected by Mirza's generated high-entropy webhook secret. On Railway the platform proxy prevents the original direct-IP check from seeing Telegram's address; the application therefore relies on the webhook secret check for that deployment path.
- For production, keep one web replica while the cron dispatcher runs in the same container; multiple replicas could duplicate scheduled work.
- This project is licensed under AGPL-3.0-or-later. If you modify and offer it as a network service, review the license obligations and preserve required notices/source availability.

### 5. Verify

After deployment, open `https://YOUR-DOMAIN/healthz.php`, then visit `https://YOUR-DOMAIN/panel/login.php`. Send `/start` to your Telegram bot. Check Railway deployment logs for `[Mirza]` startup messages and PHP errors.
