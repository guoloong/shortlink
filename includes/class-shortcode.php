<?php
/**
 * [shorturl] shortcode for ShortLink.
 *
 * Usage:
 *   [shorturl id="123"]              -> renders the full short URL for link 123
 *   [shorturl slug="abc"]            -> renders the short URL for slug "abc"
 *   [shorturl id="123" target="_blank" rel="nofollow"]text[/shorturl]
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shortcode {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'shorturl', [ $this, 'render' ] );
        add_shortcode( 'shortlink', [ $this, 'render' ] );
    }

    /**
     * @param array  $atts    Shortcode attributes.
     * @param string $content Inner content (optional).
     */
    public function render( $atts, $content = '' ): string {
        $atts = shortcode_atts( [
            'id'     => 0,
            'slug'   => '',
            'target' => '',
            'rel'    => '',
            'class'  => '',
        ], $atts, 'shorturl' );

        $slug = '';
        if ( ! empty( $atts['id'] ) ) {
            $post = get_post( (int) $atts['id'] );
            if ( $post && CPT::POST_TYPE === $post->post_type ) {
                $slug = $post->post_name;
            }
        } elseif ( ! empty( $atts['slug'] ) ) {
            $slug = Helper::sanitize_custom_slug( $atts['slug'] );
        }

        if ( '' === $slug ) {
            return $content ? wp_kses_post( $content ) : '';
        }

        $url = Helper::build_short_url( $slug, true );
        $anchor_text = '' !== trim( (string) $content ) ? wp_kses_post( $content ) : $url;

        $attrs = [ 'href' => esc_url( $url ) ];
        if ( ! empty( $atts['target'] ) ) {
            $attrs['target'] = esc_attr( $atts['target'] );
        }
        if ( ! empty( $atts['rel'] ) ) {
            $attrs['rel'] = esc_attr( $atts['rel'] );
        } else {
            $attrs['rel'] = 'nofollow noopener noreferrer';
        }
        if ( ! empty( $atts['class'] ) ) {
            $attrs['class'] = esc_attr( $atts['class'] );
        }

        $html = '<a';
        foreach ( $attrs as $k => $v ) {
            $html .= ' ' . $k . '="' . $v . '"';
        }
        $html .= '>' . $anchor_text . '</a>';

        return $html;
    }
}
