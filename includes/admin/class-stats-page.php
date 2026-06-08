<?php
/**
 * Per-link click stats page for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink\Admin;

use ShortLink\CPT;
use ShortLink\Stats;
use ShortLink\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Stats_Page {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
    }

    public static function add_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . CPT::POST_TYPE,
            __( 'Click Stats', 'shortlink' ),
            __( 'Stats', 'shortlink' ),
            'manage_options',
            'shortlink-stats',
            [ __CLASS__, 'render' ]
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $link_id = isset( $_GET['link_id'] ) ? (int) $_GET['link_id'] : 0;
        $post    = $link_id ? get_post( $link_id ) : null;

        if ( ! $post || CPT::POST_TYPE !== $post->post_type ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Click Stats', 'shortlink' ) . '</h1>';
            echo '<p>' . esc_html__( 'Pick a link to view its click history.', 'shortlink' ) . '</p>';
            $links = get_posts( [
                'post_type'      => CPT::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ] );
            if ( empty( $links ) ) {
                echo '<p>' . esc_html__( 'No short links yet.', 'shortlink' ) . '</p>';
            } else {
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'shortlink' ) . '</th><th>' . esc_html__( 'Short URL', 'shortlink' ) . '</th><th>' . esc_html__( 'Clicks', 'shortlink' ) . '</th><th></th></tr></thead><tbody>';
                foreach ( $links as $l ) {
                    $short = Helper::build_short_url( $l->post_name, false );
                    $clicks = Stats::clicks_for( (int) $l->ID );
                    printf(
                        '<tr><td>%s</td><td><code>/%s</code></td><td>%d</td><td><a class="button" href="%s">%s</a></td></tr>',
                        esc_html( $l->post_title ),
                        esc_html( $short ),
                        (int) $clicks,
                        esc_url( admin_url( 'edit.php?post_type=' . CPT::POST_TYPE . '&page=shortlink-stats&link_id=' . (int) $l->ID ) ),
                        esc_html__( 'View', 'shortlink' )
                    );
                }
                echo '</tbody></table>';
            }
            echo '</div>';
            return;
        }

        $rows  = Stats::recent_clicks( (int) $post->ID, 200 );
        $total = Stats::clicks_for( (int) $post->ID );
        $abs   = Helper::build_short_url( $post->post_name, true );
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Click Stats', 'shortlink' ); ?>
                <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit link', 'shortlink' ); ?></a>
            </h1>
            <p>
                <strong><?php esc_html_e( 'Short URL:', 'shortlink' ); ?></strong>
                <a href="<?php echo esc_url( $abs ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $abs ); ?></code></a>
                <br>
                <strong><?php esc_html_e( 'Target:', 'shortlink' ); ?></strong>
                <code><?php echo esc_html( (string) get_post_meta( $post->ID, '_sl_target', true ) ); ?></code>
                <br>
                <strong><?php esc_html_e( 'Total clicks:', 'shortlink' ); ?></strong>
                <?php echo esc_html( number_format_i18n( $total ) ); ?>
            </p>

            <?php if ( empty( $rows ) ) : ?>
                <p><?php esc_html_e( 'No clicks recorded yet.', 'shortlink' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'When', 'shortlink' ); ?></th>
                            <th><?php esc_html_e( 'IP', 'shortlink' ); ?></th>
                            <th><?php esc_html_e( 'User agent', 'shortlink' ); ?></th>
                            <th><?php esc_html_e( 'Referrer', 'shortlink' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( $r['clicked_at'] ); ?></td>
                                <td><code><?php echo esc_html( $r['ip'] ); ?></code></td>
                                <td><span class="sl-ua" title="<?php echo esc_attr( $r['user_agent'] ); ?>"><?php echo esc_html( wp_trim_words( (string) $r['user_agent'], 8, '…' ) ); ?></span></td>
                                <td>
                                    <?php if ( ! empty( $r['referer'] ) ) : ?>
                                        <a href="<?php echo esc_url( $r['referer'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_trim_words( $r['referer'], 6, '…' ) ); ?></a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e( 'Showing the most recent 200 clicks. Older clicks are kept in the database and accessible via the REST API.', 'shortlink' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

Stats_Page::init();
