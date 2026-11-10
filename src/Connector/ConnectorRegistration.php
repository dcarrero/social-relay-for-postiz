<?php
/**
 * Registers Postiz in Settings → Connectors (WordPress 7).
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Connector;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Settings\Connection;

/**
 * Adds a "Postiz" card to the core Connectors screen.
 *
 * Core then registers the API key setting, shows where the key comes from
 * (env, constant or database) and masks it in REST responses. The instance URL
 * is not part of the connector model, so it lives on the plugin's settings page.
 */
final class ConnectorRegistration implements Module {

	public const ID = 'socialrelaycvi_postiz';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'wp_connectors_init', array( $this, 'register_connector' ) );
	}

	/**
	 * Registers the connector.
	 *
	 * @param object $registry WP_Connector_Registry instance.
	 */
	public function register_connector( object $registry ): void {
		if ( ! $registry instanceof \WP_Connector_Registry || $registry->is_registered( self::ID ) ) {
			return;
		}

		$instance_url = $this->plugin->connection->instance_url();

		$registry->register(
			self::ID,
			array(
				'name'           => 'Postiz',
				'description'    => __( 'Publish to social networks through your Postiz instance (self-hosted or Postiz Cloud). Set the instance URL in Social Relay → Settings.', 'social-relay-for-postiz' ),
				'type'           => 'social_publishing',
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => '' !== $instance_url ? $instance_url : 'https://docs.postiz.com/public-api/introduction',
					'setting_name'    => Connection::OPTION_API_KEY,
					'constant_name'   => Connection::CONSTANT_NAME,
					'env_var_name'    => Connection::ENV_VAR_NAME,
				),
				'plugin'         => array(
					'file'      => plugin_basename( SOCIALRELAYCVI_FILE ),
					'is_active' => '__return_true',
				),
			)
		);
	}
}
