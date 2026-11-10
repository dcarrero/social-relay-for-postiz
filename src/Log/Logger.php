<?php
/**
 * Activity log.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Log;

use SocialRelayCvi\Lifecycle\Installer;
use SocialRelayCvi\Module;
use SocialRelayCvi\Queue\Queue;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table, no core API for it.

/**
 * Writes to the socialrelaycvi_log table and purges old rows daily.
 *
 * Never log the API key: the client does not put it in any error message or context.
 */
final class Logger implements Module {

	public const RETENTION_DAYS = 90;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( Queue::HOOK_CLEANUP, array( $this, 'purge' ) );
	}

	/**
	 * Info entry.
	 *
	 * @param string               $action         Short action name, e.g. share, upload, test.
	 * @param string               $message        Human-readable message.
	 * @param int                  $post_id        Related post, 0 if none.
	 * @param string               $integration_id Related channel, '' if none.
	 * @param array<string, mixed> $context        Extra data, stored as JSON.
	 */
	public function info( string $action, string $message, int $post_id = 0, string $integration_id = '', array $context = array() ): void {
		$this->write( 'info', $action, $message, $post_id, $integration_id, $context );
	}

	/**
	 * Error entry.
	 *
	 * @param string               $action         Short action name.
	 * @param string               $message        Human-readable message.
	 * @param int                  $post_id        Related post, 0 if none.
	 * @param string               $integration_id Related channel, '' if none.
	 * @param array<string, mixed> $context        Extra data, stored as JSON.
	 */
	public function error( string $action, string $message, int $post_id = 0, string $integration_id = '', array $context = array() ): void {
		$this->write( 'error', $action, $message, $post_id, $integration_id, $context );
	}

	/**
	 * Latest entries.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function latest( int $limit = 100 ): array {
		global $wpdb;

		$table = Installer::tables()['log'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Deletes entries older than the retention period.
	 */
	public function purge(): void {
		global $wpdb;

		/**
		 * Filters how many days log entries are kept.
		 *
		 * @param int $days Retention in days.
		 */
		$days  = max( 1, (int) apply_filters( 'socialrelaycvi_log_retention_days', self::RETENTION_DAYS ) );
		$table = Installer::tables()['log'];

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Inserts a row.
	 *
	 * @param string               $level          info or error.
	 * @param string               $action         Short action name.
	 * @param string               $message        Message.
	 * @param int                  $post_id        Post id.
	 * @param string               $integration_id Channel id.
	 * @param array<string, mixed> $context        Extra data.
	 */
	private function write( string $level, string $action, string $message, int $post_id, string $integration_id, array $context ): void {
		global $wpdb;

		$wpdb->insert(
			Installer::tables()['log'],
			array(
				'created_at'     => current_time( 'mysql', true ),
				'level'          => $level,
				'action'         => $action,
				'post_id'        => $post_id,
				'integration_id' => $integration_id,
				'message'        => $message,
				'context'        => array() === $context ? null : wp_json_encode( $context ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}
}
