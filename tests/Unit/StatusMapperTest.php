<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Sync\StatusMapper;

final class StatusMapperTest extends TestCase {

	public function test_maps_states(): void {
		$this->assertSame( 'published', StatusMapper::status( 'PUBLISHED' ) );
		$this->assertSame( 'queued', StatusMapper::status( 'queue' ) );
		$this->assertNull( StatusMapper::status( 'DELETED' ) );
	}

	public function test_published_brings_release_url(): void {
		$changes = StatusMapper::changes(
			array( 'status' => 'queued', 'release_url' => '', 'error' => '' ),
			array( 'state' => 'PUBLISHED', 'releaseURL' => 'https://x.com/a/status/1', 'error' => null )
		);

		$this->assertSame( array( 'status' => 'published', 'release_url' => 'https://x.com/a/status/1' ), $changes );
	}

	public function test_error_brings_the_platform_message(): void {
		$changes = StatusMapper::changes(
			array( 'status' => 'queued', 'release_url' => '', 'error' => '' ),
			array( 'state' => 'ERROR', 'error' => 'Token expired' )
		);

		$this->assertSame( array( 'status' => 'error', 'error' => 'Token expired' ), $changes );
	}

	public function test_no_changes_when_equal(): void {
		$this->assertSame(
			array(),
			StatusMapper::changes( array( 'status' => 'queued', 'release_url' => '', 'error' => '' ), array( 'state' => 'QUEUE' ) )
		);
	}

	public function test_unknown_state_changes_nothing(): void {
		$this->assertSame( array(), StatusMapper::changes( array( 'status' => 'queued' ), array( 'state' => 'WHATEVER' ) ) );
	}

	public function test_range_adds_a_day_each_side(): void {
		$this->assertSame(
			array( '2025-12-13T10:00:00.000Z', '2025-12-16T10:00:00.000Z' ),
			StatusMapper::range( array( 1765706400, 1765792800 ) )
		);
	}
}
