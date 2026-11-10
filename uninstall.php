<?php
/**
 * Uninstall: removes data only when the site owner asked for it.
 *
 * Define SOCIALRELAYCVI_DELETE_DATA as true in wp-config.php (or enable the
 * option in Settings, coming in 0.2) to drop tables and options. Nothing is
 * ever deleted in Postiz.
 *
 * @package SocialRelayCvi
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$socialrelaycvi_delete = ( defined( 'SOCIALRELAYCVI_DELETE_DATA' ) && SOCIALRELAYCVI_DELETE_DATA )
	|| (bool) get_option( 'socialrelaycvi_delete_data_on_uninstall', false );

if ( ! $socialrelaycvi_delete ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Dropping our own tables.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'socialrelaycvi_shares' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'socialrelaycvi_log' ) );
// phpcs:enable

foreach ( array( 'socialrelaycvi_instance_url', 'socialrelaycvi_api_base', 'socialrelaycvi_api_key', 'socialrelaycvi_profiles', 'socialrelaycvi_db_version', 'socialrelaycvi_delete_data_on_uninstall', 'socialrelaycvi_webhook_secret', 'socialrelaycvi_webhook_last', 'socialrelaycvi_post_requests', 'socialrelaycvi_error_email', 'socialrelaycvi_error_email_sent', 'socialrelaycvi_error_email_checked' ) as $socialrelaycvi_option ) {
	delete_option( $socialrelaycvi_option );
}

delete_post_meta_by_key( '_socialrelaycvi_enabled' );
delete_post_meta_by_key( '_socialrelaycvi_message' );
delete_post_meta_by_key( '_socialrelaycvi_reshare' );
delete_post_meta_by_key( '_socialrelaycvi_overrides' );
delete_post_meta_by_key( '_socialrelaycvi_channels' );
delete_post_meta_by_key( '_socialrelaycvi_mode' );
delete_post_meta_by_key( '_socialrelaycvi_publish_at' );
delete_post_meta_by_key( '_socialrelaycvi_only' );
delete_metadata( 'user', 0, 'socialrelaycvi_errors_seen', '', true );

// Cached channel lists.
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_socialrelaycvi_' ) . '%', $wpdb->esc_like( '_transient_timeout_socialrelaycvi_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing our own transients.
