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
	 * Prefix of the short-lived mutex rows in the options table.
	 */
	public const LOCK_PREFIX = 'socialrelaycvi_lock_';

	/**
	 * Postiz limit per hour.
	 */
	public const LIMIT = 90;

	/**
	 * Records one request now.
	 *
	 * Parallel queue runners write the same option: the read-modify-write runs under a mutex
	 * and reads the stored value, not this process's cached copy.
	 */
	public static function hit(): void {
		self::with_lock(
			'post_requests',
			static function (): void {
				wp_cache_delete( self::OPTION, 'options' );

				$times   = self::recent( HOUR_IN_SECONDS );
				$times[] = time();

				update_option( self::OPTION, array_slice( $times, -( self::LIMIT * 2 ) ), false );
			}
		);
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
	 * Runs $callback while holding a short mutex shared by every PHP process of the site.
	 *
	 * The mutex is a row in the options table created with INSERT IGNORE, which is atomic
	 * on the unique option_name key. A lock left by a crashed process expires after 30 seconds.
	 * If the lock cannot be taken in about 2 seconds the callback runs anyway: a slightly
	 * wrong count is better than a lost request or a stuck job.
	 *
	 * @template T
	 * @param string        $name     Lock name (a-z, 0-9, _).
	 * @param callable(): T $callback Work to do.
	 * @return T
	 */
	public static function with_lock( string $name, callable $callback ): mixed {
		$key  = self::LOCK_PREFIX . $name;
		$held = self::acquire( $key );

		try {
			return $callback();
		} finally {
			if ( null !== $held ) {
				self::release( $key, $held );
			}
		}
	}

	/**
	 * Takes the lock.
	 *
	 * @param string $key Option name.
	 * @return string|null Token stored in the row, or null when the lock was not taken.
	 */
	private static function acquire( string $key ): ?string {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return null;
		}

		for ( $try = 0; $try < 40; $try++ ) {
			$token = ( time() + 30 ) . ':' . wp_generate_password( 12, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic insert is the point; the row is never cached.
			if ( 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, $token ) ) ) {
				return $token;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh read of the lock row.
			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
			if ( '' !== $current && (int) $current < time() ) {
				// Expired: remove only that exact row, so two processes cannot both take it over.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-delete of the lock row.
				$wpdb->delete(
					$wpdb->options,
					array(
						'option_name'  => $key,
						'option_value' => $current,
					)
				);
				continue;
			}

			usleep( 50000 );
		}

		return null;
	}

	/**
	 * Frees the lock, only if it is still ours.
	 *
	 * @param string $key   Option name.
	 * @param string $token Token returned by acquire().
	 */
	private static function release( string $key, string $token ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-delete of the lock row.
		$wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => $key,
				'option_value' => $token,
			)
		);
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
