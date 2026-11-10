<?php
/**
 * Uninstall: removes data only when the site owner asked for it.
 *
 * Define SOCIALRELAYCVI_DELETE_DATA as true in wp-config.php (or enable the
 * option in Settings → Advanced) to drop tables and options. On multisite
 * the cleanup runs once per site. Nothing is ever deleted in Postiz.
 *
 * @package SocialRelayCvi
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes this plugin's data from the current site, if the owner opted in.
 *
 * A closure keeps the global namespace clean and is safe to load twice.
 */
$socialrelaycvi_cleanup_site = static function (): void {
	global $wpdb;

	$delete = ( defined( 'SOCIALRELAYCVI_DELETE_DATA' ) && SOCIALRELAYCVI_DELETE_DATA )
		|| (bool) get_option( 'socialrelaycvi_delete_data_on_uninstall', false );

	if ( ! $delete ) {
		return;
	}

	// Per-site prefix: computed after switch_to_blog().
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Dropping our own tables.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'socialrelaycvi_shares' ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'socialrelaycvi_log' ) );
	// phpcs:enable

	foreach ( array( 'socialrelaycvi_instance_url', 'socialrelaycvi_api_base', 'socialrelaycvi_api_key', 'socialrelaycvi_profiles', 'socialrelaycvi_db_version', 'socialrelaycvi_delete_data_on_uninstall', 'socialrelaycvi_webhook_secret', 'socialrelaycvi_webhook_last', 'socialrelaycvi_webhook_history', 'socialrelaycvi_channels_last', 'socialrelaycvi_recurring_checked', 'socialrelaycvi_post_requests', 'socialrelaycvi_error_email', 'socialrelaycvi_error_email_sent', 'socialrelaycvi_error_email_checked', 'socialrelaycvi_named_sync', 'socialrelaycvi_named_sync_lock' ) as $option ) {
		delete_option( $option );
	}

	foreach ( array( '_socialrelaycvi_enabled', '_socialrelaycvi_message', '_socialrelaycvi_reshare', '_socialrelaycvi_overrides', '_socialrelaycvi_channels', '_socialrelaycvi_mode', '_socialrelaycvi_publish_at', '_socialrelaycvi_only', '_socialrelaycvi_images', '_socialrelaycvi_channels_by_admin' ) as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	delete_transient( 'socialrelaycvi_webhook_check' );
	delete_metadata( 'user', 0, 'socialrelaycvi_errors_seen', '', true );

	// Cached channel lists and share locks.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_socialrelaycvi_' ) . '%', $wpdb->esc_like( '_transient_timeout_socialrelaycvi_' ) . '%', $wpdb->esc_like( 'socialrelaycvi_share_lock_' ) . '%', $wpdb->esc_like( 'socialrelaycvi_lock_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing our own transients.
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $socialrelaycvi_site_id ) {
		switch_to_blog( (int) $socialrelaycvi_site_id );
		$socialrelaycvi_cleanup_site();
		restore_current_blog();
	}
} else {
	$socialrelaycvi_cleanup_site();
}
