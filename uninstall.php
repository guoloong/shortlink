<?php
/**
 * ShortLink uninstall.
 *
 * Runs only when the user deletes the plugin via WP admin (full uninstall).
 * Removes plugin options and the clicks table. Leaves shortlink posts alone
 * unless the user explicitly opts in via the constant below.
 *
 * @package ShortLink
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

delete_option( 'shortlink_settings' );

// Drop clicks table.
$table = $wpdb->prefix . 'shortlink_clicks';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// If site owner opts in, also delete all shortlink posts and their meta.
if ( defined( 'SHORTLINK_FULL_ERASE' ) && SHORTLINK_FULL_ERASE ) {
    $ids = get_posts( [
        'post_type'      => 'shortlink',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );
    foreach ( $ids as $id ) {
        wp_delete_post( (int) $id, true );
    }
}
