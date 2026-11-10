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
			'/posts/(?P<id>\d+)/shares',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'shares' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['id'] ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => static fn ( WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['id'] ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
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
	 * GET /posts/{id}/shares — what was sent to Postiz for a post, newest first.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function shares( WP_REST_Request $request ): WP_REST_Response {
		$rows = array_map(
			static fn ( array $row ): array => array(
				'id'             => (int) $row['id'],
				'integration_id' => (string) $row['integration_id'],
				'identifier'     => (string) $row['identifier'],
				'batch'          => (string) $row['batch'],
				'postiz_post_id' => (string) $row['postiz_post_id'],
				'type'           => (string) $row['type'],
				'status'         => (string) $row['status'],
				'publish_at'     => null === $row['publish_at'] ? null : self::utc( (string) $row['publish_at'] ),
				'release_url'    => (string) ( $row['release_url'] ?? '' ),
				'error'          => (string) ( $row['error'] ?? '' ),
				'created_at'     => self::utc( (string) $row['created_at'] ),
			),
			$this->plugin->shares->for_post( (int) $request['id'] )
		);

		return new WP_REST_Response( $rows );
	}

	/**
	 * GET /posts/{id}/preview — messages that would be sent, nothing is sent.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'socialrelaycvi_not_found', __( 'Post not found.', 'social-relay-for-postiz' ), array( 'status' => 404 ) );
		}

		$preview = $this->plugin->share_service->preview( $post );

		return is_wp_error( $preview ) ? $preview : new WP_REST_Response( $preview );
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

	/**
	 * Converts a stored UTC MySQL datetime to ISO 8601 with Z.
	 *
	 * @param string $mysql_utc Datetime in UTC, Y-m-d H:i:s.
	 */
	private static function utc( string $mysql_utc ): string {
		return gmdate( 'Y-m-d\\TH:i:s\\Z', (int) strtotime( $mysql_utc . ' UTC' ) );
	}
}
