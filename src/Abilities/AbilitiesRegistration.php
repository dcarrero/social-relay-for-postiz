<?php
/**
 * Abilities API (WordPress 6.9+).
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Abilities;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\PostMeta;
use WP_Error;
use WP_Post;

/**
 * Exposes the plugin's actions as abilities, so AI agents (MCP Adapter) and other
 * tools such as the future Agency Hub can discover and run them.
 *
 * All abilities are shown in REST (/wp-abilities/v1) and check capabilities.
 */
final class AbilitiesRegistration implements Module {

	public const CATEGORY = 'social-media';

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
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the category unless another plugin already did.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) || wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Social media', 'social-relay-for-postiz' ),
				'description' => __( 'Publishing and scheduling content on social networks.', 'social-relay-for-postiz' ),
			)
		);
	}

	/**
	 * Registers the abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$post_input = array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'WordPress post ID.', 'social-relay-for-postiz' ),
					'minimum'     => 1,
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);

		$can_edit_post = static fn ( mixed $input ): bool => is_array( $input ) && current_user_can( 'edit_post', (int) ( $input['post_id'] ?? 0 ) );
		$can_manage    = static fn (): bool => current_user_can( 'manage_options' );
		$can_edit      = static fn (): bool => current_user_can( 'edit_posts' );

		$this->ability(
			'get-status',
			__( 'Get Postiz connection status', 'social-relay-for-postiz' ),
			__( 'Returns whether the site is connected to Postiz, the instance URL, the detected API path and where the API key comes from. Never returns the key.', 'social-relay-for-postiz' ),
			array( $this, 'get_status' ),
			$can_manage,
			null,
			array(
				'type'       => 'object',
				'properties' => array(
					'version'      => array( 'type' => 'string' ),
					'configured'   => array( 'type' => 'boolean' ),
					'instance_url' => array( 'type' => 'string' ),
					'api_base'     => array( 'type' => 'string' ),
					'key_source'   => array( 'type' => 'string' ),
				),
			),
			array( 'readonly' => true )
		);

		$this->ability(
			'list-channels',
			__( 'List social channels', 'social-relay-for-postiz' ),
			__( 'Lists the social network channels connected in Postiz (id, name, network identifier, disabled).', 'social-relay-for-postiz' ),
			array( $this, 'list_channels' ),
			$can_edit,
			null,
			array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array( 'type' => 'string' ),
						'name'       => array( 'type' => 'string' ),
						'identifier' => array( 'type' => 'string' ),
						'disabled'   => array( 'type' => 'boolean' ),
					),
				),
			),
			array( 'readonly' => true )
		);

		$this->ability(
			'preview-post',
			__( 'Preview social posts', 'social-relay-for-postiz' ),
			__( 'Shows the message each channel would receive for a post (templates, UTM and limits applied) without sending anything.', 'social-relay-for-postiz' ),
			array( $this, 'preview_post' ),
			$can_edit_post,
			$post_input,
			array( 'type' => 'object' ),
			array( 'readonly' => true )
		);

		$this->ability(
			'share-post',
			__( 'Share a post on social networks', 'social-relay-for-postiz' ),
			__( 'Queues a published post to be sent to Postiz with its publishing profile. Use preview-post first to check the messages.', 'social-relay-for-postiz' ),
			array( $this, 'share_post' ),
			$can_edit_post,
			array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id' => $post_input['properties']['post_id'],
					'force'   => array(
						'type'        => 'boolean',
						'description' => __( 'Send even if the same content was already sent.', 'social-relay-for-postiz' ),
						'default'     => false,
					),
				),
				'required'             => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array(
				'type'       => 'object',
				'properties' => array( 'queued' => array( 'type' => 'boolean' ) ),
			),
			array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			)
		);

		$this->ability(
			'get-post-shares',
			__( 'Get social sharing status of a post', 'social-relay-for-postiz' ),
			__( 'Lists what was sent to Postiz for a post: channel, status (queued, published, error, draft, cancelled), publish date, published URL and error.', 'social-relay-for-postiz' ),
			array( $this, 'get_post_shares' ),
			$can_edit_post,
			$post_input,
			array( 'type' => 'array' ),
			array( 'readonly' => true )
		);

		$this->ability(
			'cancel-post-shares',
			__( 'Cancel pending social posts', 'social-relay-for-postiz' ),
			__( 'Deletes in Postiz the drafts and future scheduled posts of a WordPress post. Posts already published on the networks are not touched.', 'social-relay-for-postiz' ),
			array( $this, 'cancel_post_shares' ),
			$can_edit_post,
			$post_input,
			array(
				'type'       => 'object',
				'properties' => array( 'cancelled' => array( 'type' => 'integer' ) ),
			),
			array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			)
		);
	}

	/**
	 * Ability: connection status.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		$connection = $this->plugin->connection;

		return array(
			'version'      => SOCIALRELAYCVI_VERSION,
			'configured'   => $connection->is_configured(),
			'instance_url' => $connection->instance_url(),
			'api_base'     => $connection->api_base(),
			'key_source'   => $connection->key_source(),
		);
	}

	/**
	 * Ability: channels.
	 *
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public function list_channels(): array|WP_Error {
		$channels = $this->plugin->channels->all();
		if ( is_wp_error( $channels ) ) {
			return $channels;
		}

		return array_map(
			static fn ( array $channel ): array => array(
				'id'         => $channel['id'],
				'name'       => $channel['name'],
				'identifier' => $channel['identifier'],
				'disabled'   => $channel['disabled'],
			),
			$channels
		);
	}

	/**
	 * Ability: dry run for a post.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function preview_post( array $input ): array|WP_Error {
		$post = $this->post( $input );

		return is_wp_error( $post ) ? $post : $this->plugin->share_service->preview( $post );
	}

	/**
	 * Ability: queue a share.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{queued: bool}|WP_Error
	 */
	public function share_post( array $input ): array|WP_Error {
		$post = $this->post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'socialrelaycvi_not_published', __( 'Only published posts can be shared.', 'social-relay-for-postiz' ) );
		}

		if ( ! empty( $input['force'] ) ) {
			update_post_meta( $post->ID, PostMeta::RESHARE, true );
		}

		$this->plugin->queue->enqueue_share( $post->ID, 0 );

		return array( 'queued' => true );
	}

	/**
	 * Ability: shares of a post.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return list<array<string, mixed>>|WP_Error
	 */
	public function get_post_shares( array $input ): array|WP_Error {
		$post = $this->post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return array_map(
			static fn ( array $row ): array => array(
				'integration_id' => (string) $row['integration_id'],
				'identifier'     => (string) $row['identifier'],
				'status'         => (string) $row['status'],
				'type'           => (string) $row['type'],
				'publish_at'     => (string) $row['publish_at'],
				'release_url'    => (string) ( $row['release_url'] ?? '' ),
				'error'          => (string) ( $row['error'] ?? '' ),
			),
			$this->plugin->shares->for_post( $post->ID )
		);
	}

	/**
	 * Ability: cancel pending posts.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array{cancelled: int}|WP_Error
	 */
	public function cancel_post_shares( array $input ): array|WP_Error {
		$post = $this->post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$batches = count( $this->plugin->shares->cancellable_batches( $post->ID ) );
		$this->plugin->share_service->cancel( $post->ID );

		return array( 'cancelled' => $batches );
	}

	/**
	 * Registers one ability under the plugin namespace.
	 *
	 * @param string                    $name        Name without namespace.
	 * @param string                    $label       Label.
	 * @param string                    $description Description for agents: what it does and when to use it.
	 * @param callable                  $execute     Execute callback.
	 * @param callable                  $permission  Permission callback.
	 * @param array<string, mixed>|null $input       Input schema, null when the ability takes no input.
	 * @param array<string, mixed>      $output      Output schema.
	 * @param array<string, bool>       $annotations Behaviour hints.
	 */
	private function ability( string $name, string $label, string $description, callable $execute, callable $permission, ?array $input, array $output, array $annotations ): void {
		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => self::CATEGORY,
			'execute_callback'    => $execute,
			'permission_callback' => $permission,
			'output_schema'       => $output,
			'meta'                => array(
				'annotations'  => $annotations,
				'show_in_rest' => true,
			),
		);

		if ( null !== $input ) {
			$args['input_schema'] = $input;
		}

		wp_register_ability( 'socialrelaycvi/' . $name, $args );
	}

	/**
	 * Post from the input.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return WP_Post|WP_Error
	 */
	private function post( array $input ): WP_Post|WP_Error {
		$post = get_post( (int) ( $input['post_id'] ?? 0 ) );

		return $post instanceof WP_Post ? $post : new WP_Error( 'socialrelaycvi_not_found', __( 'Post not found.', 'social-relay-for-postiz' ) );
	}
}
