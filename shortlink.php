<?php
/**
 * Plugin Name:       ShortLink WP URL Shortener
 * Plugin URI:        https://example.com/shortlink
 * Description:       A self-hosted URL shortener with click tracking, QR codes, REST API, and shortcode support.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            You
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shortlink
 * Domain Path:       /languages
 *
 * @package ShortLink
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SHORTLINK_VERSION',    '1.0.0' );
define( 'SHORTLINK_PLUGIN_FILE', __FILE__ );
define( 'SHORTLINK_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SHORTLINK_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SHORTLINK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4-ish autoloader for the ShortLink\ namespace.
 *
 * Mapping:
 *   ShortLink\Foo\Bar           -> includes/foo/class-bar.php
 *   ShortLink\Foo               -> includes/class-foo.php
 */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'ShortLink\\' ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( 'ShortLink\\' ) );
    $parts    = explode( '\\', $relative );
    $file     = array_pop( $parts );
    $kebab    = strtolower( $file );

    if ( empty( $parts ) ) {
        $path = SHORTLINK_PLUGIN_DIR . 'includes/class-' . $kebab . '.php';
    } else {
        $dir  = strtolower( implode( '/', $parts ) );
        $path = SHORTLINK_PLUGIN_DIR . 'includes/' . $dir . '/class-' . $kebab . '.php';
    }

    if ( file_exists( $path ) ) {
        require_once $path;
    }
} );

/**
 * Boot the plugin.
 */
final class ShortLink_Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        \ShortLink\CPT::instance();
        \ShortLink\Redirect::instance();
        \ShortLink\Admin\Admin::instance();
        \ShortLink\Settings::instance();
        \ShortLink\REST::instance();
        \ShortLink\Shortcode::instance();
        \ShortLink\QRCode::instance();

        register_activation_hook( __FILE__, [ __CLASS__, 'on_activate' ] );
        register_deactivation_hook( __FILE__, [ __CLASS__, 'on_deactivate' ] );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'shortlink', false, dirname( SHORTLINK_PLUGIN_BASENAME ) . '/languages' );
    }

    public static function on_activate(): void {
        // Make sure the CPT is registered before flushing.
        \ShortLink\CPT::instance()->register();
        \ShortLink\CPT::register_rewrite();
        flush_rewrite_rules();

        // Install the click tracking table.
        \ShortLink\Stats::install();

        // Default options.
        if ( false === get_option( 'shortlink_settings' ) ) {
            update_option( 'shortlink_settings', [
                'slug_length'      => 6,
                'slug_charset'     => 'alnum',     // alnum | alnum_lower | alnum_no_lookalike
                'allow_custom'     => 1,
                'track_clicks'     => 1,
                'track_useragent'  => 1,
                'track_referrer'   => 1,
                'redirect_status'  => 302,
                'default_prefix'   => '',          // e.g. "go" -> /go/abc123
            ] );
        }
    }

    public static function on_deactivate(): void {
        flush_rewrite_rules();
    }
}

ShortLink_Plugin::instance();
