SPC Bunny Connector
A WordPress plugin that bridges Super Page Cache Pro and Bunny.net CDN — automatically keeping your Bunny Pull Zone cache in sync with your server-side cache, with zero manual intervention.

Why This Exists
Super Page Cache Pro is excellent at managing your server's HTML cache. Bunny CDN is excellent at serving that HTML from the edge. But when SPC clears its cache, Bunny doesn't know. You're left with stale HTML at the CDN while your server has fresh content.
This plugin fixes that. It hooks directly into SPC's internal purge controller — not the Cloudflare-specific hooks, but the actual swcfpc_purge_all and swcfpc_purge_urls actions that fire regardless of CDN provider — and triggers a full Bunny Pull Zone purge every time SPC clears its cache. No polling, no cron workarounds. It just works.

Features
Cache Sync

Hooks into SPC's purge flow at the source — full and per-URL purges both trigger a Bunny zone purge
Supports SPC Free and SPC Pro
Also purges on post publish/update, Bricks Builder saves (via REST API hooks), plugin/theme updates, and manual purge button
Cache Sync Status card with live 8-second polling so you can see both caches clearing together in real time

Edge Rules (19 rules, per-rule on/off toggles)
Deployed directly to your Bunny Pull Zone via API:
#Rule1Force SSL — HTTP→HTTPS at the edge2Disable Shield & WAF for WP admin3Bypass cache for logged-in users4Bypass cache for WP admin & PHP5Bypass Perma-Cache for WP admin & PHP6Disable Optimizer for WP admin & login7Bypass cache for wp-cron.php8Bypass cache for REST API9Bypass cache for RSS/Atom feeds10–11WooCommerce pages + session cookies12–13SureCart pages + session cookies14Custom URL exclusions (your own paths)15Cache HTML for anonymous visitors (configurable TTL)16No browser cache for HTML (edge caches, browser doesn't — fresh after every purge without reload)171-year browser cache for static assets18Ignore query strings on CSS & JS19Security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, X-XSS-Protection)
Supports WooCommerce, SureCart, WPML, and Polylang for multilingual URL variants.
Perma-Cache Cleanup
When Bunny does a full Pull Zone purge, Perma-Cache files are not deleted — Bunny switches to a new directory and the old one accumulates storage cost indefinitely. This plugin connects to the Bunny Storage API and automatically deletes old __bcdn_perma_cache__ directories after every purge, keeping only the newest (active) one.
Stats Dashboard
Live data pulled from the Bunny API:

Total bandwidth, cached bandwidth, uncached bandwidth
Cache hit rate
Requests served
Average origin response time
Homepage cache health check (HIT / MISS / BYPASS)

DNS Stats
If you use Bunny DNS, a dedicated tab shows query statistics for your DNS zone — total queries, cached queries, and average response time.
Cache Warmer
After every full purge, an optional background cron job crawls your sitemap and pre-warms the CDN edge so the first real visitor gets a cache hit. Detects and uses the correct sitemap for:

Yoast SEO
Rank Math
All in One SEO
SEOPress
SlimSEO
The SEO Framework
Squirrly
WordPress core sitemap (5.5+)

Configurable batch size and delay between batches.
Custom Cache Exclusions
Enter one path per line in the Edge Rules tab. Those paths bypass Bunny's edge cache on deploy — useful for pages you want to stay dynamic regardless of the global caching rules.

Requirements

WordPress 6.0+
PHP 8.1+
Super Page Cache Pro (free version also supported)
A Bunny.net account with a Pull Zone


Installation

Download the zip from Releases
Upload and activate via Plugins → Add New → Upload
Go to Settings → SPC Bunny Connector
Enter your Bunny Account API Key and select your Pull Zone
Go to Edge Rules and click Deploy Edge Rules


Background
Built by Nahnu Media for internal use across client sites running the Bricks Builder / Super Page Cache / Bunny CDN stack. Open sourced because the SPC + Bunny combination is surprisingly common and surprisingly undocumented.
The hardest part of building this was discovering that the hooks the SPC team documented (swcfpc_cf_purge_whole_cache_after, swcfpc_cf_purge_cache_by_urls_after) only fire when Cloudflare is actively connected. Since these sites use Bunny instead of Cloudflare, those hooks never fire. The correct hooks — swcfpc_purge_all and swcfpc_purge_urls in cache_controller.class.php — fire unconditionally regardless of CDN provider. That took a while to figure out.

License
GPL-2.0+
