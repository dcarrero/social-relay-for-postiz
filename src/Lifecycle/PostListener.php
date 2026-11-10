<?php
/**
 * Queues a share when a post is published.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Lifecycle;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\PostMeta;
use SocialRelayCvi\Sharing\ShareService;
use WP_Post;

/**
 * Watches post saves and queues the share job.
 *
 * Uses wp_after_insert_post, which runs after meta and terms are saved (including
 * block editor REST saves and scheduled posts published by cron).
 */
final class PostListener implements Module {

	/**
	 * Seconds between the save and the job, so late meta updates land first.
	 */
	private const DELAY = 10;

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
		add_action( 'wp_after_insert_post', array( $this, 'on_save' ), 20, 4 );
		add_action( 'transition_post_status', array( $this, 'on_unpublish' ), 10, 3 );
		// Deleting permanently (REST force=true, WP-CLI --force, no trash) skips the status transition.
		add_action( 'before_delete_post', array( $this, 'on_delete' ), 10, 2 );
	}

	/**
	 * Cancels pending posts in Postiz when a post is deleted without going through the trash.
	 *
	 * The cancel job needs only the post id and the plugin's own records, so it still works
	 * after the post is gone.
	 *
	 * @param int          $post_id Post id.
	 * @param WP_Post|null $post    Post being deleted.
	 */
	public function on_delete( int $post_id, ?WP_Post $post = null ): void {
		if ( ! $post instanceof WP_Post || array() === $this->plugin->shares->cancellable_batches( $post_id ) ) {
			return;
		}

		/** This filter is documented in src/Lifecycle/PostListener.php */
		if ( apply_filters( 'socialrelaycvi_cancel_on_unpublish', true, $post, 'deleted' ) ) {
			$this->plugin->queue->enqueue_cancel( $post_id );
		}
	}

	/**
	 * Cancels pending posts in Postiz when a published post goes back to draft, private or trash.
	 * Permanent deletion is handled by on_delete().
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public function on_unpublish( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $old_status || 'publish' === $new_status ) {
			return;
		}

		/**
		 * Filters whether pending Postiz posts are cancelled when a post is unpublished.
		 *
		 * @param bool    $cancel     Default true.
		 * @param WP_Post $post       Post.
		 * @param string  $new_status New status.
		 */
		if ( apply_filters( 'socialrelaycvi_cancel_on_unpublish', true, $post, $new_status ) ) {
			$this->plugin->queue->enqueue_cancel( $post->ID );
		}
	}

	/**
	 * Decides whether this save should be shared.
	 *
	 * @param int          $post_id     Post id.
	 * @param WP_Post      $post        Post after the save.
	 * @param bool         $update      Whether this is an update.
	 * @param WP_Post|null $post_before Post before the save, null for new posts.
	 */
	public function on_save( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before ): void {
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! ShareService::may_share_protected( $post ) ) {
			return;
		}

		$profile = $this->plugin->profiles->for_post_type( $post->post_type );
		if ( ! $profile['enabled'] || ! get_post_meta( $post_id, PostMeta::ENABLED, true ) ) {
			return;
		}

		$was_published = $post_before instanceof WP_Post && 'publish' === $post_before->post_status;

		if ( $was_published && ! ( $profile['share_on_update'] && get_post_meta( $post_id, PostMeta::RESHARE, true ) ) ) {
			// A "share again" flag left while the profile did not allow it must not fire later.
			delete_post_meta( $post_id, PostMeta::RESHARE );
			return;
		}

		// Sharing again on update follows the same once-a-minute rule as the "Share again" button.
		if ( $was_published && ! ShareService::claim_reshare_slot( $post_id ) ) {
			return;
		}

		// Imports, migrations and posts created by code with an old date are not news:
		// sharing them would flood the networks (and the 90 requests/hour of Postiz).
		$importing = defined( 'WP_IMPORTING' ) && WP_IMPORTING;
		$old_new   = ! $post_before instanceof WP_Post && strtotime( $post->post_date_gmt . ' UTC' ) < time() - DAY_IN_SECONDS;

		/**
		 * Filters whether a post should be shared on this save.
		 *
		 * Default false for imports (WP_IMPORTING) and for posts created already published
		 * with a date older than a day; true otherwise.
		 *
		 * @param bool    $should  Default decision.
		 * @param WP_Post $post    Post.
		 * @param bool    $update  Whether this is an update.
		 */
		if ( ! apply_filters( 'socialrelaycvi_should_share', ! $importing && ! $old_new, $post, $update ) ) {
			return;
		}

		$this->plugin->queue->enqueue_share( $post_id, self::DELAY );
	}
}
