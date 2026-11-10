<?php
/**
 * Works out the public API base URL of a Postiz instance.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

/**
 * Turns whatever URL the user typed into the list of API bases worth trying.
 *
 * Self-hosted Postiz serves the public API at {domain}/api/public/v1 when the backend
 * sits behind the frontend, or at {backend}/public/v1 when the backend has its own
 * host. Postiz Cloud uses https://api.postiz.com/public/v1.
 */
final class EndpointResolver {

	public const CLOUD_BASE = 'https://api.postiz.com/public/v1';

	/**
	 * Candidate API bases, most likely first.
	 *
	 * @param string $url URL typed by the user.
	 * @return list<string>
	 */
	public static function candidates( string $url ): array {
		$url = self::normalize( $url );

		if ( '' === $url ) {
			return array();
		}

		$host = (string) parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure helper, also used outside WordPress in tests.

		if ( in_array( $host, array( 'postiz.com', 'www.postiz.com', 'app.postiz.com', 'platform.postiz.com', 'api.postiz.com' ), true ) ) {
			return array( self::CLOUD_BASE );
		}

		if ( str_ends_with( $url, '/public/v1' ) ) {
			return array( $url );
		}

		if ( str_ends_with( $url, '/api' ) ) {
			return array( $url . '/public/v1' );
		}

		return array( $url . '/api/public/v1', $url . '/public/v1' );
	}

	/**
	 * Adds https:// when missing and strips trailing slashes, query and fragment.
	 *
	 * @param string $url Raw URL.
	 */
	public static function normalize( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		$url = (string) preg_replace( '/[?#].*$/', '', $url );

		return rtrim( $url, '/' );
	}
}
