# Changelog

## v0.6 — 2026-07-02

- Fixed a noisy false-positive: ZedGuard's own installation folder is now automatically excluded from scanning (regardless of what you name it). Previously `baseline.json` — rewritten on every run by design — was flagged as "changed" on every single scan, generating a false alert each time.
- Added `wflogs` (Wordfence's own internal config/log folder) to the default `exclude_dirs` — it self-updates routinely as part of normal Wordfence operation and was triggering unnecessary alerts.

## v0.5 — 2026-07-02 (critical fix)

- **Fixed a self-deletion bug**: because ZedGuard's own source code contains the literal known-malware strings it's designed to detect (e.g. `myzedd.tech`), a copy or redeploy of `monitor.php` itself could match Layer 1's content-based auto-delete and get wiped by its own logic. Found this the hard way while testing v0.4 — the tool deleted itself twice in a row.
- Added a self-fingerprint constant (`ZEDGUARD_FINGERPRINT`) that both `matchesKnownMalware()` and `checkSuspiciousPatterns()` check for before flagging anything. Any file containing this marker (i.e. ZedGuard itself, any copy, any backup) is now unconditionally exempt from both detection layers, regardless of filename.
- If you're running v0.1–v0.4, **upgrade immediately** — the auto-delete feature can otherwise remove your own monitor.php on the next redeploy or file-manager copy.

## v0.4 — 2026-07-02

- Added URLhaus (abuse.ch) lookup — requires a free Auth-Key from auth.abuse.ch. Flags domains found in suspicious files against their malware-distribution feed.
- Added Spamhaus Intelligence API lookup — requires a free DQS account (username/password). Handles token refresh automatically (JWT expires every 24h, re-login happens on each run, no manual renewal needed).
- All three threat-intel sources (USOM, URLhaus, Spamhaus) are independently toggleable and fail silently if unreachable or misconfigured — none of them can break the core scan.

## v0.3 — 2026-07-02

- Added optional threat-intel lookup: when a file is flagged by the generic suspicious-pattern layer, domains found in that file are checked against Turkey's National Cyber Security Presidency (USOM) free, no-auth threat feed (`siberguvenlik.gov.tr/api`). Matches are appended to the Telegram alert with category and criticality. Toggle via `usom_check_enabled`. Fails silently if the API is unreachable or changes shape — never breaks the core scan.

## v0.2 — 2026-07-02

- Added `notify_on_clean` config option: when `true` (default), every scan run sends a "clean" heartbeat to Telegram instead of only at fixed hours. Set to `false` to fall back to the original `clean_report_hours`-restricted schedule if you'd rather stay quiet when nothing's wrong.

## v0.1 — 2026-07-02

- Initial alpha release.
- Three-layer detection: known-signature auto-delete, generic suspicious-pattern flagging, file add/remove/modify diffing.
- Auto-discovery of sites under the hosting account (no hardcoded site list).
- Telegram notifications.
- Zero dependencies, single PHP file.
