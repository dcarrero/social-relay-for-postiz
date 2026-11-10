<?php
/**
 * Keeps share statuses in line with Postiz.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sync;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Queue\Queue;

/**
 * Reads GET /posts for the pending shares and records published, error and draft states.
 *
 * Runs every 15 minutes while something is pending, a couple of minutes after each
 * send, and right away when the Postiz webhook calls in. The webhook is only a
 * trigger: the data always comes from the API, so its payload format does not matter.
 */
final class StatusSync implements Module {

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
		add_action( Queue::HOOK_SYNC, array( $this, 'run' ), 10, 0 );
		add_action( 'socialrelaycvi_shared', array( $this, 'after_share' ) );
	}

	/**
	 * Checks again shortly after a batch is accepted, when "now" posts are usually live.
	 */
	public function after_share(): void {
		$this->plugin->queue->enqueue_sync( 2 * MINUTE_IN_SECONDS );
	}

	/**
	 * Sync job.
	 */
	public function run(): void {
		$rows = $this->plugin->shares->syncable();
		if ( array() === $rows ) {
			return;
		}

		$gateway = $this->plugin->gateway();
		if ( null === $gateway ) {
			return;
		}

		$times           = array_map( static fn ( array $row ): int => (int) strtotime( (string) $row['publish_at'] . ' UTC' ), $rows );
		[ $start, $end ] = StatusMapper::range( $times );

		$response = $gateway->posts( $start, $end );
		if ( is_wp_error( $response ) ) {
			$this->plugin->logger->error( 'sync', $response->get_error_message() );
			return;
		}

		$by_id = array();
		foreach ( (array) ( $response['posts'] ?? array() ) as $post ) {
			if ( is_array( $post ) && isset( $post['id'] ) ) {
				$by_id[ (string) $post['id'] ] = $post;
			}
		}

		foreach ( $rows as $row ) {
			$postiz_post = $by_id[ (string) $row['postiz_post_id'] ] ?? null;
			if ( null === $postiz_post ) {
				continue;
			}

			$changes = StatusMapper::changes( $row, $postiz_post );
			if ( array() === $changes ) {
				continue;
			}

			$this->plugin->shares->update_row( (int) $row['id'], $changes );
			$this->log_change( $row, $changes );

			/**
			 * Fires when a share changes state in Postiz.
			 *
			 * @param array<string, mixed>  $row     Share row before the change.
			 * @param array<string, string> $changes Changed columns (status, release_url, error).
			 */
			do_action( 'socialrelaycvi_status_changed', $row, $changes );
		}
	}

	/**
	 * Logs the interesting transitions.
	 *
	 * @param array<string, mixed>  $row     Share row.
	 * @param array<string, string> $changes Changes.
	 */
	private function log_change( array $row, array $changes ): void {
		$status = $changes['status'] ?? '';
		$post   = (int) $row['post_id'];
		$chan   = (string) $row['integration_id'];

		if ( 'published' === $status ) {
			$this->plugin->logger->info( 'sync', __( 'Published on the network.', 'social-relay-for-postiz' ), $post, $chan, array( 'url' => $changes['release_url'] ?? '' ) );
		} elseif ( 'error' === $status ) {
			$message = '' !== ( $changes['error'] ?? '' ) ? $changes['error'] : __( 'Postiz reports an error for this post.', 'social-relay-for-postiz' );
			$this->plugin->logger->error( 'sync', $message, $post, $chan );
		}
	}
}
