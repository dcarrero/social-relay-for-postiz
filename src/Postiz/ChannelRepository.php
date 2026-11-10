<?php
/**
 * Cached list of Postiz channels.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

use Closure;
use SocialRelayCvi\Settings\Connection;
use WP_Error;

/**
 * Fetches the channels (integrations) from Postiz and caches them for 15 minutes.
 */
final class ChannelRepository {

	private const TRANSIENT = 'socialrelaycvi_channels';

	/**
	 * Constructor.
	 *
	 * @param Connection                $connection     Connection settings.
	 * @param Closure(): (Gateway|null) $client_factory Returns a gateway or null when not configured.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly Closure $client_factory
	) {}

	/**
	 * All channels, normalized.
	 *
	 * @param bool $refresh Skip the cache.
	 * @return list<array{id: string, name: string, identifier: string, picture: string, profile: string, disabled: bool}>|WP_Error
	 */
	public function all( bool $refresh = false ): array|WP_Error {
		$cache_key = self::TRANSIENT . '_' . md5( $this->connection->api_base() );

		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$client = ( $this->client_factory )();
		if ( null === $client ) {
			return new WP_Error( 'socialrelaycvi_not_configured', __( 'Postiz is not configured yet.', 'social-relay-for-postiz' ) );
		}

		$response = $client->integrations();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$channels = array();
		foreach ( $response as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$channels[] = array(
				'id'         => (string) $item['id'],
				'name'       => (string) ( $item['name'] ?? '' ),
				'identifier' => (string) ( $item['identifier'] ?? '' ),
				'picture'    => (string) ( $item['picture'] ?? '' ),
				'profile'    => (string) ( $item['profile'] ?? '' ),
				'disabled'   => ! empty( $item['disabled'] ),
			);
		}

		set_transient( $cache_key, $channels, 15 * MINUTE_IN_SECONDS );

		return $channels;
	}

	/**
	 * Channels indexed by id.
	 *
	 * @return array<string, array{id: string, name: string, identifier: string, picture: string, profile: string, disabled: bool}>|WP_Error
	 */
	public function by_id(): array|WP_Error {
		$all = $this->all();
		if ( is_wp_error( $all ) ) {
			return $all;
		}

		return array_column( $all, null, 'id' );
	}

	/**
	 * Drops the cache.
	 */
	public function flush(): void {
		delete_transient( self::TRANSIENT . '_' . md5( $this->connection->api_base() ) );
	}
}
