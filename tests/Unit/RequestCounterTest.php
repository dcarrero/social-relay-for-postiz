<?php
/**
 * RequestCounter tests.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

// Option storage for the namespaced calls of RequestCounter (no WordPress in unit tests).
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * In-memory options.
 *
 * @var array<string, mixed>
 */
$GLOBALS['socialrelaycvi_test_options'] = array();

/**
 * Test double for get_option().
 *
 * @param string $name    Option.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( string $name, $default = false ) {
	return $GLOBALS['socialrelaycvi_test_options'][ $name ] ?? $default;
}

/**
 * Test double for update_option().
 *
 * @param string $name     Option.
 * @param mixed  $value    Value.
 * @param bool   $autoload Ignored.
 */
function update_option( string $name, $value, bool $autoload = false ): bool {
	$GLOBALS['socialrelaycvi_test_options'][ $name ] = $value;
	return true;
}

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Postiz\RequestCounter;

/**
 * Counts requests in the last hour.
 */
final class RequestCounterTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['socialrelaycvi_test_options'] = array();
	}

	public function test_counts_each_hit(): void {
		RequestCounter::hit();
		RequestCounter::hit();
		RequestCounter::hit();

		$this->assertSame( 3, RequestCounter::count() );
	}

	public function test_ignores_requests_older_than_the_window(): void {
		$GLOBALS['socialrelaycvi_test_options'][ RequestCounter::OPTION ] = array( time() - 7200, time() - 4000, time() - 60 );

		$this->assertSame( 1, RequestCounter::count() );

		RequestCounter::hit();
		$this->assertCount( 2, $GLOBALS['socialrelaycvi_test_options'][ RequestCounter::OPTION ], 'Old times are dropped on write.' );
	}

	public function test_storage_is_capped(): void {
		for ( $i = 0; $i < RequestCounter::LIMIT * 3; $i++ ) {
			RequestCounter::hit();
		}

		$this->assertSame( RequestCounter::LIMIT * 2, RequestCounter::count() );
	}
}
