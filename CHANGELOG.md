# Changelog

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
