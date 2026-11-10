<?php
/**
 * Connection settings: Postiz instance URL and API key.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Settings;

/**
 * Reads the Postiz connection settings.
 *
 * The API key follows the same lookup order as WordPress 7 connectors:
 * environment variable, then PHP constant, then the database option.
 */
final class Connection {

	public const OPTION_URL      = 'socialrelaycvi_instance_url';
	public const OPTION_API_BASE = 'socialrelaycvi_api_base';
	public const OPTION_API_KEY  = 'socialrelaycvi_api_key';
	public const CONSTANT_NAME   = 'SOCIALRELAYCVI_POSTIZ_API_KEY';
	public const ENV_VAR_NAME    = 'POSTIZ_API_KEY';

	/**
	 * Instance URL as typed by the user, e.g. https://postiz.example.com.
	 */
	public function instance_url(): string {
		return (string) get_option( self::OPTION_URL, '' );
	}

	/**
	 * Public API base detected for the instance, e.g. https://postiz.example.com/api/public/v1.
	 */
	public function api_base(): string {
		return (string) get_option( self::OPTION_API_BASE, '' );
	}

	/**
	 * Stores the detected API base.
	 *
	 * @param string $api_base Detected base URL.
	 */
	public function save_api_base( string $api_base ): void {
		update_option( self::OPTION_API_BASE, esc_url_raw( $api_base ), false );
	}

	/**
	 * The API key, from the first source that has one.
	 */
	public function api_key(): string {
		return match ( $this->key_source() ) {
			'env'      => (string) getenv( self::ENV_VAR_NAME ),
			'constant' => (string) constant( self::CONSTANT_NAME ),
			'database' => (string) get_option( self::OPTION_API_KEY, '' ),
			default    => '',
		};
	}

	/**
	 * Where the API key comes from: env, constant, database or none.
	 */
	public function key_source(): string {
		$env = getenv( self::ENV_VAR_NAME );
		if ( false !== $env && '' !== $env ) {
			return 'env';
		}

		if ( defined( self::CONSTANT_NAME ) && '' !== (string) constant( self::CONSTANT_NAME ) ) {
			return 'constant';
		}

		if ( '' !== (string) get_option( self::OPTION_API_KEY, '' ) ) {
			return 'database';
		}

		return 'none';
	}

	/**
	 * Whether the key can be edited from the admin (it is not locked by env or constant).
	 */
	public function key_is_editable(): bool {
		return in_array( $this->key_source(), array( 'database', 'none' ), true );
	}

	/**
	 * Whether there is enough configuration to call the API.
	 */
	public function is_configured(): bool {
		return '' !== $this->api_base() && '' !== $this->api_key();
	}

	/**
	 * Last four characters of the key, for display.
	 */
	public function masked_key(): string {
		$key = $this->api_key();

		if ( '' === $key ) {
			return '';
		}

		return str_repeat( '•', 12 ) . substr( $key, -4 );
	}
}
