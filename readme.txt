=== ShortLink WP URL Shortener ===
Contributors: you
Tags: url shortener, short link, redirect, qr code, links
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-hosted URL shortener with click tracking, QR codes, REST API, and shortcode support.

== Description ==

ShortLink turns your WordPress site into a self-hosted URL shortener. Each short link is a custom post type entry with a generated (or custom) slug. Visitors hitting `/<slug>` (or `/<prefix>/<slug>` if you set a prefix) are redirected to the target URL.

= Features =
* Auto-generated slugs with configurable length and character set
* Optional URL prefix (e.g. `/go/abc123`)
* Optional custom slugs (editable in the post editor)
* Click tracking (timestamp, IP, user agent, referrer)
* Per-link stats view in the admin
* QR code generator for each short link (inline SVG, no third-party library)
* `[shorturl]` shortcode for embedding short links in posts
* REST API: `GET /shortlink/v1/resolve/<slug>`, `POST /shortlink/v1/links`, etc.
* Configurable redirect status (301/302/303/307)
* GDPR-friendly: tracking toggles per data type
* Clean uninstall

= Shortcode =
`[shorturl id="123"]` — renders the short URL as an anchor.
`[shorturl slug="abc"]` — same, by slug.
`[shorturl id="123"]click here[/shorturl]` — custom link text.
Attributes: `id`, `slug`, `target`, `rel`, `class`.

= REST API examples =
* `GET  /wp-json/shortlink/v1/resolve/abc123` — public resolver.
* `POST /wp-json/shortlink/v1/links` (admin only) — body: `{"target":"https://…","slug":"optional"}`.
* `GET  /wp-json/shortlink/v1/links` (admin only) — paginated list.

= Settings =
Settings → Short Links → Settings. Includes slug length, character set, prefix, redirect status, and tracking toggles.

= Developer notes =
* Filters: `shortlink_qr_payload` (modify QR payload).
* Constants: define `SHORTLINK_FULL_ERASE` in `wp-config.php` to also delete all shortlink posts on uninstall.

== Installation ==
1. Upload the `shortlink` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** screen in WordPress.
3. Visit **Short Links → Add New** to create your first short link.

== Changelog ==
= 1.0.0 =
* Initial release.
