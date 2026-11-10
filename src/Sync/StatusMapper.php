<?php
/**
 * Maps Postiz post states to share statuses.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sync;

/**
 * Pure mapping and change detection for the status sync.
 */
final class StatusMapper {

	/**
	 * Share status for a Postiz state, or null for unknown states.
	 *
	 * @param string $state Postiz state: QUEUE, PUBLISHED, ERROR or DRAFT.
	 */
	public static function status( string $state ): ?string {
		return match ( strtoupper( $state ) ) {
			'QUEUE'     => 'queued',
			'PUBLISHED' => 'published',
			'ERROR'     => 'error',
			'DRAFT'     => 'draft',
			default     => null,
		};
	}

	/**
	 * Changes to apply to a share row, or an empty array when nothing changed.
	 *
	 * @param array<string, mixed> $row         Share row (status, release_url, error).
	 * @param array<string, mixed> $postiz_post Post from GET /posts (state, releaseURL, error).
	 * @return array{status?: string, release_url?: string, error?: string}
	 */
	public static function changes( array $row, array $postiz_post ): array {
		$status = self::status( (string) ( $postiz_post['state'] ?? '' ) );
		if ( null === $status ) {
			return array();
		}

		$changes = array();

		$current_status = (string) ( $row['status'] ?? '' );
		$current_url    = (string) ( $row['release_url'] ?? '' );
		$current_error  = (string) ( $row['error'] ?? '' );

		if ( $current_status !== $status ) {
			$changes['status'] = $status;
		}

		$release_url = (string) ( $postiz_post['releaseURL'] ?? '' );
		// Only web links: the URL becomes a link in wp-admin.
		if ( 1 !== preg_match( '#^https?://#i', $release_url ) ) {
			$release_url = '';
		}
		if ( '' !== $release_url && $current_url !== $release_url ) {
			$changes['release_url'] = $release_url;
		}

		$error = 'error' === $status ? (string) ( $postiz_post['error'] ?? '' ) : '';
		if ( $current_error !== $error ) {
			$changes['error'] = $error;
		}

		return $changes;
	}

	/**
	 * UTC date range (ISO 8601) that covers every publish date, with a day of margin.
	 *
	 * @param list<int> $timestamps Publish times.
	 * @return array{0: string, 1: string}
	 */
	public static function range( array $timestamps ): array {
		$start = min( $timestamps ) - 86400;
		$end   = max( $timestamps ) + 86400;

		return array( gmdate( 'Y-m-d\TH:i:s.000\Z', $start ), gmdate( 'Y-m-d\TH:i:s.000\Z', $end ) );
	}
}
