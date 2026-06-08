<?php
/**
 * Helper utilities for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helper {

    public static function settings(): array {
        $defaults = [
            'slug_length'      => 6,
            'slug_charset'     => 'alnum',
            'allow_custom'     => 1,
            'track_clicks'     => 1,
            'track_useragent'  => 1,
            'track_referrer'   => 1,
            'redirect_status'  => 302,
            'default_prefix'   => '',
        ];
        $opts = get_option( 'shortlink_settings', [] );
        return wp_parse_args( $opts, $defaults );
    }

    /**
     * Get a setting value with default.
     */
    public static function setting( string $key, $fallback = null ) {
        $s = self::settings();
        return $s[ $key ] ?? $fallback;
    }

    /**
     * Update a single setting, preserving the rest.
     */
    public static function update_setting( string $key, $value ): bool {
        $s = self::settings();
        $s[ $key ] = $value;
        return update_option( 'shortlink_settings', $s );
    }

    /**
     * Return the character pool used to mint slugs.
     */
    public static function charset( string $name = '' ): string {
        if ( '' === $name ) {
            $name = (string) self::setting( 'slug_charset', 'alnum' );
        }
        switch ( $name ) {
            case 'alnum_lower':
                return 'abcdefghijklmnopqrstuvwxyz0123456789';
            case 'alnum_no_lookalike':
                // Skip 0/O, 1/l/I to avoid confusion when reading QR / handwritten.
                return 'abcdefghijkmnpqrstuvwxyz23456789';
            case 'alnum':
            default:
                return 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        }
    }

    /**
     * Generate a random slug of a given length.
     */
    public static function generate_slug( int $length = 0 ): string {
        if ( $length <= 0 ) {
            $length = (int) self::setting( 'slug_length', 6 );
        }
        $length = max( 1, min( 32, $length ) );
        $chars  = self::charset();
        $max    = strlen( $chars ) - 1;
        $out    = '';
        for ( $i = 0; $i < $length; $i++ ) {
            // wp_rand is cryptographically safer when available.
            $out .= $chars[ wp_rand( 0, $max ) ];
        }
        return $out;
    }

    /**
     * Build a unique slug; retries on collision.
     */
    public static function unique_slug( int $length = 0, int $tries = 8 ): string {
        for ( $i = 0; $i < $tries; $i++ ) {
            $slug = self::generate_slug( $length );
            if ( ! self::slug_exists( $slug ) ) {
                return $slug;
            }
        }
        // Fallback: append a microsecond suffix.
        return self::generate_slug( $length ) . substr( (string) microtime( true ), -4 );
    }

    public static function slug_exists( string $slug ): bool {
        global $wpdb;
        $slug = trim( $slug, '/' );
        if ( '' === $slug ) {
            return true;
        }
        $post = get_posts( [
            'name'           => $slug,
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );
        if ( ! empty( $post ) ) {
            return true;
        }
        // Also disallow clashes with WP core reserved slugs.
        $reserved = [ 'page', 'comments', 'feed', 'wp-admin', 'wp-login.php', 'wp-content', 'wp-includes', 'robots.txt', 'sitemap.xml' ];
        return in_array( $slug, $reserved, true );
    }

    /**
     * Return the public short URL for a slug.
     */
    public static function build_short_url( string $slug, bool $absolute = true ): string {
        $slug    = ltrim( $slug, '/' );
        $prefix  = trim( (string) self::setting( 'default_prefix', '' ), '/' );
        $base    = $absolute ? home_url( '/' ) : '';
        $path    = $prefix ? $prefix . '/' . $slug : $slug;
        return $base . $path;
    }

    /**
     * Sanitize a user-supplied custom slug.
     */
    public static function sanitize_custom_slug( string $raw ): string {
        $raw = strtolower( trim( $raw ) );
        $raw = preg_replace( '/[^a-z0-9_\-]+/i', '', $raw );
        return substr( $raw, 0, 64 );
    }

    /**
     * Quick stat: count links.
     */
    public static function total_links(): int {
        $counts = wp_count_posts( CPT::POST_TYPE );
        return (int) ( $counts->publish ?? 0 );
    }

    /**
     * Quick stat: count clicks (sum).
     */
    public static function total_clicks(): int {
        global $wpdb;
        $table = Stats::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        return $total;
    }
}
