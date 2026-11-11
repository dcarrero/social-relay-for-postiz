<?php
/**
 * Activation, deactivation and schema upgrades.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Lifecycle;

use SocialRelayCvi\Queue\Queue;

/**
 * Creates and upgrades the plugin tables.
 */
final class Installer {

	public const DB_VERSION        = 3;
	public const OPTION_DB_VERSION = 'socialrelaycvi_db_version';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::install_schema();
	}

	/**
	 * Deactivation hook: stops scheduled jobs, keeps data.
	 */
	public static function deactivate(): void {
		Queue::unschedule_all();
	}

	/**
	 * Runs on every load; installs or upgrades the schema when the stored version is older.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION ) {
			self::install_schema();
		}
	}

	/**
	 * Table names.
	 *
	 * @return array{shares: string, log: string}
	 */
	public static function tables(): array {
		global $wpdb;

		return array(
			'shares' => $wpdb->prefix . 'socialrelaycvi_shares',
			'log'    => $wpdb->prefix . 'socialrelaycvi_log',
		);
	}

	/**
	 * Creates or updates the tables with dbDelta.
	 */
	private static function install_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables  = self::tables();
		$charset = $wpdb->get_charset_collate();

		// One row per post × channel × batch.
		dbDelta(
			"CREATE TABLE {$tables['shares']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				integration_id varchar(64) NOT NULL,
				identifier varchar(40) NOT NULL DEFAULT '',
				batch varchar(80) NOT NULL,
				postiz_post_id varchar(64) NOT NULL DEFAULT '',
				type varchar(20) NOT NULL DEFAULT 'now',
				status varchar(20) NOT NULL DEFAULT 'pending',
				publish_at datetime DEFAULT NULL,
				release_url text NULL,
				error text NULL,
				content_hash char(40) NOT NULL DEFAULT '',
				source varchar(20) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY post_integration (post_id,integration_id,id),
				KEY batch (batch),
				KEY postiz_post_id (postiz_post_id),
				KEY status_publish (status,publish_at),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$tables['log']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				level varchar(10) NOT NULL DEFAULT 'info',
				action varchar(40) NOT NULL DEFAULT '',
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				integration_id varchar(64) NOT NULL DEFAULT '',
				message text NOT NULL,
				context longtext NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY post_id (post_id),
				KEY level (level)
			) {$charset};"
		);

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}
}
