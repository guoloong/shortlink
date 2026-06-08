<?php
/**
 * Admin UI for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink\Admin;

use ShortLink\CPT;
use ShortLink\Helper;
use ShortLink\Stats;
use ShortLink\QRCode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );
        add_action( 'save_post_' . CPT::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_shortlink_create', [ $this, 'ajax_create' ] );
        add_action( 'wp_ajax_shortlink_stats', [ $this, 'ajax_stats' ] );
        add_action( 'before_delete_post', [ $this, 'cleanup_clicks' ] );
        add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
        add_filter( 'display_post_states', [ $this, 'display_states' ], 10, 2 );

        // Custom title placeholder.
        add_filter( 'enter_title_here', [ $this, 'title_placeholder' ], 10, 2 );
    }

    public function title_placeholder( $placeholder, $post ) {
        if ( $post && CPT::POST_TYPE === $post->post_type ) {
            return __( 'Internal label (e.g. "Summer campaign")', 'shortlink' );
        }
        return $placeholder;
    }

    public function row_actions( $actions, $post ) {
        if ( CPT::POST_TYPE !== $post->post_type ) {
            return $actions;
        }
        $slug = $post->post_name;
        $url  = Helper::build_short_url( $slug, true );

        // Always put these first so the user can't miss them.
        $visit = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Visit', 'shortlink' ) . '</a>';
        $copy  = '<a href="#" class="sl-copy" data-url="' . esc_attr( $url ) . '">' . esc_html__( 'Copy URL', 'shortlink' ) . '</a>';

        // Drop WP's default "View" permalink for this CPT — it doesn't make sense here.
        unset( $actions['view'] );

        return array_merge( [
            'shortlink_visit' => $visit,
            'shortlink_copy'  => $copy,
        ], $actions );
    }

    public function display_states( $states, $post ) {
        if ( CPT::POST_TYPE !== $post->post_type ) {
            return $states;
        }
        $clicks = Stats::clicks_for( (int) $post->ID );
        $states['shortlink_clicks'] = sprintf(
            /* translators: %s: click count */
            esc_html__( '%s clicks', 'shortlink' ),
            number_format_i18n( $clicks )
        );
        return $states;
    }

    public function cleanup_clicks( $post_id ): void {
        if ( get_post_type( $post_id ) === CPT::POST_TYPE ) {
            Stats::delete_for_link( (int) $post_id );
        }
    }

    public function enqueue( $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        $is_shortlink_screen = ( CPT::POST_TYPE === $screen->post_type );
        if ( ! $is_shortlink_screen && 'shortlink_page_shortlink-settings' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'shortlink-admin',
            SHORTLINK_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SHORTLINK_VERSION
        );
        wp_enqueue_script(
            'shortlink-admin',
            SHORTLINK_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            SHORTLINK_VERSION,
            true
        );
        wp_localize_script( 'shortlink-admin', 'ShortLink', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'shortlink_admin' ),
            'i18n'    => [
                'copied'     => __( 'Copied!', 'shortlink' ),
                'copyFailed' => __( 'Press Ctrl+C to copy.', 'shortlink' ),
                'loading'    => __( 'Loading…', 'shortlink' ),
                'error'      => __( 'Something went wrong.', 'shortlink' ),
            ],
        ] );
    }

    /* ------------------------------------------------------------------ */
    /*  Meta boxes                                                        */
    /* ------------------------------------------------------------------ */

    public function meta_boxes(): void {
        add_meta_box(
            'shortlink-target',
            __( 'Short Link Details', 'shortlink' ),
            [ $this, 'render_details_box' ],
            CPT::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'shortlink-stats',
            __( 'Click Stats', 'shortlink' ),
            [ $this, 'render_stats_box' ],
            CPT::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_details_box( $post ): void {
        wp_nonce_field( 'shortlink_save', '_sl_nonce' );
        $target   = get_post_meta( $post->ID, '_sl_target', true );
        $custom   = get_post_meta( $post->ID, '_sl_custom_slug', true );
        $auto     = get_post_meta( $post->ID, '_sl_auto_slug', true );
        $notes    = get_post_meta( $post->ID, '_sl_notes', true );
        $short    = $post->post_name ? Helper::build_short_url( $post->post_name, false ) : '';
        $abs      = $post->post_name ? Helper::build_short_url( $post->post_name, true )  : '';
        $qr_svg   = $abs ? QRCode::instance()->svg( $abs, 180 ) : '';
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="sl-target"><?php esc_html_e( 'Target URL', 'shortlink' ); ?></label></th>
                <td>
                    <input type="url" id="sl-target" name="_sl_target" value="<?php echo esc_attr( $target ); ?>" class="large-text" placeholder="https://example.com/some/long/url" required />
                    <p class="description"><?php esc_html_e( 'The full URL visitors will be sent to. Must start with http:// or https://', 'shortlink' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sl-custom-slug"><?php esc_html_e( 'Short code', 'shortlink' ); ?></label></th>
                <td>
                    <code><?php echo esc_html( home_url( '/' ) . ( Helper::setting( 'default_prefix' ) ? Helper::setting( 'default_prefix' ) . '/' : '' ) ); ?></code>
                    <input type="text" id="sl-custom-slug" name="_sl_custom_slug" value="<?php echo esc_attr( $custom ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $auto ?: __( 'auto-generated', 'shortlink' ) ); ?>" <?php disabled( (int) Helper::setting( 'allow_custom', 1 ) === 0 ); ?> />
                    <button type="button" class="button sl-regen"><?php esc_html_e( 'Regenerate', 'shortlink' ); ?></button>
                    <p class="description"><?php esc_html_e( 'Leave blank to auto-generate. Saved on Publish/Update.', 'shortlink' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sl-notes"><?php esc_html_e( 'Notes', 'shortlink' ); ?></label></th>
                <td>
                    <textarea id="sl-notes" name="_sl_notes" rows="3" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Internal note — never shown to visitors.', 'shortlink' ); ?></p>
                </td>
            </tr>
            <?php if ( $abs ) : ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Preview', 'shortlink' ); ?></th>
                <td>
                    <p>
                        <strong><?php esc_html_e( 'Public URL:', 'shortlink' ); ?></strong>
                        <a href="<?php echo esc_url( $abs ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $abs ); ?></code></a>
                        <button type="button" class="button button-small sl-copy" data-url="<?php echo esc_attr( $abs ); ?>"><?php esc_html_e( 'Copy', 'shortlink' ); ?></button>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Relative path:', 'shortlink' ); ?></strong>
                        <code>/<?php echo esc_html( $short ); ?></code>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Shortcode:', 'shortlink' ); ?></strong>
                        <code>[shorturl id="<?php echo (int) $post->ID; ?>"]</code>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'QR code', 'shortlink' ); ?></th>
                <td>
                    <div class="sl-qr"><?php echo $qr_svg; ?></div>
                    <button type="button" class="button" id="sl-download-qr"><?php esc_html_e( 'Download SVG', 'shortlink' ); ?></button>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function render_stats_box( $post ): void {
        $clicks = Stats::clicks_for( (int) $post->ID );
        ?>
        <div class="sl-stats-box">
            <p>
                <strong style="font-size: 24px;"><?php echo esc_html( number_format_i18n( $clicks ) ); ?></strong><br>
                <span class="description"><?php esc_html_e( 'Total clicks', 'shortlink' ); ?></span>
            </p>
            <p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=shortlink-stats&link_id=' . (int) $post->ID ) ); ?>" class="button"><?php esc_html_e( 'View recent clicks', 'shortlink' ); ?></a></p>
            <?php if ( (int) Helper::setting( 'track_clicks', 1 ) === 0 ) : ?>
                <p class="description" style="color:#a00;"><?php esc_html_e( 'Click tracking is disabled in Settings.', 'shortlink' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  Save                                                               */
    /* ------------------------------------------------------------------ */

    public function save_post( $post_id, $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['_sl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sl_nonce'] ) ), 'shortlink_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Target URL.
        $target = isset( $_POST['_sl_target'] ) ? esc_url_raw( wp_unslash( $_POST['_sl_target'] ) ) : '';
        // Clean up a stray trailing "?" (common copy-paste artifact).
        $target = rtrim( $target, '?' );
        if ( $target ) {
            update_post_meta( $post_id, '_sl_target', $target );
        } else {
            delete_post_meta( $post_id, '_sl_target' );
        }

        // Notes.
        $notes = isset( $_POST['_sl_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_sl_notes'] ) ) : '';
        if ( $notes ) {
            update_post_meta( $post_id, '_sl_notes', $notes );
        } else {
            delete_post_meta( $post_id, '_sl_notes' );
        }

        // Custom slug.
        $custom_raw = isset( $_POST['_sl_custom_slug'] ) ? trim( (string) wp_unslash( $_POST['_sl_custom_slug'] ) ) : '';
        if ( ! (int) Helper::setting( 'allow_custom', 1 ) ) {
            $custom_raw = '';
        }
        $custom = Helper::sanitize_custom_slug( $custom_raw );

        $current_slug = $post->post_name;
        $desired_slug = $current_slug;

        if ( '' !== $custom && $custom !== $current_slug ) {
            // Check collision with OTHER posts only.
            $collides = false;
            $existing = get_posts( [
                'name'           => $custom,
                'post_type'      => CPT::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post__not_in'   => [ $post_id ],
            ] );
            if ( ! empty( $existing ) ) {
                $collides = true;
            } elseif ( in_array( $custom, [ 'page', 'comments', 'feed', 'wp-admin', 'wp-login.php', 'wp-content', 'wp-includes' ], true ) ) {
                $collides = true;
            }
            $desired_slug = $collides ? Helper::unique_slug() : $custom;
        } elseif ( '' === $current_slug ) {
            $desired_slug = Helper::unique_slug();
        }

        if ( $desired_slug !== $current_slug ) {
            remove_action( 'save_post_' . CPT::POST_TYPE, [ $this, 'save_post' ], 10 );
            wp_update_post( [
                'ID'        => $post_id,
                'post_name' => $desired_slug,
            ] );
            add_action( 'save_post_' . CPT::POST_TYPE, [ $this, 'save_post' ], 10, 2 );
        }

        update_post_meta( $post_id, '_sl_custom_slug', $custom );
        update_post_meta( $post_id, '_sl_auto_slug', $current_slug );
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX                                                               */
    /* ------------------------------------------------------------------ */

    public function ajax_create(): void {
        check_ajax_referer( 'shortlink_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Forbidden.', 'shortlink' ) ], 403 );
        }
        $target = isset( $_POST['target'] ) ? esc_url_raw( wp_unslash( $_POST['target'] ) ) : '';
        $custom = isset( $_POST['slug'] )   ? Helper::sanitize_custom_slug( (string) wp_unslash( $_POST['slug'] ) ) : '';
        $title  = isset( $_POST['title'] )  ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

        if ( '' === $target ) {
            wp_send_json_error( [ 'message' => __( 'Target URL is required.', 'shortlink' ) ], 400 );
        }

        $slug = $custom ?: Helper::unique_slug();

        $post_id = wp_insert_post( [
            'post_type'    => CPT::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $title ?: $slug,
            'post_name'    => $slug,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => $post_id->get_error_message() ], 500 );
        }

        update_post_meta( $post_id, '_sl_target', $target );
        update_post_meta( $post_id, '_sl_custom_slug', $custom );
        update_post_meta( $post_id, '_sl_auto_slug', $slug );

        wp_send_json_success( [
            'id'        => $post_id,
            'slug'      => $slug,
            'short_url' => Helper::build_short_url( $slug, true ),
            'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
        ] );
    }

    public function ajax_stats(): void {
        check_ajax_referer( 'shortlink_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Forbidden.', 'shortlink' ) ], 403 );
        }
        $link_id = isset( $_GET['link_id'] ) ? (int) $_GET['link_id'] : 0;
        if ( ! $link_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid link id.', 'shortlink' ) ], 400 );
        }
        $rows = Stats::recent_clicks( $link_id, 50 );
        $total = Stats::clicks_for( $link_id );
        wp_send_json_success( [
            'total' => $total,
            'rows'  => $rows,
        ] );
    }
}
