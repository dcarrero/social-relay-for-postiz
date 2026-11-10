<?php
/**
 * Background jobs on Action Scheduler (WP-Cron as fallback).
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Queue;

use SocialRelayCvi\Module;

/**
 * Schedules the plugin's background jobs.
 *
 * Every call to Postiz runs here, never during the editor's save request.
 */
final class Queue implements Module {

	public const GROUP        = 'socialrelaycvi';
	public const HOOK_SHARE   = 'socialrelaycvi_share_post';
	public const HOOK_CLEANUP = 'socialrelaycvi_cleanup';
	public const HOOK_CANCEL  = 'socialrelaycvi_cancel_post';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule_recurring' ) );
	}

	/**
	 * Queues a share job for a post.
	 *
	 * @param int $post_id Post id.
	 * @param int $delay   Seconds to wait before running.
	 * @param int $attempt Attempt number, starting at 1.
	 */
	public function enqueue_share( int $post_id, int $delay = 10, int $attempt = 1 ): void {
		$args = array( $post_id, $attempt );

		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			// Not using the $unique flag: Action Scheduler compares only hook and group, so it
			// would drop the jobs of other posts and the retries scheduled from a running job.
			if ( ! as_has_scheduled_action( self::HOOK_SHARE, $args, self::GROUP ) ) {
				as_schedule_single_action( time() + max( 0, $delay ), self::HOOK_SHARE, $args, self::GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_SHARE, $args ) ) {
			wp_schedule_single_event( time() + max( 0, $delay ), self::HOOK_SHARE, $args );
		}
	}

	/**
	 * Queues the cancellation in Postiz of a post's pending shares.
	 *
	 * @param int $post_id Post id.
	 */
	public function enqueue_cancel( int $post_id ): void {
		$args = array( $post_id );

		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_CANCEL, $args, self::GROUP ) ) {
				as_enqueue_async_action( self::HOOK_CANCEL, $args, self::GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_CANCEL, $args ) ) {
			wp_schedule_single_event( time(), self::HOOK_CANCEL, $args );
		}
	}

	/**
	 * Schedules the daily cleanup once.
	 */
	public function schedule_recurring(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_CLEANUP, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_CLEANUP, array(), self::GROUP, true );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_CLEANUP );
		}
	}

	/**
	 * Removes every scheduled job of the plugin.
	 */
	public static function unschedule_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::GROUP );
		}

		wp_clear_scheduled_hook( self::HOOK_CLEANUP );
		wp_unschedule_hook( self::HOOK_SHARE );
		wp_unschedule_hook( self::HOOK_CANCEL );
	}
}
