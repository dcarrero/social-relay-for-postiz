<?php
/**
 * REST routes for the admin screens.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Rest;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Postiz\EndpointResolver;
use SocialRelayCvi\Postiz\ProviderRules;
use SocialRelayCvi\Profiles\ProfileRepository;
use SocialRelayCvi\Settings\Connection;
use SocialRelayCvi\Settings\ConnectionTester;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;

/**
 * Routes under socialrelaycvi/v1/admin, used by the React settings and log screens.
 * Every route requires manage_options. The API key is write-only.
 */
final class AdminController implements Module {

	/**
	 * Option read by uninstall.php.
	 */
	public const OPTION_DELETE_DATA = 'socialrelaycvi_delete_data_on_uninstall';

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
		$admin = static fn (): bool => current_user_can( 'manage_options' );
		$ns    = ConnectionController::NAMESPACE;

		register_rest_route(
			$ns,
			'/admin/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			$ns,
			'/admin/connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_connection' ),
				'permission_callback' => $admin,
				'args'                => array(
					'instance_url' => array(
						'type'     => 'string',
						'required' => true,
					),
					'api_key'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/admin/profiles',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_profiles' ),
				'permission_callback' => $admin,
				'args'                => array(
					'profiles' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/admin/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			$ns,
			'/admin/advanced',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_advanced' ),
				'permission_callback' => $admin,
				'args'                => array(
					'delete_on_uninstall' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/admin/webhook/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'regenerate_webhook' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			$ns,
			'/admin/log',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => $admin,
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'level'    => array(
							'type'    => 'string',
							'default' => '',
							'enum'    => array( '', 'info', 'error' ),
						),
						'search'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'post_id'  => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_log' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * GET /admin/settings — everything the settings screen needs.
	 */
	public function get_settings(): WP_REST_Response {
		return new WP_REST_Response( $this->settings_payload() );
	}

	/**
	 * POST /admin/connection — saves URL and key, then tests the connection.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function save_connection( WP_REST_Request $request ): WP_REST_Response {
		$url = EndpointResolver::normalize( sanitize_text_field( (string) $request['instance_url'] ) );
		update_option( Connection::OPTION_URL, esc_url_raw( $url ), false );

		$key = trim( sanitize_text_field( (string) $request['api_key'] ) );
		if ( '' !== $key && $this->plugin->connection->key_is_editable() ) {
			update_option( Connection::OPTION_API_KEY, $key, false );
		}

		$result = ( new ConnectionTester( $this->plugin ) )->run();

		return new WP_REST_Response(
			array(
				'ok'       => ! is_wp_error( $result ),
				'message'  => is_wp_error( $result ) ? $result->get_error_message() : '',
				'channels' => is_wp_error( $result ) ? 0 : $result['channels'],
				'settings' => $this->settings_payload(),
			)
		);
	}

	/**
	 * POST /admin/profiles — saves every profile.
	 *
	 * Conditions arrive as include_categories / exclude_categories (term ids) and
	 * include_tags / exclude_tags (tag names); they are stored as term ids.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function save_profiles( WP_REST_Request $request ): WP_REST_Response {
		$raw      = (array) $request['profiles'];
		$channels = $this->plugin->channels->all();
		$ids      = is_wp_error( $channels ) ? array() : array_column( $channels, 'identifier', 'id' );
		$profiles = array();

		foreach ( array_keys( $this->post_types() ) as $post_type ) {
			$profile = isset( $raw[ $post_type ] ) && is_array( $raw[ $post_type ] ) ? $raw[ $post_type ] : array();

			foreach ( array( 'include', 'exclude' ) as $kind ) {
				$profile[ $kind . '_terms' ] = array_merge(
					array_map( 'absint', (array) ( $profile[ $kind . '_categories' ] ?? array() ) ),
					self::tag_ids( (array) ( $profile[ $kind . '_tags' ] ?? array() ) )
				);
			}

			$profiles[ $post_type ] = ProfileRepository::sanitize( $profile, $ids );
		}

		update_option( ProfileRepository::OPTION, $profiles, false );

		return new WP_REST_Response( $this->settings_payload() );
	}

	/**
	 * GET /admin/stats — figures for the log screen.
	 */
	public function get_stats(): WP_REST_Response {
		$counts = $this->plugin->shares->counts( 30 );

		return new WP_REST_Response(
			array(
				'published' => (int) ( $counts['published'] ?? 0 ),
				'queued'    => (int) ( $counts['queued'] ?? 0 ),
				'errors'    => (int) ( $counts['error'] ?? 0 ),
				'lastHour'  => $this->plugin->shares->batches_since( HOUR_IN_SECONDS ),
			)
		);
	}

	/**
	 * POST /admin/advanced — options that are not part of a profile.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function save_advanced( WP_REST_Request $request ): WP_REST_Response {
		update_option( self::OPTION_DELETE_DATA, (bool) $request['delete_on_uninstall'], false );

		return new WP_REST_Response( $this->settings_payload() );
	}

	/**
	 * POST /admin/webhook/regenerate
	 */
	public function regenerate_webhook(): WP_REST_Response {
		WebhookController::regenerate();

		return new WP_REST_Response( $this->webhook_payload() );
	}

	/**
	 * GET /admin/log
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function get_log( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$result   = $this->plugin->logger->query( (int) $request['page'], $per_page, (string) $request['level'], (string) $request['search'], (int) $request['post_id'] );
		$titles   = array();

		$items = array_map(
			static function ( array $row ) use ( &$titles ): array {
				$post_id = (int) $row['post_id'];
				if ( $post_id > 0 && ! isset( $titles[ $post_id ] ) ) {
					$titles[ $post_id ] = get_the_title( $post_id );
				}

				return array(
					'id'             => (int) $row['id'],
					'created_at'     => gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( (string) $row['created_at'] . ' UTC' ) ),
					'level'          => (string) $row['level'],
					'action'         => (string) $row['action'],
					'post_id'        => $post_id,
					'post_title'     => $post_id > 0 ? html_entity_decode( (string) $titles[ $post_id ], ENT_QUOTES, 'UTF-8' ) : '',
					'edit_link'      => $post_id > 0 ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
					'integration_id' => (string) $row['integration_id'],
					'message'        => (string) $row['message'],
				);
			},
			$result['items']
		);

		return new WP_REST_Response(
			array(
				'items' => $items,
				'total' => $result['total'],
				'pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);
	}

	/**
	 * DELETE /admin/log
	 */
	public function clear_log(): WP_REST_Response {
		$this->plugin->logger->clear();

		return new WP_REST_Response( array( 'cleared' => true ) );
	}

	/**
	 * Settings screen data. Never includes the API key.
	 *
	 * @return array<string, mixed>
	 */
	private function settings_payload(): array {
		$connection = $this->plugin->connection;
		$channels   = $connection->is_configured() ? $this->plugin->channels->all() : array();
		$error      = is_wp_error( $channels ) ? $channels->get_error_message() : '';
		$channels   = is_wp_error( $channels ) ? array() : $channels;

		$fields = array();
		$limits = array();
		foreach ( $channels as $channel ) {
			$fields[ $channel['identifier'] ] = ProviderRules::setting_fields( $channel['identifier'] );
			$limits[ $channel['identifier'] ] = ProviderRules::char_limit( $channel['identifier'] );
		}

		$profiles = array();
		foreach ( $this->post_types() as $post_type => $info ) {
			$profile                = $this->plugin->profiles->for_post_type( $post_type );
			$profiles[ $post_type ] = $profile + $this->split_terms( $profile['include_terms'], 'include' ) + $this->split_terms( $profile['exclude_terms'], 'exclude' );
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		return array(
			'connection'    => array(
				'instance_url' => $connection->instance_url(),
				'api_base'     => $connection->api_base(),
				'configured'   => $connection->is_configured(),
				'key_source'   => $connection->key_source(),
				'key_editable' => $connection->key_is_editable(),
				'masked_key'   => $connection->masked_key(),
			),
			'webhook'       => $this->webhook_payload(),
			'advanced'      => array(
				'delete_on_uninstall' => (bool) get_option( self::OPTION_DELETE_DATA, false ),
				'delete_constant'     => defined( 'SOCIALRELAYCVI_DELETE_DATA' ) && SOCIALRELAYCVI_DELETE_DATA,
			),
			'channels'      => $channels,
			'channelsError' => $error,
			'postTypes'     => $this->post_types(),
			'profiles'      => $profiles,
			'fields'        => $fields,
			'limits'        => $limits,
			'categories'    => is_array( $categories )
				? array_values(
					array_map(
						static fn ( WP_Term $term ): array => array(
							'id'   => $term->term_id,
							'name' => $term->name,
						),
						array_filter( $categories, static fn ( mixed $term ): bool => $term instanceof WP_Term )
					)
				)
				: array(),
		);
	}

	/**
	 * Webhook URL and last call.
	 *
	 * @return array{url: string, last: int}
	 */
	private function webhook_payload(): array {
		return array(
			'url'  => WebhookController::url(),
			'last' => (int) get_option( WebhookController::OPTION_LAST, 0 ),
		);
	}

	/**
	 * Public post types with an admin UI, and which term taxonomies they use.
	 *
	 * @return array<string, array{label: string, rest_base: string, categories: bool, tags: bool}>
	 */
	private function post_types(): array {
		$types = array();

		foreach ( get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}

			$types[ $type->name ] = array(
				'label'      => (string) $type->labels->name,
				'rest_base'  => $type->show_in_rest ? (string) ( $type->rest_base ? $type->rest_base : $type->name ) : '',
				'categories' => is_object_in_taxonomy( $type->name, 'category' ),
				'tags'       => is_object_in_taxonomy( $type->name, 'post_tag' ),
			);
		}

		/** This filter is documented in src/Admin/SettingsPage.php */
		return (array) apply_filters( 'socialrelaycvi_post_types', $types );
	}

	/**
	 * Splits stored term ids into category ids and tag names for the UI.
	 *
	 * @param list<int> $term_ids Term ids.
	 * @param string    $kind     include or exclude.
	 * @return array<string, list<int>|list<string>>
	 */
	private function split_terms( array $term_ids, string $kind ): array {
		$categories = array();
		$tags       = array();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			if ( 'category' === $term->taxonomy ) {
				$categories[] = $term->term_id;
			} elseif ( 'post_tag' === $term->taxonomy ) {
				$tags[] = $term->name;
			}
		}

		return array(
			$kind . '_categories' => $categories,
			$kind . '_tags'       => $tags,
		);
	}

	/**
	 * Tag ids from names; unknown names are ignored.
	 *
	 * @param array<mixed> $names Tag names.
	 * @return list<int>
	 */
	private static function tag_ids( array $names ): array {
		$ids = array();

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', sanitize_text_field( (string) $name ), 'post_tag' );
			if ( $term instanceof WP_Term ) {
				$ids[] = $term->term_id;
			}
		}

		return $ids;
	}
}
