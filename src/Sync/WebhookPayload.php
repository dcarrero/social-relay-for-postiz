<?php
/**
 * Reads what Postiz sends to the webhook.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sync;

/**
 * Postiz posts a JSON list of the published posts: id, content, publishDate, releaseURL,
 * state and integration { id, name, providerIdentifier, picture }.
 *
 * The webhook is not signed, so these values are only hints: they say which shares to
 * check, and the status itself always comes from the API. Pure, so it can be unit-tested.
 */
final class WebhookPayload {

	/**
	 * Postiz post ids and channel ids found in a webhook body.
	 *
	 * Accepts a list of posts, a single post, or { posts: [...] }. Anything else gives no ids.
	 *
	 * @param mixed $decoded Decoded JSON body.
	 * @return array{posts: int, ids: list<string>}
	 */
	public static function parse( mixed $decoded ): array {
		if ( ! is_array( $decoded ) ) {
			return array(
				'posts' => 0,
				'ids'   => array(),
			);
		}

		if ( isset( $decoded['posts'] ) && is_array( $decoded['posts'] ) ) {
			$decoded = $decoded['posts'];
		} elseif ( isset( $decoded['id'] ) ) {
			$decoded = array( $decoded );
		}

		$ids   = array();
		$posts = 0;
		foreach ( $decoded as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			++$posts;
			$id = isset( $post['id'] ) && is_scalar( $post['id'] ) ? (string) $post['id'] : '';
			// Same characters as the ids Postiz returns (cuid / uuid); anything else is ignored.
			if ( 1 === preg_match( '/^[A-Za-z0-9_-]{6,64}$/', $id ) ) {
				$ids[] = $id;
			}
		}

		return array(
			'posts' => $posts,
			'ids'   => array_values( array_unique( array_slice( $ids, 0, 50 ) ) ),
		);
	}

	/**
	 * Whether a site address cannot be reached from the internet (localhost, .test, private IPs…).
	 *
	 * @param string $url Site URL.
	 */
	public static function is_local_url( string $url ): bool {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure class, no WordPress loaded in tests.
		$host = trim( $host, '[]' );

		if ( '' === $host || 'localhost' === $host || ( ! str_contains( $host, '.' ) && false === filter_var( $host, FILTER_VALIDATE_IP ) ) ) {
			return true;
		}

		foreach ( array( '.localhost', '.local', '.test', '.example', '.invalid', '.internal', '.lan', '.home.arpa' ) as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return false;
	}
}
