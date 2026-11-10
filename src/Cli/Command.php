<?php
/**
 * WP-CLI commands.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Cli;

use SocialRelayCvi\Plugin;
use SocialRelayCvi\PostMeta;
use SocialRelayCvi\Settings\ConnectionTester;
use WP_CLI;
use WP_CLI\Utils;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI passes $args and $assoc_args to every command.

/**
 * Manage Social Relay for Postiz from the command line.
 *
 * ## EXAMPLES
 *
 *     wp social-relay status
 *     wp social-relay channels --format=table
 *     wp social-relay preview 123
 *     wp social-relay share 123 --now
 */
final class Command {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * Shows the connection status (never the API key).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function status( array $args, array $assoc_args ): void {
		$connection = $this->plugin->connection;

		$rows = array(
			array(
				'field' => 'version',
				'value' => SOCIALRELAYCVI_VERSION,
			),
			array(
				'field' => 'configured',
				'value' => $connection->is_configured() ? 'yes' : 'no',
			),
			array(
				'field' => 'instance_url',
				'value' => $connection->instance_url(),
			),
			array(
				'field' => 'api_base',
				'value' => $connection->api_base(),
			),
			array(
				'field' => 'key_source',
				'value' => $connection->key_source(),
			),
		);

		Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'field', 'value' ) );
	}

	/**
	 * Tests the connection with Postiz and detects the API path.
	 *
	 * ## EXAMPLES
	 *
	 *     wp social-relay test
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function test( array $args, array $assoc_args ): void {
		$result = ( new ConnectionTester( $this->plugin ) )->run();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Connected. API: %s · %d channel(s).', $result['api_base'], $result['channels'] ) );
	}

	/**
	 * Lists the Postiz channels.
	 *
	 * ## OPTIONS
	 *
	 * [--refresh]
	 * : Skip the 15-minute cache.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - ids
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function channels( array $args, array $assoc_args ): void {
		$channels = $this->plugin->channels->all( (bool) Utils\get_flag_value( $assoc_args, 'refresh', false ) );

		if ( is_wp_error( $channels ) ) {
			WP_CLI::error( $channels->get_error_message() );
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', array_column( $channels, 'id' ) ) );
			return;
		}

		$rows = array_map(
			static fn ( array $channel ): array => array(
				'id'         => $channel['id'],
				'name'       => $channel['name'],
				'identifier' => $channel['identifier'],
				'disabled'   => $channel['disabled'] ? 'yes' : 'no',
			),
			$channels
		);

		Utils\format_items( $format, $rows, array( 'id', 'name', 'identifier', 'disabled' ) );
	}

	/**
	 * Shows the messages a post would send, without sending anything.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : Post id.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: text
	 * options:
	 *   - text
	 *   - json
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function preview( array $args, array $assoc_args ): void {
		$post = get_post( (int) $args[0] );
		if ( ! $post instanceof \WP_Post ) {
			WP_CLI::error( 'Post not found.' );
		}

		$preview = $this->plugin->share_service->preview( $post );
		if ( is_wp_error( $preview ) ) {
			WP_CLI::error( $preview->get_error_message() );
		}

		if ( 'json' === ( $assoc_args['format'] ?? 'text' ) ) {
			WP_CLI::line( (string) wp_json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		WP_CLI::line( sprintf( 'Enabled: %s · Conditions: %s · Mode: %s', $preview['enabled'] ? 'yes' : 'no', $preview['passes_conditions'] ? 'pass' : 'fail', (string) $preview['mode'] ) );

		foreach ( (array) $preview['items'] as $item ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%G' . $item['name'] . ' (' . $item['identifier'] . ')%n' ) );
			foreach ( $item['parts'] as $index => $part ) {
				if ( $index > 0 ) {
					WP_CLI::line( '---' );
				}
				WP_CLI::line( $part );
			}
		}

		foreach ( (array) $preview['skipped'] as $skip ) {
			WP_CLI::warning( $skip['integration_id'] . ': ' . $skip['reason'] );
		}
	}

	/**
	 * Shares a post with its profile.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : Post id. It must be published.
	 *
	 * [--now]
	 * : Send in this process instead of queuing the job.
	 *
	 * [--force]
	 * : Send even if the same content was already sent.
	 *
	 * ## EXAMPLES
	 *
	 *     wp social-relay share 123
	 *     wp social-relay share 123 --now --force
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function share( array $args, array $assoc_args ): void {
		$post_id = (int) $args[0];
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			WP_CLI::error( 'The post does not exist or is not published.' );
		}

		$reason = $this->why_not_shared( $post );
		if ( null !== $reason ) {
			WP_CLI::error( $reason );
		}

		if ( Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			update_post_meta( $post_id, PostMeta::RESHARE, true );
		}

		if ( ! Utils\get_flag_value( $assoc_args, 'now', false ) ) {
			$this->plugin->queue->enqueue_share( $post_id, 0 );
			WP_CLI::success( 'Share queued. Follow it with: wp social-relay shares ' . $post_id );
			return;
		}

		$before = count( $this->plugin->shares->for_post( $post_id ) );
		$this->plugin->share_service->handle( $post_id );

		if ( count( $this->plugin->shares->for_post( $post_id ) ) === $before ) {
			$last = $this->plugin->logger->latest( 1 )[0] ?? null;
			WP_CLI::error( 'Nothing was sent.' . ( $last && (int) $last['post_id'] === $post_id ? ' ' . $last['message'] : ' See: wp social-relay log' ) );
		}

		$this->shares( array( (string) $post_id ), array() );
	}

	/**
	 * Why a post would not be shared before even building the messages, or null.
	 *
	 * @param \WP_Post $post Post.
	 * @return string|null Reason.
	 */
	private function why_not_shared( \WP_Post $post ): ?string {
		if ( ! $this->plugin->connection->is_configured() ) {
			return 'Postiz is not configured yet.';
		}

		if ( ! $this->plugin->profiles->for_post_type( $post->post_type )['enabled'] ) {
			return sprintf( 'Sharing is turned off in the publishing profile for "%s".', $post->post_type );
		}

		if ( ! get_post_meta( $post->ID, PostMeta::ENABLED, true ) ) {
			return 'Sharing is turned off for this post (editor panel or metabox).';
		}

		return null;
	}

	/**
	 * Lists what was sent to Postiz for a post.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : Post id.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function shares( array $args, array $assoc_args ): void {
		$rows   = $this->plugin->shares->for_post( (int) $args[0] );
		$format = $assoc_args['format'] ?? 'table';

		if ( array() === $rows && 'table' === $format ) {
			WP_CLI::line( 'Nothing was sent to Postiz for this post yet.' );
			return;
		}

		Utils\format_items( $format, $rows, array( 'integration_id', 'identifier', 'type', 'status', 'publish_at', 'release_url', 'error' ) );
	}

	/**
	 * Cancels in Postiz what is still pending for a post.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : Post id.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function cancel( array $args, array $assoc_args ): void {
		$post_id = (int) $args[0];
		$pending = count( $this->plugin->shares->cancellable_batches( $post_id ) );

		if ( 0 === $pending ) {
			WP_CLI::success( 'Nothing pending in Postiz for this post.' );
			return;
		}

		if ( ! $this->plugin->connection->is_configured() ) {
			WP_CLI::error( 'Postiz is not configured yet.' );
		}

		$this->plugin->share_service->cancel( $post_id );
		$left = count( $this->plugin->shares->cancellable_batches( $post_id ) );

		if ( $left > 0 ) {
			WP_CLI::error( sprintf( '%d of %d pending group(s) could not be cancelled. See: wp social-relay log', $left, $pending ) );
		}

		WP_CLI::success( sprintf( 'Cancelled %d pending group(s) in Postiz.', $pending ) );
	}

	/**
	 * Updates the status of pending shares from Postiz now.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function sync( array $args, array $assoc_args ): void {
		$this->plugin->status_sync->run();
		WP_CLI::success( 'Status sync finished.' );
	}

	/**
	 * Shows the latest log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Number of entries.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 */
	public function log( array $args, array $assoc_args ): void {
		$rows   = $this->plugin->logger->latest( max( 1, (int) ( $assoc_args['limit'] ?? 20 ) ) );
		$format = $assoc_args['format'] ?? 'table';

		if ( array() === $rows && 'table' === $format ) {
			WP_CLI::line( 'The log is empty.' );
			return;
		}

		Utils\format_items( $format, $rows, array( 'created_at', 'level', 'action', 'post_id', 'integration_id', 'message' ) );
	}
}
