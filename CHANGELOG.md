# Changelog

## v0.2 — 2026-07-02

- Added `notify_on_clean` config option: when `true` (default), every scan run sends a "clean" heartbeat to Telegram instead of only at fixed hours. Set to `false` to fall back to the original `clean_report_hours`-restricted schedule if you'd rather stay quiet when nothing's wrong.

## v0.1 — 2026-07-02

- Initial alpha release.
- Three-layer detection: known-signature auto-delete, generic suspicious-pattern flagging, file add/remove/modify diffing.
- Auto-discovery of sites under the hosting account (no hardcoded site list).
- Telegram notifications.
- Zero dependencies, single PHP file.
