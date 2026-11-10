<?php
/**
 * Adds UTM parameters to links.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sharing;

/**
 * Pure helper: appends utm_* parameters, keeping existing query and fragment.
 */
final class Utm {

	/**
	 * Adds UTM parameters to a URL. Existing utm_* values in the URL win.
	 *
	 * @param string                $url    URL.
	 * @param array<string, string> $params e.g. [ 'utm_source' => 'x', 'utm_medium' => 'social' ].
	 */
	public static function add( string $url, array $params ): string {
		if ( '' === $url ) {
			return $url;
		}

		$fragment = '';
		$hash_at  = strpos( $url, '#' );
		if ( false !== $hash_at ) {
			$fragment = substr( $url, $hash_at );
			$url      = substr( $url, 0, $hash_at );
		}

		$existing = array();
		$query_at = strpos( $url, '?' );
		if ( false !== $query_at ) {
			parse_str( substr( $url, $query_at + 1 ), $existing );
		}

		$add = array();
		foreach ( $params as $key => $value ) {
			if ( '' !== $value && ! array_key_exists( $key, $existing ) ) {
				$add[ $key ] = $value;
			}
		}

		if ( array() === $add ) {
			return $url . $fragment;
		}

		$separator = false === $query_at ? '?' : ( str_ends_with( $url, '?' ) || str_ends_with( $url, '&' ) ? '' : '&' );

		return $url . $separator . http_build_query( $add, '', '&', PHP_QUERY_RFC3986 ) . $fragment;
	}
}
