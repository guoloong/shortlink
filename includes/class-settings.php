<?php
/**
 * Settings page for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . CPT::POST_TYPE,
            __( 'ShortLink Settings', 'shortlink' ),
            __( 'Settings', 'shortlink' ),
            'manage_options',
            'shortlink-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            'shortlink_settings_group',
            'shortlink_settings',
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize' ],
                'default'           => Helper::settings(),
            ]
        );
    }

    public function sanitize( $input ): array {
        $out = Helper::settings();

        $out['slug_length']     = max( 2, min( 32, (int) ( $input['slug_length'] ?? 6 ) ) );
        $out['slug_charset']    = in_array( $input['slug_charset'] ?? 'alnum', [ 'alnum', 'alnum_lower', 'alnum_no_lookalike' ], true )
            ? $input['slug_charset']
            : 'alnum';
        $out['allow_custom']    = ! empty( $input['allow_custom'] ) ? 1 : 0;
        $out['track_clicks']    = ! empty( $input['track_clicks'] ) ? 1 : 0;
        $out['track_useragent'] = ! empty( $input['track_useragent'] ) ? 1 : 0;
        $out['track_referrer']  = ! empty( $input['track_referrer'] ) ? 1 : 0;
        $out['redirect_status'] = in_array( (int) ( $input['redirect_status'] ?? 302 ), [ 301, 302, 303, 307 ], true )
            ? (int) $input['redirect_status']
            : 302;
        $out['default_prefix']  = preg_replace( '/[^a-z0-9_\-]/i', '', (string) ( $input['default_prefix'] ?? '' ) );

        // Slug prefix may affect rewrite rules — flush.
        if ( $out['default_prefix'] !== Helper::setting( 'default_prefix' ) ) {
            add_action( 'updated_option', function () {
                CPT::register_rewrite();
                flush_rewrite_rules();
            } );
        }

        return $out;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $s = Helper::settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'ShortLink Settings', 'shortlink' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'shortlink_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sl-slug-length"><?php esc_html_e( 'Slug length', 'shortlink' ); ?></label></th>
                        <td>
                            <input name="shortlink_settings[slug_length]" id="sl-slug-length" type="number" min="2" max="32" value="<?php echo esc_attr( $s['slug_length'] ); ?>" class="small-text" />
                            <p class="description"><?php esc_html_e( 'Number of characters in auto-generated short codes (2-32).', 'shortlink' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Slug character set', 'shortlink' ); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="radio" name="shortlink_settings[slug_charset]" value="alnum" <?php checked( $s['slug_charset'], 'alnum' ); ?>> <?php esc_html_e( 'Alphanumeric (mixed case + digits)', 'shortlink' ); ?></label><br>
                                <label><input type="radio" name="shortlink_settings[slug_charset]" value="alnum_lower" <?php checked( $s['slug_charset'], 'alnum_lower' ); ?>> <?php esc_html_e( 'Lowercase alphanumeric only', 'shortlink' ); ?></label><br>
                                <label><input type="radio" name="shortlink_settings[slug_charset]" value="alnum_no_lookalike" <?php checked( $s['slug_charset'], 'alnum_no_lookalike' ); ?>> <?php esc_html_e( 'No lookalikes (no 0/O, 1/l/I)', 'shortlink' ); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sl-default-prefix"><?php esc_html_e( 'URL prefix', 'shortlink' ); ?></label></th>
                        <td>
                            <code><?php echo esc_html( home_url( '/' ) ); ?></code>
                            <input name="shortlink_settings[default_prefix]" id="sl-default-prefix" type="text" value="<?php echo esc_attr( $s['default_prefix'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '(none)', 'shortlink' ); ?>" />
                            <code>/&lt;slug&gt;</code>
                            <p class="description"><?php esc_html_e( 'Optional prefix segment, e.g. "go" makes links like /go/abc123. Leave empty for root-level slugs.', 'shortlink' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Behavior', 'shortlink' ); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="shortlink_settings[allow_custom]" value="1" <?php checked( $s['allow_custom'], 1 ); ?>> <?php esc_html_e( 'Allow custom slugs (editors can pick their own)', 'shortlink' ); ?></label><br>
                                <label><input type="checkbox" name="shortlink_settings[track_clicks]" value="1" <?php checked( $s['track_clicks'], 1 ); ?>> <?php esc_html_e( 'Track clicks', 'shortlink' ); ?></label><br>
                                <label><input type="checkbox" name="shortlink_settings[track_useragent]" value="1" <?php checked( $s['track_useragent'], 1 ); ?>> <?php esc_html_e( 'Record user agent on click', 'shortlink' ); ?></label><br>
                                <label><input type="checkbox" name="shortlink_settings[track_referrer]" value="1" <?php checked( $s['track_referrer'], 1 ); ?>> <?php esc_html_e( 'Record referrer on click', 'shortlink' ); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sl-redirect-status"><?php esc_html_e( 'Redirect status', 'shortlink' ); ?></label></th>
                        <td>
                            <select name="shortlink_settings[redirect_status]" id="sl-redirect-status">
                                <option value="301" <?php selected( $s['redirect_status'], 301 ); ?>>301 Permanent</option>
                                <option value="302" <?php selected( $s['redirect_status'], 302 ); ?>>302 Temporary (default)</option>
                                <option value="303" <?php selected( $s['redirect_status'], 303 ); ?>>303 See Other</option>
                                <option value="307" <?php selected( $s['redirect_status'], 307 ); ?>>307 Temporary (preserve method)</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Stats overview', 'shortlink' ); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: 1: link count, 2: click count */
                    esc_html__( 'Total short links: %1$s — Total clicks: %2$s', 'shortlink' ),
                    '<strong>' . esc_html( Helper::total_links() ) . '</strong>',
                    '<strong>' . esc_html( Helper::total_clicks() ) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}
