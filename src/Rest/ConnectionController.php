<?php
/**
 * REST routes: connection test and channel list.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Rest;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Settings\ConnectionTester;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Routes under socialrelaycvi/v1, used by the editor panel and, later, by the Agency Hub.
 */
final class ConnectionController implements Module {

	public const NAMESPACE = 'socialrelaycvi/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/connection/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test' ),
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => static fn (): bool => current_user_can( 'manage_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/channels',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'channels' ),
				'permission_callback' => static fn (): bool => current_user_can( 'edit_posts' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * POST /connection/test
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function test(): WP_REST_Response|\WP_Error {
		$result = ( new ConnectionTester( $this->plugin ) )->run();

		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * GET /status — connection state without secrets.
	 */
	public function status(): WP_REST_Response {
		$connection = $this->plugin->connection;

		return new WP_REST_Response(
			array(
				'version'      => SOCIALRELAYCVI_VERSION,
				'configured'   => $connection->is_configured(),
				'instance_url' => $connection->instance_url(),
				'api_base'     => $connection->api_base(),
				'key_source'   => $connection->key_source(),
			)
		);
	}

	/**
	 * GET /channels
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function channels( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$channels = $this->plugin->channels->all( (bool) $request->get_param( 'refresh' ) && current_user_can( 'manage_options' ) );

		return is_wp_error( $channels ) ? $channels : new WP_REST_Response( $channels );
	}
}
