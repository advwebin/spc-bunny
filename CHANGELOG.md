# Changelog

## 3.0.0 — 2026-04-15

### Added
- Per-URL cache purge for post saves — purges post permalink, homepage, primary category, and primary tag instead of the entire Pull Zone
- Single-URL purge button in the Manual Purge admin tab
- `spc_bunny_purge_urls_for_post` filter for customizing which URLs are purged on post save
- Wildcard purge support via `purge_wildcard()` API method
- Background edge rule deployment — no more browser timeouts on deploy
- Deploy status polling in admin UI
- Atomic purge deduplication via `wp_cache_add()` — bulk edits no longer trigger multiple purges
- Rate-limit handling (HTTP 429) — returns structured error with `retry_after`, schedules retry via cron
- SSRF protection in cache warmer — validates sitemap URLs belong to site host
- Deactivation hook — cleans up cron events and temporary options
- Plugin Update Checker (PUC) replaces abandoned GitHub updater library
- User-Agent header on all Bunny API requests
- URL validation via `esc_url_raw()` on all purge inputs

### Fixed
- `wp_unslash()` added to all `$_POST` reads before sanitization (10+ instances)
- Unique nonces assigned to each AJAX handler (3 handlers previously shared one nonce)
- API credentials masked in admin form (no longer visible in page source)
- Raw Bunny API response body stripped from error messages shown to users
- DNS stats response sanitized (no longer forwards full raw API payload)
- Unescaped `$icon` output in admin UI now uses `wp_kses_post()`
- `ajax_update` uses `wp_send_json_success/error()` for consistent response envelope
- `on_rest_save()` now respects `spc_bunny_watched_post_types` filter
- `current_time('mysql')` replaced with `wp_date()` across 4 files
- Autoload parameter fixed on `update_option()` call in `ajax_update`
- JSON decode failures now return `WP_Error` instead of silent empty array

### Changed
- Cache warmer uses recurring `wp_schedule_event` instead of chained single events (more reliable under alternate cron)
- Sitemap URL collection deferred to cron time (no longer blocks post save request)
- SSL verification enabled on warmer requests (was `sslverify => false`)
- Edge rule deployment runs in background cron (was synchronous AJAX blocking 37+ API calls)
- `sleep(1)` removed from edge rule deploy
- `SPC_Bunny_API` accessed via `spc_bunny_api()` singleton (was instantiated 3+ times per request)
- `swcfpc_purge_urls` hook now passes URLs to per-URL purge (was discarding URL list)
- Edge rule settings read once per deploy (was re-reading per rule)
- API request timeout reduced from 15s to 10s
- Content-Type header only sent on requests with body

### Removed
- `class-wp-github-updater.php` — abandoned library (last commit 2020) with `extract()`, query-string auth tokens, and PHP 4 property declarations
