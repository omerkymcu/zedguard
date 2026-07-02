<div align="center">

# 🛡️ ZedGuard

**A single-file, zero-dependency file integrity + malware watchdog for shared hosting.**

Runs via cron, reports suspicious changes to **Telegram**, and **auto-deletes** known malware.

![Status](https://img.shields.io/badge/status-alpha-orange)
![License](https://img.shields.io/badge/license-MIT-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![Dependencies](https://img.shields.io/badge/dependencies-0-brightgreen)

</div>

---

## 🐦 Why this exists

Every site on a 27-domain Hostinger account got hit at the exact same second, through a **single leaked credential**. The attacker (signed "Zedd") dropped the same backdoor (`awp-niin.php` — a dropper that fetches remote code) on every domain, planted a fake WordPress plugin (`wp-content/plugins/lexom/` — "Kicau Mania Plugin," injecting SEO spam) and left a `/** Secured by Zedd **/` signature inside every `wp-config.php`.

The real problem: **it went unnoticed for weeks.** No WAF, no file integrity monitor, nothing on shared hosting to catch it. Even after credentials were rotated and the account cleaned, the *exact same* mass-drop happened again hours later — because nothing was watching in real time.

ZedGuard was written overnight so that doesn't happen again. Kept deliberately simple: **one file, zero dependencies, just PHP + cron.**

## ⚠️ Alpha warning

This tool is currently **running on a real account, after a real incident** — and it works. But:
- It's only been tested in one environment (Hostinger shared hosting)
- The known-signature list is derived from this specific incident's IOCs, not a general malware database
- The codebase is small and open to review — PRs and issues welcome

**Don't treat this as a replacement for a real antivirus/firewall.** For WordPress sites, pair it with something mature like [Wordfence](https://wordfence.com) — ZedGuard complements that, it doesn't replace it.

## ✨ What it does

```
┌─────────────────────────────────────────────────────────┐
│  Hosting cron (hourly)                                    │
│         │                                                 │
│         ▼                                                 │
│  monitor.php  ──►  /home/user/domains/*/public_html       │
│         │              (each site's root, uploads excl.)  │
│         ▼                                                 │
│  ┌───────────────────────────────────────────────────┐   │
│  │ Layer 1: Known-signature match                     │   │
│  │   → AUTO-DELETE + Telegram alert                   │   │
│  ├───────────────────────────────────────────────────┤   │
│  │ Layer 2: Generic suspicious behavior (eval/base64, │   │
│  │   shell_exec($_GET), unicode obfuscation, etc.)    │   │
│  │   → ALERT ONLY, never deletes                      │   │
│  ├───────────────────────────────────────────────────┤   │
│  │ Layer 3: File added/removed/size-changed           │   │
│  │   → ALERT                                          │   │
│  └───────────────────────────────────────────────────┘   │
│         │                                                 │
│         ▼                                                 │
│     📱 Telegram notification                              │
└─────────────────────────────────────────────────────────┘
```

- **Zero dependencies** — plain PHP, no Composer, no framework
- **Auto-discovers sites** — add a new domain and it's picked up automatically; remove one and it's silently dropped, no false alarm
- **Not web-accessible** — locked down with `.htaccess`, only cron/CLI can run it
- **No FTP required** — reads the server's own filesystem directly, unaffected even if the master FTP password changes

## 📱 Example notifications

```
🛡️ ZedGuard installed. Baseline captured (11 sites). Starting periodic scans.

✅ Scan complete — 2026-07-02 18:00 — all sites clean (11 sites checked)

🗑️ AUTO-REMOVED (known signature)
mysite.com: awp-niin.php

🔎 SUSPICIOUS (manual review needed, not deleted)
mysite.com: wp-content/themes/custom/footer-new.php — obfuscated eval

⚠️ FILE CHANGE — 2026-07-02 19:00
⚠️ mysite.com — CHANGE DETECTED
  + NEW: wp-content/uploads/2026/07/image.jpg
```

## 🚀 Installation

### 1. Upload the files

Place these inside **one** of your sites' document root, in a folder that is **not web-accessible**:

```
/home/user/domains/example.com/public_html/_zedguard/
  ├── monitor.php
  ├── config.example.php
  └── .htaccess   (see below)
```

`.htaccess` contents (blocks the folder entirely):

```apache
Order Allow,Deny
Deny from all
```

### 2. Configure

```bash
cp config.example.php config.php
```

Fill in `config.php` with your own values:

```php
return [
    'telegram_token'   => 'BOT_TOKEN',      // from @BotFather
    'telegram_chat_id' => 'CHAT_ID',        // message your bot, then check getUpdates
    'sites_base_dir'   => '/home/username/domains',
    'exclude_dirs'     => ['uploads', 'media', 'backups', 'cache', 'node_modules', '.git'],
    'clean_report_hours' => [0, 6, 12, 18], // how often/when to send "all clean" heartbeats
];
```

### 3. Add a cron job

hPanel (or cPanel) → Cron Jobs:

```
0 * * * *  php /home/username/domains/example.com/public_html/_zedguard/monitor.php
```

Hourly is a reasonable default; use something like `*/15 * * * *` for a tighter interval.

### 4. First run

The first run only captures a baseline and sends an "installed" notification — there's nothing to compare against yet, so it won't alarm. To test without waiting for cron, temporarily place the script somewhere without the `.htaccess` block, hit it once via browser/`curl`, then remove it immediately.

## 🔧 Adding your own signatures

If you run into a different attack/signature in your own environment, extend the lists in `monitor.php`:

```php
$KNOWN_MALWARE_NAMES = ['awp-niin.php', 'dragonshell.php', /* ...your finding... */];
$KNOWN_MALWARE_CONTENT = ['myzedd.tech', /* ...your finding... */];
```

## 🗺️ Roadmap

- [ ] Configurable notification channels (Discord, Slack, email)
- [ ] `.env`-based configuration option
- [ ] Known-signature list as a separate, updatable JSON file
- [ ] Simple web-based status dashboard (optional, separately `.htaccess`-protected)
- [ ] Multi-account support (wire multiple cPanel/Hostinger accounts to one Telegram bot)

## 🤝 Contributing

Issues and PRs welcome — especially new malware signatures, test results from different hosting environments, and bug fixes.

## 📄 License

MIT — see [LICENSE](LICENSE).

---

<div align="center">
<sub>Born from a real attack, running on a real account. Good luck out there. 🍀</sub>
</div>
