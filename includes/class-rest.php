<?php
/**
 * REST API endpoints for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class REST {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns = 'shortlink/v1';

        // Public: resolve a slug to its target. (Useful for headless frontends.)
        register_rest_route( $ns, '/resolve/(?P<slug>[A-Za-z0-9_\-]+)', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => [ $this, 'resolve' ],
            'args'                => [
                'slug' => [ 'required' => true ],
            ],
        ] );

        // Authenticated: create a short link.
        register_rest_route( $ns, '/links', [
            [
                'methods'             => 'POST',
                'permission_callback' => [ $this, 'can_manage' ],
                'callback'            => [ $this, 'create' ],
            ],
            [
                'methods'             => 'GET',
                'permission_callback' => [ $this, 'can_manage' ],
                'callback'            => [ $this, 'list_links' ],
            ],
        ] );

        // Authenticated: get stats for a link.
        register_rest_route( $ns, '/links/(?P<id>\d+)/stats', [
            'methods'             => 'GET',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback'            => [ $this, 'stats' ],
        ] );
    }

    public function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    public function resolve( \WP_REST_Request $req ) {
        $slug = sanitize_title( $req['slug'] );
        $post = get_posts( [
            'name'           => $slug,
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );
        if ( empty( $post ) ) {
            return new \WP_Error( 'not_found', __( 'Short link not found.', 'shortlink' ), [ 'status' => 404 ] );
        }
        $target = get_post_meta( $post[0], '_sl_target', true );
        return rest_ensure_response( [
            'slug'   => $slug,
            'target' => $target,
            'url'    => Helper::build_short_url( $slug, true ),
        ] );
    }

    public function create( \WP_REST_Request $req ) {
        $body  = $req->get_json_params();
        $target = isset( $body['target'] ) ? esc_url_raw( $body['target'] ) : '';
        $custom = isset( $body['slug'] )   ? Helper::sanitize_custom_slug( (string) $body['slug'] ) : '';
        $title  = isset( $body['title'] )  ? sanitize_text_field( $body['title'] ) : '';

        if ( '' === $target ) {
            return new \WP_Error( 'bad_request', __( 'target is required.', 'shortlink' ), [ 'status' => 400 ] );
        }
        $slug = $custom ?: Helper::unique_slug();

        $post_id = wp_insert_post( [
            'post_type'   => CPT::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title ?: $slug,
            'post_name'   => $slug,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        update_post_meta( $post_id, '_sl_target', $target );
        return rest_ensure_response( [
            'id'        => $post_id,
            'slug'      => $slug,
            'target'    => $target,
            'short_url' => Helper::build_short_url( $slug, true ),
        ] );
    }

    public function list_links( \WP_REST_Request $req ) {
        $paged  = max( 1, (int) $req->get_param( 'page' ) );
        $per    = min( 100, max( 1, (int) $req->get_param( 'per_page' ) ?: 20 ) );
        $search = (string) $req->get_param( 'search' );

        $args = [
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => 'publish',
            'paged'          => $paged,
            'posts_per_page' => $per,
        ];
        if ( $search ) {
            $args['s'] = $search;
        }
        $query = new \WP_Query( $args );
        $items = [];
        foreach ( $query->posts as $p ) {
            $items[] = [
                'id'        => $p->ID,
                'slug'      => $p->post_name,
                'target'    => get_post_meta( $p->ID, '_sl_target', true ),
                'title'     => $p->post_title,
                'clicks'    => Stats::clicks_for( (int) $p->ID ),
                'short_url' => Helper::build_short_url( $p->post_name, true ),
                'created'   => $p->post_date_gmt,
            ];
        }
        return rest_ensure_response( [
            'items' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ] );
    }

    public function stats( \WP_REST_Request $req ) {
        $id = (int) $req['id'];
        if ( ! get_post( $id ) || CPT::POST_TYPE !== get_post_type( $id ) ) {
            return new \WP_Error( 'not_found', __( 'Short link not found.', 'shortlink' ), [ 'status' => 404 ] );
        }
        return rest_ensure_response( [
            'id'     => $id,
            'total'  => Stats::clicks_for( $id ),
            'recent' => Stats::recent_clicks( $id, 100 ),
        ] );
    }
}
