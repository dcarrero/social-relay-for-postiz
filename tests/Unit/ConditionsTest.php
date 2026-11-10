<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Sharing\Conditions;

final class ConditionsTest extends TestCase {

	public function test_no_rules_passes(): void {
		$this->assertTrue( Conditions::passes( array( 1, 2 ), array(), array() ) );
	}

	public function test_include_needs_any_term(): void {
		$this->assertTrue( Conditions::passes( array( 1, 2 ), array( 2, 9 ), array() ) );
		$this->assertFalse( Conditions::passes( array( 1 ), array( 2, 9 ), array() ) );
	}

	public function test_exclude_wins_over_include(): void {
		$this->assertFalse( Conditions::passes( array( 2, 5 ), array( 2 ), array( 5 ) ) );
	}

	public function test_post_without_terms_fails_include(): void {
		$this->assertFalse( Conditions::passes( array(), array( 3 ), array() ) );
	}
}
