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
	}

	/**
	 * Cancels pending posts in Postiz when a published post goes back to draft, private or trash.
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
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$profile = $this->plugin->profiles->for_post_type( $post->post_type );
		if ( ! $profile['enabled'] || ! get_post_meta( $post_id, PostMeta::ENABLED, true ) ) {
			return;
		}

		$was_published = $post_before instanceof WP_Post && 'publish' === $post_before->post_status;

		if ( $was_published && ! ( $profile['share_on_update'] && get_post_meta( $post_id, PostMeta::RESHARE, true ) ) ) {
			return;
		}

		/**
		 * Filters whether a post should be shared on this save.
		 *
		 * @param bool    $should  Default decision.
		 * @param WP_Post $post    Post.
		 * @param bool    $update  Whether this is an update.
		 */
		if ( ! apply_filters( 'socialrelaycvi_should_share', true, $post, $update ) ) {
			return;
		}

		$this->plugin->queue->enqueue_share( $post_id, self::DELAY );
	}
}
