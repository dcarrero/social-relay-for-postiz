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
	 * Status sync of the Postiz posts webhook calls named (one coalesced job, fixed args).
	 */
	public const HOOK_SYNC_NAMED = 'socialrelaycvi_sync_named';

	/**
	 * Pending ids of the named sync, as JSON: { ids: {id: seq}, full, seq, last }.
	 */
	public const OPTION_NAMED = 'socialrelaycvi_named_sync';

	/**
	 * Short lock that guards OPTION_NAMED (value: expiry time).
	 */
	public const OPTION_NAMED_LOCK = 'socialrelaycvi_named_sync_lock';

	/**
	 * Most ids a named sync carries.
	 */
	public const NAMED_CAP = 50;

	/**
	 * Least seconds between two named syncs.
	 */
	public const NAMED_SPACING = 30;

	private const NAMED_LOCK_TTL = 10;

	/**
	 * When the recurring jobs were last checked.
	 */
	public const OPTION_CHECKED = 'socialrelaycvi_recurring_checked';

	private const SCHEDULE_15 = 'socialrelaycvi_15min';

	/**
	 * Value of the lock this request holds.
	 *
	 * @var string
	 */
	private string $lock_until = '';

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
	 * Queues a status sync of the Postiz posts a webhook call named, coalesced.
	 *
	 * At most one named-sync job is pending: calls add their ids to the stored set and
	 * share that job, and runs are spaced NAMED_SPACING seconds apart, so repeated calls
	 * to the public URL cannot spend the Postiz quota.
	 *
	 * @param list<string> $postiz_ids Postiz post ids.
	 * @param bool         $full       Also check every pending share (the call named none we know).
	 */
	public function enqueue_named_sync( array $postiz_ids, bool $full = false ): void {
		if ( ! $this->lock_named() ) {
			// Lock busy for too long: the full sync covers these shares (one job at most).
			$this->enqueue_sync( MINUTE_IN_SECONDS );
			return;
		}

		try {
			$state = self::merge_named( self::read_named(), $postiz_ids, $full );
			self::write_named( $state );
			$this->schedule_named( self::named_delay( $state['last'], time() ) );
		} finally {
			$this->unlock_named();
		}
	}

	/**
	 * Starts a named-sync run: returns what to check and records the start time.
	 *
	 * @return array{ids: array<string, int>, full: int}|null Null when there is nothing to do
	 *         or it is too early (the job is then scheduled again).
	 */
	public function begin_named_sync(): ?array {
		if ( ! $this->lock_named() ) {
			$this->schedule_named( self::NAMED_SPACING );
			return null;
		}

		try {
			$state = self::read_named();
			if ( array() === $state['ids'] && 0 === $state['full'] ) {
				return null;
			}

			$delay = self::named_delay( $state['last'], time() );
			if ( $delay > 0 ) {
				$this->schedule_named( $delay );
				return null;
			}

			$state['last'] = time();
			self::write_named( $state );

			return array(
				'ids'  => $state['ids'],
				'full' => $state['full'],
			);
		} finally {
			$this->unlock_named();
		}
	}

	/**
	 * Ends a successful named-sync run: drops what it checked, keeps what arrived meanwhile.
	 *
	 * @param array{ids: array<string, int>, full: int} $snapshot What begin_named_sync() returned.
	 */
	public function finish_named_sync( array $snapshot ): void {
		// Lock busy: the ids stay stored and the next run checks them again.
		if ( ! $this->lock_named() ) {
			return;
		}

		try {
			self::write_named( self::settle_named( self::read_named(), $snapshot ) );
		} finally {
			$this->unlock_named();
		}
	}

	/**
	 * Adds ids to the stored state; each one is tagged with the call that last named it.
	 *
	 * @param array{ids: array<string, int>, full: int, seq: int, last: int} $state      State.
	 * @param list<string>                                                   $postiz_ids Postiz post ids.
	 * @param bool                                                           $full       Full check asked.
	 * @return array{ids: array<string, int>, full: int, seq: int, last: int}
	 */
	public static function merge_named( array $state, array $postiz_ids, bool $full ): array {
		$seq = $state['seq'] + 1;
		$ids = $state['ids'];

		foreach ( $postiz_ids as $id ) {
			$id = (string) $id;
			if ( '' === $id ) {
				continue;
			}
			// Re-added at the end, so the cap drops the oldest ids.
			unset( $ids[ $id ] );
			$ids[ $id ] = $seq;
		}

		return array(
			'ids'  => array_slice( $ids, -self::NAMED_CAP, null, true ),
			'full' => $full ? $seq : $state['full'],
			'seq'  => $seq,
			'last' => $state['last'],
		);
	}

	/**
	 * Removes what a run checked, unless a later call named it again.
	 *
	 * @param array{ids: array<string, int>, full: int, seq: int, last: int} $state    State.
	 * @param array{ids: array<string, int>, full: int}                      $snapshot What the run checked.
	 * @return array{ids: array<string, int>, full: int, seq: int, last: int}
	 */
	public static function settle_named( array $state, array $snapshot ): array {
		foreach ( $snapshot['ids'] as $id => $seq ) {
			if ( ( $state['ids'][ $id ] ?? null ) === $seq ) {
				unset( $state['ids'][ $id ] );
			}
		}
		if ( $state['full'] === $snapshot['full'] ) {
			$state['full'] = 0;
		}

		return $state;
	}

	/**
	 * Reads a stored state, whatever was stored.
	 *
	 * @param mixed $raw Decoded option value.
	 * @return array{ids: array<string, int>, full: int, seq: int, last: int}
	 */
	public static function parse_named( mixed $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$ids = array();

		foreach ( is_array( $raw['ids'] ?? null ) ? $raw['ids'] : array() as $id => $seq ) {
			if ( '' !== (string) $id && is_int( $seq ) ) {
				$ids[ (string) $id ] = $seq;
			}
		}

		return array(
			'ids'  => array_slice( $ids, -self::NAMED_CAP, null, true ),
			'full' => is_int( $raw['full'] ?? null ) ? $raw['full'] : 0,
			'seq'  => is_int( $raw['seq'] ?? null ) ? $raw['seq'] : 0,
			'last' => is_int( $raw['last'] ?? null ) ? $raw['last'] : 0,
		);
	}

	/**
	 * Seconds until the next named sync may run.
	 *
	 * @param int $last Start of the previous run.
	 * @param int $now  Now.
	 */
	public static function named_delay( int $last, int $now ): int {
		return max( 0, min( self::NAMED_SPACING, $last + self::NAMED_SPACING - $now ) );
	}

	/**
	 * Schedules the named-sync job unless one is already pending (a running one does not count).
	 *
	 * Called with the lock held, so parallel calls cannot both schedule.
	 *
	 * @param int $delay Seconds to wait before running.
	 */
	private function schedule_named( int $delay ): void {
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_get_scheduled_actions' ) ) {
			$pending = as_get_scheduled_actions(
				array(
					'hook'     => self::HOOK_SYNC_NAMED,
					'group'    => self::GROUP,
					'status'   => 'pending',
					'per_page' => 1,
				),
				'ids'
			);
			if ( array() !== $pending ) {
				return;
			}

			if ( $delay > 0 ) {
				as_schedule_single_action( time() + $delay, self::HOOK_SYNC_NAMED, array(), self::GROUP );
			} else {
				as_enqueue_async_action( self::HOOK_SYNC_NAMED, array(), self::GROUP );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_SYNC_NAMED, array() ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK_SYNC_NAMED, array() );
		}
	}

	/**
	 * Stored state, read from the database (not the object cache) while the lock is held.
	 *
	 * @return array{ids: array<string, int>, full: int, seq: int, last: int}
	 */
	private static function read_named(): array {
		global $wpdb;

		$raw = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, self::OPTION_NAMED ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh read under the lock.

		return self::parse_named( is_string( $raw ) ? json_decode( $raw, true ) : null );
	}

	/**
	 * Stores the state (not autoloaded).
	 *
	 * @param array{ids: array<string, int>, full: int, seq: int, last: int} $state State.
	 */
	private static function write_named( array $state ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)', $wpdb->options, self::OPTION_NAMED, (string) wp_json_encode( $state ), 'off' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Written under the lock.
		wp_cache_delete( self::OPTION_NAMED, 'options' );
	}

	/**
	 * Takes the short lock that guards the stored state.
	 *
	 * INSERT IGNORE on the unique option_name is atomic (add_option() is not: it upserts).
	 * The value is the expiry time, so a lock left by a crashed request frees itself.
	 */
	private function lock_named(): bool {
		global $wpdb;

		for ( $try = 0; $try < 20; $try++ ) {
			$until = time() + self::NAMED_LOCK_TTL;
			$taken = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, self::OPTION_NAMED_LOCK, (string) $until, 'off' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lock.
			if ( 1 === $taken ) {
				$this->lock_until = (string) $until;
				return true;
			}

			// Expired lock: remove it and try again.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value < %d', $wpdb->options, self::OPTION_NAMED_LOCK, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lock.
			usleep( 50000 );
		}

		return false;
	}

	/**
	 * Releases the lock, only if it is still ours.
	 */
	private function unlock_named(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, self::OPTION_NAMED_LOCK, $this->lock_until ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lock.
		$this->lock_until = '';
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
		delete_option( self::OPTION_NAMED );
		delete_option( self::OPTION_NAMED_LOCK );
		wp_unschedule_hook( self::HOOK_SHARE );
		wp_unschedule_hook( self::HOOK_CANCEL );
		wp_unschedule_hook( self::HOOK_SYNC );
		wp_unschedule_hook( self::HOOK_SYNC_NAMED );
	}
}
