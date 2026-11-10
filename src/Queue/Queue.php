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
	public const HOOK_SYNC    = 'socialrelaycvi_sync_status';

	/**
	 * Status sync of the Postiz posts a webhook call named (one job per call).
	 */
	public const HOOK_SYNC_NAMED = 'socialrelaycvi_sync_named';

	/**
	 * When the recurring jobs were last checked.
	 */
	public const OPTION_CHECKED = 'socialrelaycvi_recurring_checked';

	private const SCHEDULE_15 = 'socialrelaycvi_15min';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'schedule_recurring' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 15 minutes, only without Action Scheduler.
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
	 * @param int $delay   Seconds to wait before running (retries).
	 */
	public function enqueue_cancel( int $post_id, int $delay = 0 ): void {
		// A retry carries its time so the running cancel job does not count as "already queued".
		$args = 0 === $delay ? array( $post_id ) : array( $post_id, time() + $delay );

		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_CANCEL, $args, self::GROUP ) ) {
				as_schedule_single_action( time() + max( 0, $delay ), self::HOOK_CANCEL, $args, self::GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_CANCEL, $args ) ) {
			wp_schedule_single_event( time() + max( 0, $delay ), self::HOOK_CANCEL, $args );
		}
	}

	/**
	 * Queues a status sync, unless one is already waiting.
	 *
	 * @param int $delay Seconds to wait before running.
	 */
	public function enqueue_sync( int $delay = 0 ): void {
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_SYNC, array( 'single' ), self::GROUP ) ) {
				as_schedule_single_action( time() + max( 0, $delay ), self::HOOK_SYNC, array( 'single' ), self::GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_SYNC, array( 'single' ) ) ) {
			wp_schedule_single_event( time() + max( 0, $delay ), self::HOOK_SYNC, array( 'single' ) );
		}
	}

	/**
	 * Schedules the daily cleanup once.
	 */
	public function schedule_recurring(): void {
		// Checked at most once an hour (autoloaded option): not on every page view.
		$checked = (int) get_option( self::OPTION_CHECKED, 0 );
		if ( $checked > time() - HOUR_IN_SECONDS ) {
			return;
		}
		update_option( self::OPTION_CHECKED, time(), true );

		if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_CLEANUP, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_CLEANUP, array(), self::GROUP, true );
			}
			// Cheap when nothing is pending: the job returns after one query.
			if ( ! as_has_scheduled_action( self::HOOK_SYNC, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + 15 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, self::HOOK_SYNC, array(), self::GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_CLEANUP );
		}
		if ( ! wp_next_scheduled( self::HOOK_SYNC ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, self::SCHEDULE_15, self::HOOK_SYNC );
		}
	}

	/**
	 * Adds a 15-minute WP-Cron schedule, used when Action Scheduler is not available.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function cron_schedules( array $schedules ): array {
		$schedules[ self::SCHEDULE_15 ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'social-relay-for-postiz' ),
		);

		return $schedules;
	}

	/**
	 * Queues, right away, a status sync of the Postiz posts a webhook call named.
	 *
	 * One job per call with its own ids: calls that arrive together do not overwrite
	 * each other, and a sync already waiting (e.g. two minutes after a share) does not
	 * delay this one.
	 *
	 * @param list<string> $postiz_ids Postiz post ids.
	 */
	public function enqueue_named_sync( array $postiz_ids ): void {
		$args = array( array_values( $postiz_ids ) );

		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_SYNC_NAMED, $args, self::GROUP ) ) {
				as_enqueue_async_action( self::HOOK_SYNC_NAMED, $args, self::GROUP );
			}
			return;
		}

		wp_schedule_single_event( time(), self::HOOK_SYNC_NAMED, $args );
	}

	/**
	 * Removes every scheduled job of the plugin.
	 */
	public static function unschedule_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::GROUP );
		}

		wp_clear_scheduled_hook( self::HOOK_CLEANUP );
		delete_option( self::OPTION_CHECKED );
		wp_unschedule_hook( self::HOOK_SHARE );
		wp_unschedule_hook( self::HOOK_CANCEL );
		wp_unschedule_hook( self::HOOK_SYNC );
		wp_unschedule_hook( self::HOOK_SYNC_NAMED );
	}
}
