<?php
/**
 * Custom Post Type registration for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPT {

    public const POST_TYPE = 'shortlink';

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register' ] );
        add_filter( 'wp_unique_post_slug', [ $this, 'maybe_preserve_slug' ], 10, 6 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
    }

    public function register(): void {
        $labels = [
            'name'                  => __( 'Short Links', 'shortlink' ),
            'singular_name'         => __( 'Short Link', 'shortlink' ),
            'menu_name'             => __( 'Short Links', 'shortlink' ),
            'add_new'               => __( 'Add New', 'shortlink' ),
            'add_new_item'          => __( 'Add New Short Link', 'shortlink' ),
            'edit_item'             => __( 'Edit Short Link', 'shortlink' ),
            'new_item'              => __( 'New Short Link', 'shortlink' ),
            'view_item'             => __( 'View Short Link', 'shortlink' ),
            'view_items'            => __( 'View Short Links', 'shortlink' ),
            'search_items'          => __( 'Search Short Links', 'shortlink' ),
            'not_found'             => __( 'No short links found.', 'shortlink' ),
            'not_found_in_trash'    => __( 'No short links found in trash.', 'shortlink' ),
            'all_items'             => __( 'All Short Links', 'shortlink' ),
            'archives'              => __( 'Short Link Archives', 'shortlink' ),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'show_in_rest'        => true,
            'rest_base'           => 'shortlinks',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'has_archive'         => false,
            'rewrite'             => false, // We register our own rewrite rules.
            'menu_icon'           => 'dashicons-admin-links',
            'menu_position'       => 20,
            'supports'            => [ 'title' ],
            'capability_type'     => 'post',
            'capabilities'        => [
                // Editor-enabled fork: lowered from 'manage_options' to
                // 'edit_posts' so Editors can create shortlinks via the WP
                // REST CPT route and the wp-admin "Add New" UI.
                'create_posts' => 'edit_posts',
            ],
            'map_meta_cap'        => true,
            'show_in_menu'        => true,
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Register rewrite rules that route /{prefix?}/{slug} -> our redirect handler.
     */
    public static function register_rewrite(): void {
        $prefix  = trim( (string) Helper::setting( 'default_prefix', '' ), '/' );
        $pattern = $prefix ? "{$prefix}/([^/]+)/?\$" : "([^/]+)/?\$";
        add_rewrite_rule( $pattern, 'index.php?' . self::POST_TYPE . '=1&sl_slug=$matches[1]', 'top' );
        add_rewrite_tag( '%sl_slug%', '([^/]+)' );
    }

    /**
     * On init, register rewrite rules after the CPT is in place.
     */
    public function maybe_register_rewrite() {
        // Hooked from shortlink.php -> register_rewrite is called manually on activation.
        self::register_rewrite();
    }

    /**
     * Is a given slug already used by a specific post ID?
     * Used to allow re-saving a post without false collision errors.
     */
    public function is_current( string $slug, int $post_id ): bool {
        $existing = get_posts( [
            'name'           => $slug,
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => [ $post_id ],
        ] );
        return empty( $existing );
    }

    /**
     * Make sure user-supplied slugs aren't rewritten by wp_unique_post_slug.
     */
    public function maybe_preserve_slug( $override, $slug, $post_status, $post_type, $post_parent, $original ) {
        if ( self::POST_TYPE !== $post_type ) {
            return $override;
        }
        // If the slug we wanted is what was passed in, keep it.
        if ( $original === $slug ) {
            return $original;
        }
        return $override;
    }

    /* ------------------------------------------------------------------ */
    /*  Admin list table columns                                          */
    /* ------------------------------------------------------------------ */

    public function columns( $cols ): array {
        $new = [];
        foreach ( $cols as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['shortlink_slug']   = __( 'Short URL', 'shortlink' );
                $new['shortlink_target'] = __( 'Target', 'shortlink' );
                $new['shortlink_clicks'] = __( 'Clicks', 'shortlink' );
                $new['shortlink_qr']     = __( 'QR', 'shortlink' );
            }
        }
        return $new;
    }

    public function column_content( $column, $post_id ): void {
        switch ( $column ) {
            case 'shortlink_slug':
                $slug    = get_post_field( 'post_name', $post_id );
                $short   = Helper::build_short_url( $slug, false );
                $abs     = Helper::build_short_url( $slug, true );
                printf(
                    '<a class="sl-slug-link" href="%1$s" target="_blank" rel="noopener noreferrer" title="%4$s"><code class="sl-slug">/%2$s</code></a> '
                  . '<button type="button" class="button button-small sl-copy" data-url="%1$s" title="%5$s">%6$s</button>',
                    esc_url( $abs ),
                    esc_html( $short ),
                    esc_attr( $abs ),
                    esc_attr__( 'Open the short URL in a new tab', 'shortlink' ),
                    esc_attr__( 'Copy absolute URL', 'shortlink' ),
                    esc_html__( 'Copy', 'shortlink' )
                );
                break;

            case 'shortlink_target':
                $target = get_post_meta( $post_id, '_sl_target', true );
                if ( $target ) {
                    echo '<a href="' . esc_url( $target ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_trim_words( $target, 8, '…' ) ) . '</a>';
                } else {
                    echo '<span style="color:#a00">' . esc_html__( '(missing)', 'shortlink' ) . '</span>';
                }
                break;

            case 'shortlink_clicks':
                echo esc_html( (string) Stats::clicks_for( (int) $post_id ) );
                break;

            case 'shortlink_qr':
                $slug = get_post_field( 'post_name', $post_id );
                $abs  = Helper::build_short_url( $slug, true );
                echo '<a href="#" class="sl-qr-link" data-url="' . esc_attr( $abs ) . '">' . esc_html__( 'Show', 'shortlink' ) . '</a>';
                break;
        }
    }
}
