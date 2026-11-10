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
use SocialRelayCvi\PostMeta;
use SocialRelayCvi\Settings\ConnectionTester;
use SocialRelayCvi\Sharing\AiSuggester;
use SocialRelayCvi\Sharing\ShareService;
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

		$can_edit = static fn ( WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['id'] );

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/share',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'share' ),
				'permission_callback' => $can_edit,
				'args'                => array(
					'id'       => array(
						'type'     => 'integer',
						'required' => true,
					),
					'channels' => array(
						'description' => __( 'Only these channel ids (retry). Empty: every channel of the post.', 'social-relay-for-postiz' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'default'     => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $can_edit,
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
			'/posts/(?P<id>\d+)/suggest',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'suggest' ),
				'permission_callback' => $can_edit,
				'args'                => array(
					'id'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'channel' => array(
						'description' => __( 'Channel id to write for; empty for a text for all networks.', 'social-relay-for-postiz' ),
						'type'        => 'string',
						'default'     => '',
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
	 * POST /posts/{id}/share — queues a new share now, even if the same content was sent before.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function share( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'socialrelaycvi_not_found', __( 'Post not found.', 'social-relay-for-postiz' ), array( 'status' => 404 ) );
		}

		$reason = $this->plugin->share_service->why_not_shared( $post );
		if ( null !== $reason ) {
			return new \WP_Error( 'socialrelaycvi_not_shareable', $reason, array( 'status' => 409 ) );
		}

		if ( ! ShareService::claim_reshare_slot( $post->ID ) ) {
			return new \WP_Error( 'socialrelaycvi_too_soon', __( 'This post was just sent. Wait a minute before sharing it again.', 'social-relay-for-postiz' ), array( 'status' => 429 ) );
		}

		$channels = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', (array) $request->get_param( 'channels' ) ) ) ) );
		if ( array() !== $channels ) {
			update_post_meta( $post->ID, PostMeta::ONLY, $channels );
		}

		update_post_meta( $post->ID, PostMeta::RESHARE, true );
		$this->plugin->queue->enqueue_share( $post->ID, 0 );

		return new WP_REST_Response( array( 'queued' => true ) );
	}

	/**
	 * POST /posts/{id}/cancel — deletes in Postiz what is still pending for the post.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function cancel( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$post_id = (int) $request['id'];
		$pending = count( $this->plugin->shares->cancellable_batches( $post_id ) );

		if ( $pending > 0 ) {
			if ( ! $this->plugin->connection->is_configured() ) {
				return new \WP_Error( 'socialrelaycvi_not_configured', __( 'Postiz is not configured yet.', 'social-relay-for-postiz' ), array( 'status' => 409 ) );
			}
			$this->plugin->share_service->cancel( $post_id );
		}

		$left = count( $this->plugin->shares->cancellable_batches( $post_id ) );

		return new WP_REST_Response(
			array(
				'cancelled' => $pending - $left,
				'left'      => $left,
			)
		);
	}

	/**
	 * POST /posts/{id}/suggest — a message written by the site's AI provider, for review in the editor.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function suggest( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'socialrelaycvi_not_found', __( 'Post not found.', 'social-relay-for-postiz' ), array( 'status' => 404 ) );
		}

		// At most 30 suggestions per user and hour: each one costs the site's AI credits.
		$quota = 'socialrelaycvi_ai_quota_' . get_current_user_id();
		$used  = (int) get_transient( $quota );
		if ( $used >= 30 ) {
			return new \WP_Error( 'socialrelaycvi_ai_quota', __( 'You have asked for many suggestions in the last hour. Try again later.', 'social-relay-for-postiz' ), array( 'status' => 429 ) );
		}
		set_transient( $quota, $used + 1, HOUR_IN_SECONDS );

		$identifier = '';
		$channel_id = sanitize_text_field( (string) $request->get_param( 'channel' ) );
		if ( '' !== $channel_id ) {
			$channels   = $this->plugin->channels->by_id();
			$identifier = is_wp_error( $channels ) ? '' : (string) ( $channels[ $channel_id ]['identifier'] ?? '' );
		}

		$text = ( new AiSuggester() )->suggest( $post, $identifier );

		return is_wp_error( $text ) ? $text : new WP_REST_Response( array( 'text' => $text ) );
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
