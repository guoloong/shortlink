<?php
/**
 * Redirect handler for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Redirect {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ __NAMESPACE__ . '\\CPT', 'register_rewrite' ] );
        add_filter( 'query_vars', [ $this, 'register_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
    }

    public function register_query_var( $vars ): array {
        $vars[] = 'sl_slug';
        return $vars;
    }

    /**
     * If the request matches one of our short codes, redirect (or 404) it.
     */
    public function maybe_redirect(): void {
        $slug = get_query_var( 'sl_slug' );
        if ( empty( $slug ) ) {
            return;
        }

        $slug = sanitize_title( wp_unslash( $slug ) );
        if ( '' === $slug ) {
            return;
        }

        $post = get_posts( [
            'name'           => $slug,
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        if ( empty( $post ) ) {
            // Let WP 404 normally.
            return;
        }

        $link_id  = (int) $post[0];
        $target   = get_post_meta( $link_id, '_sl_target', true );
        if ( ! $target ) {
            status_header( 410 );
            nocache_headers();
            wp_die( esc_html__( 'This short link has no target URL.', 'shortlink' ), '', [ 'response' => 410 ] );
        }

        // Track click before redirecting.
        if ( (int) Helper::setting( 'track_clicks', 1 ) ) {
            Stats::record_click( $link_id );
        }

        $status = (int) Helper::setting( 'redirect_status', 302 );
        if ( ! in_array( $status, [ 301, 302, 303, 307 ], true ) ) {
            $status = 302;
        }

        // NOTE: do NOT use wp_safe_redirect() here — it restricts destinations
        // to the current host (and a filter whitelist), so any external target
        // (api.whatsapp.com, etc.) silently falls back to admin_url(). A URL
        // shortener MUST allow arbitrary external destinations by design.
        $target = wp_sanitize_redirect( esc_url_raw( $target ) );

        /**
         * Filter the final redirect target.
         *
         * @param string $target  The sanitized target URL.
         * @param int    $link_id The shortlink post ID.
         */
        $target = (string) apply_filters( 'shortlink_redirect_target', $target, $link_id );

        if ( '' === $target ) {
            status_header( 410 );
            nocache_headers();
            wp_die( esc_html__( 'This short link has no target URL.', 'shortlink' ), '', [ 'response' => 410 ] );
        }

        wp_redirect( $target, $status, 'ShortLink' );
        exit;
    }
}
