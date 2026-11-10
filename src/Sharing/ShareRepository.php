<?php
/**
 * Storage for what was sent to Postiz.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sharing;

use SocialRelayCvi\Lifecycle\Installer;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table, no core API for it.

/**
 * Reads and writes the socialrelaycvi_shares table.
 */
final class ShareRepository {

	/**
	 * Inserts a share row.
	 *
	 * @param array{post_id: int, integration_id: string, identifier: string, batch: string, postiz_post_id: string, type: string, status: string, publish_at: string|null, content_hash: string, error?: string} $row Row data.
	 */
	public function insert( array $row ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$wpdb->insert(
			Installer::tables()['shares'],
			array(
				'post_id'        => $row['post_id'],
				'integration_id' => $row['integration_id'],
				'identifier'     => $row['identifier'],
				'batch'          => $row['batch'],
				'postiz_post_id' => $row['postiz_post_id'],
				'type'           => $row['type'],
				'status'         => $row['status'],
				'publish_at'     => $row['publish_at'],
				'content_hash'   => $row['content_hash'],
				'error'          => $row['error'] ?? null,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether this exact content was already sent for the post.
	 *
	 * @param int    $post_id      Post id.
	 * @param string $content_hash Hash of channels + messages.
	 */
	public function was_sent( int $post_id, string $content_hash ): bool {
		global $wpdb;

		$table = Installer::tables()['shares'];

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM %i WHERE post_id = %d AND content_hash = %s AND status <> 'error' LIMIT 1",
				$table,
				$post_id,
				$content_hash
			)
		);
	}

	/**
	 * Number of batches already sent for a post.
	 *
	 * @param int $post_id Post id.
	 */
	public function batch_count( int $post_id ): int {
		global $wpdb;

		$table = Installer::tables()['shares'];

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT batch) FROM %i WHERE post_id = %d',
				$table,
				$post_id
			)
		);
	}

	/**
	 * Every share of a post, newest first.
	 *
	 * @param int $post_id Post id.
	 * @return list<array<string, mixed>>
	 */
	public function for_post( int $post_id ): array {
		global $wpdb;

		$table = Installer::tables()['shares'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE post_id = %d ORDER BY id DESC',
				$table,
				$post_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Batches of a post that can still be cancelled in Postiz.
	 *
	 * Only drafts and posts scheduled in the future: a "now" post or a past date may
	 * already be live, and deleting it in Postiz would only remove Postiz's record.
	 *
	 * @param int $post_id Post id.
	 * @return list<string> Batch (group) ids.
	 */
	public function cancellable_batches( int $post_id ): array {
		global $wpdb;

		$table   = Installer::tables()['shares'];
		$batches = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT batch FROM %i WHERE post_id = %d AND ( status = 'draft' OR ( status = 'queued' AND type = 'schedule' AND publish_at > %s ) )",
				$table,
				$post_id,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		return array_values( array_map( 'strval', (array) $batches ) );
	}

	/**
	 * Sets the status of every row of a batch.
	 *
	 * @param string $batch  Batch (group) id.
	 * @param string $status New status.
	 */
	public function set_batch_status( string $batch, string $status ): void {
		global $wpdb;

		$wpdb->update(
			Installer::tables()['shares'],
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'batch' => $batch ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Rows whose state may still change in Postiz: queued or draft, within a time window.
	 *
	 * @param int $past_days   How far back to look (publish date).
	 * @param int $future_days How far ahead to look (publish date).
	 * @param int $limit       Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function syncable( int $past_days = 14, int $future_days = 60, int $limit = 200 ): array {
		global $wpdb;

		$table = Installer::tables()['shares'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status IN ( 'queued', 'draft' ) AND postiz_post_id <> '' AND publish_at BETWEEN %s AND %s ORDER BY publish_at ASC LIMIT %d",
				$table,
				gmdate( 'Y-m-d H:i:s', time() - $past_days * DAY_IN_SECONDS ),
				gmdate( 'Y-m-d H:i:s', time() + $future_days * DAY_IN_SECONDS ),
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Updates one row.
	 *
	 * @param int                                                          $id      Row id.
	 * @param array{status?: string, release_url?: string, error?: string} $changes Columns to change.
	 */
	public function update_row( int $id, array $changes ): void {
		global $wpdb;

		if ( array() === $changes ) {
			return;
		}

		$changes['updated_at'] = current_time( 'mysql', true );

		$wpdb->update(
			Installer::tables()['shares'],
			$changes,
			array( 'id' => $id ),
			array_fill( 0, count( $changes ), '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Counts per status over the last days, for the dashboard.
	 *
	 * @param int $days Days back from now (by creation date).
	 * @return array<string, int>
	 */
	public function counts( int $days = 7 ): array {
		global $wpdb;

		$table = Installer::tables()['shares'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS total FROM %i WHERE created_at >= %s GROUP BY status',
				$table,
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Latest rows of any post, for the dashboard.
	 *
	 * @param string $status Optional status filter.
	 * @param int    $limit  Maximum rows.
	 * @param string $order  'upcoming' (by publish date ascending, future only) or 'recent'.
	 * @return list<array<string, mixed>>
	 */
	public function latest( string $status = '', int $limit = 5, string $order = 'recent' ): array {
		global $wpdb;

		$table = Installer::tables()['shares'];

		// Static SQL per order; an empty status switches its condition off.
		$rows = 'upcoming' === $order
			? $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE ( %s = '' OR status = %s ) AND publish_at > %s ORDER BY publish_at ASC LIMIT %d",
					$table,
					$status,
					$status,
					gmdate( 'Y-m-d H:i:s' ),
					$limit
				),
				ARRAY_A
			)
			: $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE ( %s = '' OR status = %s ) ORDER BY updated_at DESC LIMIT %d",
					$table,
					$status,
					$status,
					$limit
				),
				ARRAY_A
			);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}
}
