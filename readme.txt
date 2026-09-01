=== ShortLink WP URL Shortener ===
Contributors: you
Tags: url shortener, short link, redirect, qr code, links
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1-editor
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
* `POST /wp-json/shortlink/v1/links` (Editor or admin) — body: `{"target":"https://…","slug":"optional"}`.
* `GET  /wp-json/shortlink/v1/links` (Editor or admin) — paginated list.
* `GET  /wp-json/shortlink/v1/links/<id>/stats` (Editor or admin) — per-link click stats.

= Permissions =
Starting with version `1.0.1-editor`, the REST API endpoints and the
"Add New Short Link" admin page are available to any user with the
`edit_posts` capability (Editor role and above), not only admins
(`manage_options`).

The **Settings** page and the global **Stats** page remain admin-only.

This is a fork intended for sites that delegate shortlink management
to Editors — for example, marketing teams who run their own short URLs
without involving the site admin. If you need the original
admin-only behavior, check out the `1.0.0` tag.

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

= 1.0.1-editor =
* Lowered the capability gate on the REST API (`POST/GET /shortlink/v1/links`,
  `GET /shortlink/v1/links/{id}/stats`) and on the shortlink CPT
  (`create_posts`) from `manage_options` to `edit_posts`, so Editor-role
  users can create, list, and inspect shortlinks via REST and the
  wp-admin "Add New" button.
* The Settings page and the global Stats page remain admin-only.
* Plugin name suffix changed to "(Editor-enabled)" so the fork is obvious
  in the Plugins list. This is a backward-compatible change for any site
  where the shortlink admin is the same person as the Editor — for sites
  that want the original admin-only behavior, the `1.0.0` tag is unchanged.

= 1.0.0 =
* Initial release.
