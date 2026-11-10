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
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Page of entries with optional filters.
	 *
	 * @param int    $page     Page number, from 1.
	 * @param int    $per_page Rows per page.
	 * @param string $level    '' for all, info or error.
	 * @param string $search   Text to look for in the message.
	 * @param int    $post_id  0 for all posts.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function query( int $page = 1, int $per_page = 20, string $level = '', string $search = '', int $post_id = 0 ): array {
		global $wpdb;

		$table  = Installer::tables()['log'];
		$level  = in_array( $level, array( 'info', 'error' ), true ) ? $level : '';
		$like   = '' === $search ? '' : '%' . $wpdb->esc_like( $search ) . '%';
		$limit  = max( 1, $per_page );
		$offset = ( max( 1, $page ) - 1 ) * $limit;

		// Static SQL: an empty filter value switches its condition off.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE ( %s = '' OR level = %s ) AND ( %s = '' OR message LIKE %s ) AND ( %d = 0 OR post_id = %d )",
				$table,
				$level,
				$level,
				$like,
				$like,
				$post_id,
				$post_id
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE ( %s = '' OR level = %s ) AND ( %s = '' OR message LIKE %s ) AND ( %d = 0 OR post_id = %d ) ORDER BY id DESC LIMIT %d OFFSET %d",
				$table,
				$level,
				$level,
				$like,
				$like,
				$post_id,
				$post_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return array(
			'items' => is_array( $rows ) ? array_values( $rows ) : array(),
			'total' => $total,
		);
	}

	/**
	 * Deletes every entry.
	 */
	public function clear(): void {
		global $wpdb;

		$table = Installer::tables()['log'];
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Empties our own table.
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
				'DELETE FROM %i WHERE created_at < %s',
				$table,
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
