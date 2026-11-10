<?php
/**
 * Counts the requests that create posts in Postiz.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

/**
 * Postiz allows 90 POST /posts per hour. Every attempt counts, failed ones and
 * retries included, so the count lives here and not in the shares table (the
 * "next free slot" mode makes one request per channel, retries add more).
 */
final class RequestCounter {

	public const OPTION = 'socialrelaycvi_post_requests';

	/**
	 * Postiz limit per hour.
	 */
	public const LIMIT = 90;

	/**
	 * Records one request now.
	 */
	public static function hit(): void {
		$times   = self::recent( HOUR_IN_SECONDS );
		$times[] = time();

		update_option( self::OPTION, array_slice( $times, -( self::LIMIT * 2 ) ), false );
	}

	/**
	 * Requests in the last $seconds.
	 *
	 * @param int $seconds Window.
	 */
	public static function count( int $seconds = HOUR_IN_SECONDS ): int {
		return count( self::recent( $seconds ) );
	}

	/**
	 * Stored times inside the window.
	 *
	 * @param int $seconds Window.
	 * @return list<int>
	 */
	private static function recent( int $seconds ): array {
		$since = time() - $seconds;
		$times = get_option( self::OPTION, array() );

		return array_values( array_filter( array_map( 'intval', is_array( $times ) ? $times : array() ), static fn ( int $time ): bool => $time >= $since ) );
	}
}
