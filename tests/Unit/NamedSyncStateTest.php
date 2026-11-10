<?php
/**
 * Tests for the coalesced named-sync state in Queue.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Queue\Queue;

/**
 * Webhook calls merge their ids; a run drops only what it checked.
 */
final class NamedSyncStateTest extends TestCase {

	public function test_calls_merge_their_ids(): void {
		$state = Queue::parse_named( null );
		$state = Queue::merge_named( $state, array( 'a', 'b' ), false );
		$state = Queue::merge_named( $state, array( 'b', 'c', '' ), false );

		$this->assertSame(
			array(
				'a' => 1,
				'b' => 2,
				'c' => 2,
			),
			$state['ids']
		);
		$this->assertSame( 0, $state['full'] );
		$this->assertSame( 2, $state['seq'] );
	}

	public function test_cap_drops_the_oldest_ids(): void {
		$ids   = array_map( static fn ( int $i ): string => 'id' . $i, range( 1, 60 ) );
		$state = Queue::merge_named( Queue::parse_named( null ), $ids, false );
		$state = Queue::merge_named( $state, array( 'id5' ), false );

		$this->assertCount( Queue::NAMED_CAP, $state['ids'] );
		$this->assertArrayNotHasKey( 'id1', $state['ids'] );
		$this->assertArrayHasKey( 'id5', $state['ids'] );
		$this->assertSame( 'id5', array_key_last( $state['ids'] ) );
	}

	public function test_settle_keeps_ids_named_again_during_the_run(): void {
		$state    = Queue::merge_named( Queue::parse_named( null ), array( 'a', 'b' ), true );
		$snapshot = array(
			'ids'  => $state['ids'],
			'full' => $state['full'],
		);

		// A call arrives while Postiz is being asked.
		$state = Queue::merge_named( $state, array( 'b', 'c' ), false );
		$state = Queue::settle_named( $state, $snapshot );

		$this->assertSame(
			array(
				'b' => 2,
				'c' => 2,
			),
			$state['ids']
		);
		$this->assertSame( 0, $state['full'] );
	}

	public function test_settle_keeps_a_later_full_request(): void {
		$state    = Queue::merge_named( Queue::parse_named( null ), array(), true );
		$snapshot = array(
			'ids'  => $state['ids'],
			'full' => $state['full'],
		);
		$state    = Queue::merge_named( $state, array(), true );

		$this->assertSame( 2, Queue::settle_named( $state, $snapshot )['full'] );
	}

	public function test_parse_survives_garbage(): void {
		$this->assertSame(
			array(
				'ids'  => array( 'x' => 3 ),
				'full' => 0,
				'seq'  => 4,
				'last' => 0,
			),
			Queue::parse_named(
				array(
					'ids'  => array(
						'x' => 3,
						'y' => 'bad',
					),
					'full' => 'no',
					'seq'  => 4,
				)
			)
		);
		$this->assertSame( array(), Queue::parse_named( 'nope' )['ids'] );
	}

	public function test_runs_are_spaced(): void {
		$this->assertSame( 0, Queue::named_delay( 0, 1000 ) );
		$this->assertSame( 20, Queue::named_delay( 990, 1000 ) );
		$this->assertSame( 0, Queue::named_delay( 970, 1000 ) );
		// A start time in the future (clock change) never waits more than the spacing.
		$this->assertSame( Queue::NAMED_SPACING, Queue::named_delay( 5000, 1000 ) );
	}
}
