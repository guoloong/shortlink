<?php
/**
 * QR Code support for ShortLink.
 *
 * Strategy: build a URL pointing to a public QR service (api.qrserver.com by
 * default) and render it as an <img>. Developers can override via the
 * `shortlink_qr_url` filter to plug in their own generator (server-side
 * `ShortLink\QRCode::svg()` is provided for advanced cases).
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QRCode {

    private const DEFAULT_SERVICE = 'https://api.qrserver.com/v1/create-qr-code/';

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_shortlink_qr', [ $this, 'ajax_render' ] );
    }

    /**
     * Build a QR image URL for the given payload.
     */
    public function url( string $payload, int $size = 220 ): string {
        $payload = (string) apply_filters( 'shortlink_qr_payload', $payload );
        $size    = max( 80, min( 1000, (int) $size ) );

        $url = self::DEFAULT_SERVICE . '?' . http_build_query( [
            'size' => $size . 'x' . $size,
            'data' => $payload,
            'format' => 'png',
            'margin' => 2,
        ] );

        return (string) apply_filters( 'shortlink_qr_url', $url, $payload, $size );
    }

    /**
     * Render an <img> tag pointing at the QR service.
     */
    public function img( string $payload, int $size = 220, array $attrs = [] ): string {
        $src = $this->url( $payload, $size );
        $alt = isset( $attrs['alt'] ) ? (string) $attrs['alt'] : $payload;
        $default = [
            'src'   => $src,
            'alt'   => $alt,
            'width' => $size,
            'height' => $size,
            'class' => 'sl-qr-img',
        ];
        $merged = array_merge( $default, $attrs );
        $html = '<img';
        foreach ( $merged as $k => $v ) {
            $html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
        }
        $html .= ' />';
        return $html;
    }

    /**
     * Backwards-compat: keep an empty `svg()` method so other code calling it
     * does not fatal. Real SVG generation is not provided by this plugin
     * (developers can hook `shortlink_qr_url` to provide their own service).
     */
    public function svg( string $payload, int $size = 220 ): string {
        return $this->img( $payload, $size );
    }

    public function ajax_render(): void {
        check_ajax_referer( 'shortlink_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Forbidden.', 'shortlink' ) ], 403 );
        }
        $url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
        if ( '' === $url ) {
            wp_send_json_error( [ 'message' => __( 'Missing URL.', 'shortlink' ) ], 400 );
        }
        wp_send_json_success( [
            'img' => $this->img( $url, 220 ),
            'src' => $this->url( $url, 220 ),
        ] );
    }
}
