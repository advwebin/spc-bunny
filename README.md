# SPC Bunny Connector

| | |
|---|---|
| **Version** | 3.0.0 |
| **Last Updated** | April 15, 2026 |
| **Requires WordPress** | 6.0+ |
| **Requires PHP** | 8.2+ |
| **License** | GPL-2.0+ |
| **Author** | [Nahnu Media](https://nahnumedia.com) |
| **GitHub** | [advwebin/spc-bunny](https://github.com/advwebin/spc-bunny) |

**WordPress plugin — Super Page Cache + Bunny.net CDN**

Automatically keeps your Bunny Pull Zone cache in sync with Super Page Cache. Deploys a full suite of edge rules, cleans up Perma-Cache storage, warms the cache after purges, and surfaces live CDN stats — all from a single WordPress admin panel.

---

## The Problem

Super Page Cache Pro manages your server's HTML cache. Bunny CDN serves that HTML from the edge. But when SPC clears its cache, Bunny doesn't know. Your server has fresh content while Bunny keeps serving stale HTML to visitors.

The obvious fix — hook into SPC's purge actions — turns out to be non-trivial. The hooks the SPC team documents (`swcfpc_cf_purge_whole_cache_after`, `swcfpc_cf_purge_cache_by_urls_after`) only fire when Cloudflare is actively connected. Since this stack uses Bunny instead of Cloudflare, those hooks never fire.

The correct hooks are `swcfpc_purge_all` and `swcfpc_purge_urls`, found in `cache_controller.class.php`. These fire unconditionally regardless of CDN provider. This plugin hooks those directly.

---

## Requirements

- WordPress 6.0+
- PHP 8.2+
- Super Page Cache (free or Pro)
- Bunny.net account with a Pull Zone
- Composer (for plugin auto-updates via GitHub)

---

## Installation

### From GitHub (recommended)

1. Download the latest zip from [Releases](../../releases)
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. Go to **Settings → SPC Bunny Connector**

### From Source

```bash
git clone https://github.com/advwebin/spc-bunny.git spc-bunny-connector
cd spc-bunny-connector
composer install
```

Copy the `spc-bunny-connector` directory into `wp-content/plugins/` and activate.

### Composer Dependency

The plugin uses [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) for auto-updates from GitHub. Run `composer install` in the plugin directory to install it. If Composer is not available, the plugin works normally — auto-updates are simply disabled.

---

## Setup

### Step 1: Connect to Bunny.net

1. Go to **Settings → SPC Bunny Connector → Settings tab**
2. Enter your **Bunny Account API Key** (found in your Bunny dashboard → Account → API Keys)
3. Click **Test Connection** to verify
4. Click **Load Zones** and select your **Pull Zone** from the dropdown
5. Click **Save Settings**

### Step 2: Enable Purge Triggers

Choose which WordPress events should trigger a CDN cache clear:

| Trigger | When to Enable |
|---|---|
| **Post publish / update** | Always recommended. Purges the post URL, homepage, and related archives when content changes. |
| **Plugin / theme updates** | Recommended. Full zone purge when plugins, themes, or WordPress core are updated. |
| **Super Page Cache events** | Recommended. Syncs Bunny purge with SPC's own purge button and scheduled purges. |

### Step 3: Deploy Edge Rules (Optional)

1. Go to the **Edge Rules tab**
2. Review the 20 rules — toggle off any you don't need
3. Select your preferred **Cache TTL** (1 week recommended)
4. Choose **Cache-Control Mode**: Respect Origin Headers (default) or Force Cache
5. Click **Deploy Edge Rules**

Deployment runs in the background. The UI polls for status and shows results when complete.

### Step 4: Configure Cache Warmer (Optional)

1. In **Settings → Advanced**, check **Auto-warm cache after full purge**
2. Adjust **URLs per batch** (default: 5) and **Delay between batches** (default: 30s)
3. Save Settings

The warmer automatically detects your sitemap (supports Yoast, Rank Math, AIOSEO, SEOPress, SlimSEO, The SEO Framework, Squirrly, and WordPress core sitemaps).

### Step 5: Configure Perma-Cache Cleanup (Optional)

If you use Bunny Perma-Cache:

1. In **Settings → Perma-Cache Cleanup**, check **Enable**
2. Enter your **Storage Zone Name** and **Storage Zone Password** (found under FTP & API Access — NOT your account API key)
3. Select your **Storage Region**
4. Click **Test Connection** to verify
5. Save Settings

Old Perma-Cache directories are automatically cleaned up after every full purge.

---

## Configuration

### Settings Tab

| Setting | Description |
|---|---|
| Account API Key | Your Bunny.net account API key (masked after saving) |
| Pull Zone | The Pull Zone connected to your WordPress site |
| DNS Zone ID | Optional — enables the DNS Stats tab if you use Bunny DNS |
| Post publish / update | Per-URL purge when a post/page is published, updated, or deleted |
| Plugin / theme updates | Full zone purge when plugins or themes are updated |
| SPC cache clear events | Syncs Bunny purge with Super Page Cache clear operations |
| Admin toolbar button | Adds a Purge Bunny button to the WP admin bar |
| Auto-warm cache | Crawls your sitemap after every full purge to pre-populate the edge |
| Perma-Cache cleanup | Deletes old Perma-Cache directories after every purge |

### Edge Rules Tab

Deploys up to 20 edge rules to your Pull Zone. Each rule has an on/off toggle. Rules are deployed in a specific order so bypass rules always take priority over cache rules.

Three action buttons:

| Button | What it does |
|---|---|
| **Deploy Edge Rules** | Creates all rules fresh (runs in background). |
| **Update Edge Rules** | Wipes existing rules, then redeploys from scratch. |
| **Remove All Rules** | Deletes every SPC Bunny rule. No redeploy. |

**Custom Cache Exclusions** — enter one path per line to bypass caching for specific pages. Supports wildcards (`/shop/*`).

### Manual Purge Tab

Two purge options:

- **Purge Entire CDN Cache** — full Pull Zone purge (clears everything)
- **Purge Single URL** — purge one specific URL without affecting the rest of the cache

### Purge Log Tab

Shows the last 20 purge events with timestamp, trigger source, and result. Includes per-URL purges, full purges, deduplicated (skipped) purges, and Perma-Cache cleanup results.

### Stats Tab

Live CDN statistics: cache hit rate, bandwidth (cached vs uncached), total requests, average origin response time, and homepage cache health check. Cache sync status shows whether Bunny and SPC purges are in sync.

### DNS Stats Tab

Query statistics for your Bunny DNS zone. Requires a DNS Zone ID in Settings.

---

## How Purges Work

### Two-Tier Purge Strategy (v3.0.0)

| Event | Purge Type | What's Purged |
|---|---|---|
| Single post publish/update/delete | **Per-URL** | Post permalink, homepage, primary category archive, primary tag archive (~4 URLs) |
| REST API post save (Gutenberg, Bricks) | **Per-URL** | Same 4 URLs |
| Post status change (publish → draft) | **Per-URL** | Same 4 URLs |
| SPC per-URL purge | **Per-URL** | URLs passed by SPC |
| Plugin/theme/core update | **Full zone** | Entire Pull Zone |
| Theme switch, plugin activate/deactivate | **Full zone** | Entire Pull Zone |
| SPC "Purge whole cache" button | **Full zone** | Entire Pull Zone |
| Manual purge button (admin) | **Full zone** | Entire Pull Zone |

### Deduplication

Full zone purges are deduplicated via an atomic lock (`wp_cache_add`). If a purge is already in progress, subsequent requests within a 15-second window are logged as "deduplicated" and skipped. This prevents bulk edits from triggering hundreds of redundant API calls.

### Rate-Limit Handling

If Bunny returns HTTP 429 (rate limit exceeded), the plugin:
1. Returns immediately without retrying (no `sleep()`)
2. Schedules a retry via `wp_schedule_single_event` using the `retry-after` header value
3. Logs the rate-limit event to the Purge Log

Bunny's rate limits: 120 burst for exact URL purge, 20 burst for wildcard purge.

---

## Edge Rules

All 20 rules, in deployment order:

| # | Rule | What it does |
|---|---|---|
| 1 | Force SSL | Redirects HTTP → HTTPS at the CDN edge |
| 2 | Disable Shield & WAF: WP admin | Bypasses Shield and WAF for `/wp-admin/*` |
| 3 | Bypass cache: logged-in users | Skips cache for `wordpress_logged_in_*` cookies |
| 4 | Bypass cache: WP admin & PHP | No caching for `/wp-admin/*` and `*.php` |
| 5 | Bypass Perma-Cache: WP admin & PHP | Admin responses never stored in Perma-Cache |
| 6 | Disable Optimizer: WP admin & login | Prevents Bunny Optimizer mangling admin assets |
| 7 | Bypass cache: wp-cron.php | Cron always hits origin |
| 8 | Bypass cache: REST API | `/wp-json/*` never cached |
| 9 | Bypass cache: POST requests | All POST requests bypass cache |
| 10 | Bypass cache: RSS/Atom feeds | Feeds always served fresh |
| 11–12 | WooCommerce pages + cookies | Dynamically resolved from WooCommerce settings |
| 13–14 | SureCart pages + cookies | Checkout, dashboard, cart, shop |
| 15 | Custom URL exclusions | Your custom paths |
| 16 | Cache HTML: anonymous visitors | Caches HTTP 200 responses at edge. TTL configurable. |
| 17 | No browser cache: HTML | Browsers always fetch fresh; edge still caches |
| 18 | Long browser cache: static assets | 1-year browser cache for CSS, JS, images, fonts |
| 19 | Ignore query string: CSS & JS | `?ver=` params share one cache entry |
| 20 | Security headers | X-Content-Type-Options, X-Frame-Options, Referrer-Policy, X-XSS-Protection |

---

## Cache Warmer

After every full purge, an optional background cron job fetches your sitemap and requests each URL to pre-populate Bunny's edge cache.

**Supported sitemap plugins:**
Yoast SEO, Rank Math, All in One SEO, SEOPress, SlimSEO, The SEO Framework, Squirrly SEO, and WordPress core sitemap (5.5+).

Falls back to the homepage plus the 50 most recent posts if no sitemap is found.

**How it works (v3.0.0):**
1. When a full purge fires, the warmer stores a "warm pending" flag — **no sitemap fetch during the purge request**
2. A recurring cron job (every 30 seconds) picks up the pending flag
3. On first run, it fetches the sitemap and expands URLs (up to 500)
4. URLs are processed in configurable batches with overlap prevention
5. The cron self-terminates when the queue is empty

**SSRF Protection:** All URLs from sitemaps are validated against the site's own hostname before fetching. URLs with non-HTTP schemes or foreign hosts are rejected.

---

## Perma-Cache Cleanup

When Bunny does a full Pull Zone purge, Perma-Cache files are **not** deleted. Old directories accumulate and cost storage.

This plugin connects to the Bunny Edge Storage API and deletes old directories after every purge, keeping only the active one.

**Supported storage regions:** Falkenstein (EU), New York, Los Angeles, Singapore, Sydney, Stockholm, Sao Paulo, Johannesburg.

---

## Frequently Asked Questions

**Do I need Super Page Cache Pro or does the free version work?**
Both work. The hooks this plugin uses (`swcfpc_purge_all`, `swcfpc_purge_urls`) exist in the free version.

**What changed in v3.0.0 regarding purge behavior?**
Previously, every event triggered a full Pull Zone purge. Now, single post saves use per-URL purge (4 specific URLs) via Bunny's `/purge` endpoint. Full zone purge is reserved for bulk operations. This reduces cache churn and improves cache hit rates.

**Will the cache warmer slow down my server after a purge?**
No. In v3.0.0, sitemap fetching is deferred to the background cron — it no longer blocks the request that triggered the purge. The warmer processes URLs in batches with configurable delays.

**What happens during a bulk edit of many posts?**
Full zone purges are deduplicated via an atomic lock. The first purge executes; subsequent purges within 15 seconds are skipped and logged. For per-URL purges, each post save purges its own 4 URLs independently.

**What if Bunny's API is down?**
The plugin degrades gracefully. API errors are logged to the Purge Log. Failed per-URL purges are scheduled for retry via WordPress cron. The site continues working normally — Bunny serves from its last cached version.

**Does edge rule deployment block my browser?**
No. In v3.0.0, deployment runs in a background cron job. The admin UI polls for status every 5 seconds and shows results when complete. No more browser timeouts.

**Can I use this without deploying edge rules?**
Yes. Cache sync (purging Bunny when SPC purges) works independently of edge rules.

**Does this work on WordPress multisite?**
Yes. Each sub-site has its own settings, API key, and Pull Zone. The plugin operates per-site, which is correct since each sub-site typically has its own domain/subdomain.

**Can I customize which URLs are purged on post save?**
Yes, via the `spc_bunny_purge_urls_for_post` filter. See the Developer section below.

**What PHP versions are supported?**
PHP 8.2 or higher. The plugin uses PHP 8.2 features (standalone `true` type in union types). It is tested and clean on PHP 8.2, 8.3, and 8.4 with zero deprecation warnings.

**My form submissions stopped working after deploying edge rules.**
Rule 9 (POST bypass) ensures all form submissions bypass cache. If a specific form page needs to bypass for logged-out users too, add its path to **Custom Cache Exclusions** and redeploy.

---

## Developer Reference

### Filters

#### `spc_bunny_purge_urls_for_post`

Modify the list of URLs purged when a post is saved.

**Parameters:**
- `$urls` *(string[])* — URLs to purge (default: permalink, homepage, primary category, primary tag)
- `$post_id` *(int)* — The post ID that triggered the purge
- `$post` *(WP_Post)* — The post object

**Example:**
```php
add_filter( 'spc_bunny_purge_urls_for_post', function( array $urls, int $post_id, WP_Post $post ): array {
    // Also purge a custom archive page
    $urls[] = home_url( '/custom-archive/' );
    return $urls;
}, 10, 3 );
```

**Note:** If the filter returns more than 50 URLs, the plugin automatically falls back to a full zone purge.

#### `spc_bunny_watched_post_types`

Control which post types trigger a per-URL purge on save.

**Parameters:**
- `$post_types` *(string[])* — Post types to watch (default: `['post', 'page']`)

**Example:**
```php
add_filter( 'spc_bunny_watched_post_types', function( array $types ): array {
    $types[] = 'product'; // Also purge WooCommerce products
    return $types;
} );
```

### Architecture

```
spc-bunny-connector/
├── spc-bunny-connector.php             # Bootstrap, singleton, hooks, deactivation
├── CHANGELOG.md                        # Release notes
└── includes/
    ├── class-spc-bunny-api.php         # Bunny REST API wrapper (CDN + per-URL purge)
    ├── class-spc-bunny-purge.php       # Purge orchestration (per-URL + full zone)
    ├── class-spc-bunny-hooks.php       # WordPress action hooks for auto-purge
    ├── class-spc-bunny-admin.php       # Admin UI, AJAX handlers, settings
    ├── class-spc-bunny-warmer.php      # Sitemap-based cache warmer with SSRF protection
    ├── class-spc-bunny-edge-rules.php  # Edge rule deployment (20 rules)
    ├── class-spc-bunny-perma-cache.php # Perma-Cache storage cleanup
    ├── class-spc-bunny-stats.php       # CDN stats with transient caching
    └── class-spc-bunny-updater.php     # GitHub auto-update via PUC
```

### API Singleton

All internal code uses `spc_bunny_api()` to access the shared `SPC_Bunny_API` instance:

```php
$api = spc_bunny_api();
$api->purge_url( 'https://example.com/my-page/' );
```

The constructor remains public for unit testing with explicit options arrays.

### Auto-Updates

Updates are delivered via [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker). It reads the `Version:` header from `spc-bunny-connector.php` on the `main` branch. No GitHub Release required — bump the version header and push to `main`.

For private repositories, define a GitHub Personal Access Token in `wp-config.php`:

```php
define( 'SPC_BUNNY_GITHUB_TOKEN', 'ghp_your_token_here' );
```

PUC sends the token via `Authorization` header (not query string).

### Bunny API Endpoints Used

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/purge?url={url}&async=true` | Per-URL cache purge |
| `POST` | `/pullzone/{id}/purgeCache` | Full Pull Zone cache purge |
| `GET` | `/pullzone` | List Pull Zones |
| `GET` | `/pullzone/{id}` | Get Pull Zone details + EdgeRules |
| `POST` | `/pullzone/{id}` | Update Pull Zone settings |
| `POST` | `/pullzone/{id}/edgerules/addOrUpdate` | Create/update edge rule |
| `DELETE` | `/pullzone/{id}/edgerules/{guid}` | Delete edge rule |
| `GET` | `/statistics?pullZone={id}` | CDN statistics |
| `GET` | `/dnszone` | List DNS zones |
| `GET` | `/dnszone/{id}/statistics` | DNS query statistics |
| `GET` | `/{zone}/__bcdn_perma_cache__/` | List Perma-Cache directories (Storage API) |
| `DELETE` | `/{zone}/{path}/` | Delete Perma-Cache directory (Storage API) |

---

## License

GPL-2.0+

---

Built by [Nahnu Media](https://nahnumedia.com)
