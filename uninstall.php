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

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping our own tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}socialrelaycvi_shares" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}socialrelaycvi_log" );
// phpcs:enable

foreach ( array( 'socialrelaycvi_instance_url', 'socialrelaycvi_api_base', 'socialrelaycvi_api_key', 'socialrelaycvi_profiles', 'socialrelaycvi_db_version', 'socialrelaycvi_delete_data_on_uninstall' ) as $socialrelaycvi_option ) {
	delete_option( $socialrelaycvi_option );
}

delete_post_meta_by_key( '_socialrelaycvi_enabled' );
delete_post_meta_by_key( '_socialrelaycvi_message' );
delete_post_meta_by_key( '_socialrelaycvi_reshare' );
