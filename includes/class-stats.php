<?php
/**
 * Click tracking for ShortLink.
 *
 * @package ShortLink
 */

namespace ShortLink;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Stats {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'shortlink_clicks';
    }

    public static function install(): void {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            link_id BIGINT(20) UNSIGNED NOT NULL,
            clicked_at DATETIME NOT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            referer VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY link_id (link_id),
            KEY clicked_at (clicked_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private static function ensure_table(): bool {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        return $exists === $table;
    }

    public static function record_click( int $link_id ): void {
        self::ensure_table();
        global $wpdb;
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ua = '';
        if ( (int) Helper::setting( 'track_useragent', 1 ) ) {
            $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
        }
        $ref = '';
        if ( (int) Helper::setting( 'track_referrer', 1 ) ) {
            $ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
        }
        $wpdb->insert(
            self::table_name(),
            [
                'link_id'     => $link_id,
                'clicked_at'  => current_time( 'mysql' ),
                'ip'          => $ip,
                'user_agent'  => $ua,
                'referer'     => $ref,
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    public static function clicks_for( int $link_id ): int {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists !== $table ) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE link_id = %d", $link_id ) );
        return $count;
    }

    /**
     * Last N clicks (newest first).
     */
    public static function recent_clicks( int $link_id, int $limit = 20 ): array {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists !== $table ) {
            return [];
        }
        $limit  = max( 1, min( 200, $limit ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, clicked_at, ip, user_agent, referer FROM {$table} WHERE link_id = %d ORDER BY id DESC LIMIT %d",
                $link_id, $limit
            ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : [];
    }

    public static function delete_for_link( int $link_id ): void {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists !== $table ) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete( $table, [ 'link_id' => $link_id ], [ '%d' ] );
    }
}
