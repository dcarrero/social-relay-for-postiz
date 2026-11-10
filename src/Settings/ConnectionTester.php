<?php
/**
 * Detects the API base and validates the key.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Settings;

use SocialRelayCvi\Plugin;
use SocialRelayCvi\Postiz\Client;
use SocialRelayCvi\Postiz\EndpointResolver;
use WP_Error;

/**
 * Tries each candidate API base until one accepts the key.
 */
final class ConnectionTester {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * Tests the saved settings. On success stores the detected base and refreshes channels.
	 *
	 * @return array{api_base: string, channels: int}|WP_Error
	 */
	public function run(): array|WP_Error {
		$connection = $this->plugin->connection;
		$key        = $connection->api_key();

		if ( '' === $connection->instance_url() ) {
			return new WP_Error( 'socialrelaycvi_no_url', __( 'Enter the URL of your Postiz instance.', 'social-relay-for-postiz' ) );
		}

		if ( '' === $key ) {
			return new WP_Error( 'socialrelaycvi_no_key', __( 'Enter your Postiz API key (Postiz → Settings → Developers → Public API).', 'social-relay-for-postiz' ) );
		}

		$last_error = null;

		foreach ( EndpointResolver::candidates( $connection->instance_url() ) as $base ) {
			$result = ( new Client( $base, $key, 10 ) )->is_connected();

			if ( ! is_wp_error( $result ) ) {
				$connection->save_api_base( $base );
				$this->plugin->channels->flush();
				$channels = $this->plugin->channels->all( true );

				$this->plugin->logger->info( 'test', __( 'Connection to Postiz OK.', 'social-relay-for-postiz' ), 0, '', array( 'api_base' => $base ) );

				return array(
					'api_base' => $base,
					'channels' => is_wp_error( $channels ) ? 0 : count( $channels ),
				);
			}

			$last_error = $result;
			$status     = (int) ( ( (array) $result->get_error_data() )['status'] ?? 0 );

			// Every candidate is on the same host: if it cannot be reached, the next path won't be either.
			if ( 0 === $status ) {
				$last_error = new WP_Error(
					'socialrelaycvi_unreachable',
					sprintf(
						/* translators: 1: host name, 2: technical error. */
						__( 'Could not reach %1$s. Check the URL, DNS and firewall. (%2$s)', 'social-relay-for-postiz' ),
						(string) wp_parse_url( $base, PHP_URL_HOST ),
						$result->get_error_message()
					),
					$result->get_error_data()
				);
				break;
			}

			// A 401/403 means we found the API and the key is wrong: no point trying other paths.
			if ( in_array( $status, array( 401, 403 ), true ) ) {
				break;
			}
		}

		$error = $last_error ?? new WP_Error( 'socialrelaycvi_no_url', __( 'The Postiz URL is not valid.', 'social-relay-for-postiz' ) );
		$this->plugin->logger->error( 'test', $error->get_error_message() );

		return $error;
	}
}
