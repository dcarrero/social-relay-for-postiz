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
 * send, and right away for the posts a Postiz webhook call names. The webhook only
 * says what to check: the data always comes from the API.
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
		add_action( Queue::HOOK_SYNC_NAMED, array( $this, 'run_named' ), 10, 1 );
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
		$this->plugin->shares->expire_stale();

		$rows = $this->plugin->shares->syncable();
		if ( array() === $rows ) {
			return;
		}

		$this->sync_rows( $rows );
	}

	/**
	 * Coalesced sync job for the Postiz posts webhook calls named: a narrower request to Postiz.
	 *
	 * The stored ids are dropped only after Postiz answered; on failure they stay and a
	 * full sync is queued.
	 *
	 * @param mixed $legacy Ids of a job queued by an older version (one job per call).
	 */
	public function run_named( mixed $legacy = null ): void {
		if ( is_array( $legacy ) && array() !== $legacy ) {
			$this->plugin->queue->enqueue_named_sync( array_values( array_map( 'strval', $legacy ) ) );
			return;
		}

		$snapshot = $this->plugin->queue->begin_named_sync();
		if ( null === $snapshot ) {
			return;
		}

		if ( 0 !== $snapshot['full'] ) {
			// A call named no share we know: check every pending one.
			$this->plugin->shares->expire_stale();
			$rows = $this->plugin->shares->syncable();
		} else {
			$rows = array_values(
				array_filter(
					$this->plugin->shares->by_postiz_ids( array_map( 'strval', array_keys( $snapshot['ids'] ) ) ),
					static fn ( array $row ): bool => in_array( (string) $row['status'], array( 'queued', 'draft' ), true )
				)
			);
		}

		if ( array() !== $rows && ! $this->sync_rows( $rows ) ) {
			$this->plugin->queue->enqueue_sync( MINUTE_IN_SECONDS );
			return;
		}

		$this->plugin->queue->finish_named_sync( $snapshot );
	}

	/**
	 * Asks Postiz about these shares and records what changed.
	 *
	 * @param list<array<string, mixed>> $rows Share rows.
	 * @return bool False when Postiz could not be asked.
	 */
	private function sync_rows( array $rows ): bool {
		$gateway = $this->plugin->gateway();
		if ( null === $gateway ) {
			return false;
		}

		$times           = array_map( static fn ( array $row ): int => (int) strtotime( (string) $row['publish_at'] . ' UTC' ), $rows );
		[ $start, $end ] = StatusMapper::range( $times );

		$response = $gateway->posts( $start, $end );
		if ( is_wp_error( $response ) ) {
			$this->plugin->logger->error( 'sync', $response->get_error_message() );
			return false;
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
				// Gone from Postiz a day after its date: deleted there. Stop showing it as pending.
				if ( strtotime( (string) $row['publish_at'] . ' UTC' ) < time() - DAY_IN_SECONDS ) {
					$this->plugin->shares->update_row(
						(int) $row['id'],
						array(
							'status' => 'cancelled',
							'error'  => __( 'No longer in Postiz.', 'social-relay-for-postiz' ),
						)
					);
				}
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

		return true;
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
