<?php
/**
 * HTTP client for the Postiz public API.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

use WP_Error;

/**
 * Direct connection to a Postiz instance, over the WordPress HTTP API.
 *
 * Every method returns the decoded JSON body or a WP_Error whose code is
 * `socialrelaycvi_http` (network) or `socialrelaycvi_api_{status}` (API error).
 * The error data carries `status`, `retryable` and `retry_after`.
 */
final class Client implements Gateway {

	/**
	 * Constructor.
	 *
	 * @param string $api_base Public API base, e.g. https://postiz.example.com/api/public/v1.
	 * @param string $api_key  Postiz API key.
	 * @param int    $timeout  Request timeout in seconds.
	 */
	public function __construct(
		private readonly string $api_base,
		private readonly string $api_key,
		private readonly int $timeout = 20
	) {}

	/**
	 * Checks that the key is valid for this instance.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public function is_connected(): array|WP_Error {
		return $this->request( 'GET', 'is-connected' );
	}

	/**
	 * Lists the connected channels (integrations).
	 *
	 * @param string $group Optional customer/group id.
	 * @return array<mixed>|WP_Error
	 */
	public function integrations( string $group = '' ): array|WP_Error {
		return $this->request( 'GET', 'integrations', null, '' === $group ? array() : array( 'group' => $group ) );
	}

	/**
	 * Next free publishing slot for a channel.
	 *
	 * @param string $integration_id Channel id.
	 * @return array<mixed>|WP_Error
	 */
	public function find_slot( string $integration_id ): array|WP_Error {
		return $this->request( 'GET', 'find-slot/' . rawurlencode( $integration_id ) );
	}

	/**
	 * Creates one or more posts in a single request.
	 *
	 * @param array<string, mixed> $payload CreatePostRequest body, see PayloadBuilder.
	 * @return array<mixed>|WP_Error List of { postId, integration }.
	 */
	public function create_posts( array $payload ): array|WP_Error {
		RequestCounter::hit();

		return $this->request( 'POST', 'posts', $payload );
	}

	/**
	 * Deletes every post in a group.
	 *
	 * @param string $group Group id sent when the posts were created.
	 * @return array<mixed>|WP_Error
	 */
	public function delete_group( string $group ): array|WP_Error {
		return $this->request( 'DELETE', 'posts/group/' . rawurlencode( $group ) );
	}

	/**
	 * Asks Postiz to fetch a file from a public URL.
	 *
	 * @param string $url    Public file URL.
	 * @param bool   $adjust Let Postiz resize it to the networks' limits.
	 * @return array<mixed>|WP_Error { id, path }.
	 */
	public function upload_from_url( string $url, bool $adjust = false ): array|WP_Error {
		return $this->request(
			'POST',
			'upload-from-url',
			array(
				'url'    => $url,
				'adjust' => $adjust,
			)
		);
	}

	/**
	 * Lists posts between two UTC dates.
	 *
	 * @param string $start_iso Start date, ISO 8601.
	 * @param string $end_iso   End date, ISO 8601.
	 * @return array<mixed>|WP_Error
	 */
	public function posts( string $start_iso, string $end_iso ): array|WP_Error {
		return $this->request(
			'GET',
			'posts',
			null,
			array(
				'startDate' => $start_iso,
				'endDate'   => $end_iso,
			)
		);
	}

	/**
	 * Sends a request.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Path relative to the API base.
	 * @param array<string, mixed>|null $body   JSON body.
	 * @param array<string, string>     $query  Query arguments.
	 * @return array<mixed>|WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null, array $query = array() ): array|WP_Error {
		$url = $this->api_base . '/' . ltrim( $path, '/' );

		if ( array() !== $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => $this->timeout,
			// The API never redirects; following one could carry the key to another host.
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => $this->api_key,
				'Accept'        => 'application/json',
				'User-Agent'    => 'SocialRelayForPostiz/' . SOCIALRELAYCVI_VERSION,
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}

		$response = self::allows_local_instance()
			? wp_remote_request( $url, $args )
			: wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'socialrelaycvi_http',
				$response->get_error_message(),
				array(
					'status'      => 0,
					'retryable'   => true,
					'retry_after' => 0,
				)
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = '' === $raw ? array() : json_decode( $raw, true );

		if ( $status >= 400 ) {
			return new WP_Error(
				'socialrelaycvi_api_' . $status,
				self::error_message( $status, $decoded ),
				array(
					'status'      => $status,
					'retryable'   => 429 === $status || $status >= 500,
					'retry_after' => (int) wp_remote_retrieve_header( $response, 'retry-after' ),
				)
			);
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Whether requests may go to private or local addresses.
	 *
	 * Off by default (wp_safe_remote_request blocks them). Self-hosted Postiz on a LAN
	 * or on the same server needs it: define SOCIALRELAYCVI_ALLOW_LOCAL_INSTANCE as true.
	 */
	public static function allows_local_instance(): bool {
		$allowed = defined( 'SOCIALRELAYCVI_ALLOW_LOCAL_INSTANCE' ) && SOCIALRELAYCVI_ALLOW_LOCAL_INSTANCE;

		/**
		 * Filters whether requests to Postiz may target private or local addresses.
		 *
		 * @param bool $allowed Default from the SOCIALRELAYCVI_ALLOW_LOCAL_INSTANCE constant.
		 */
		return (bool) apply_filters( 'socialrelaycvi_allow_local_instance', $allowed );
	}

	/**
	 * Human-readable message for an API error.
	 *
	 * @param int   $status  HTTP status.
	 * @param mixed $decoded Decoded body.
	 */
	private static function error_message( int $status, mixed $decoded ): string {
		if ( is_array( $decoded ) && isset( $decoded['message'] ) ) {
			$message = is_array( $decoded['message'] ) ? implode( ' ', array_map( 'strval', $decoded['message'] ) ) : (string) $decoded['message'];
			if ( '' !== $message ) {
				return sprintf( '%d · %s', $status, $message );
			}
		}

		return match ( true ) {
			401 === $status => __( '401 · The API key is not valid for this Postiz instance.', 'social-relay-for-postiz' ),
			403 === $status => __( '403 · The API key cannot access this resource.', 'social-relay-for-postiz' ),
			404 === $status => __( '404 · Endpoint not found. Check the Postiz URL.', 'social-relay-for-postiz' ),
			413 === $status => __( '413 · Request too large.', 'social-relay-for-postiz' ),
			429 === $status => __( '429 · Postiz API rate limit reached.', 'social-relay-for-postiz' ),
			/* translators: %d: HTTP status code. */
			default         => sprintf( __( '%d · Unexpected response from Postiz.', 'social-relay-for-postiz' ), $status ),
		};
	}
}
