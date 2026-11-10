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
 * Cache deletions seen.
 *
 * @var list<string>
 */
$GLOBALS['socialrelaycvi_test_cache_deletes'] = array();

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

/**
 * Test double for wp_cache_delete().
 *
 * @param string $key   Key.
 * @param string $group Group.
 */
function wp_cache_delete( string $key, string $group = '' ): bool {
	$GLOBALS['socialrelaycvi_test_cache_deletes'][] = $group . ':' . $key;
	return true;
}

/**
 * Test double for wp_generate_password().
 *
 * @param int  $length Length.
 * @param bool $special Ignored.
 */
function wp_generate_password( int $length = 12, bool $special = true ): string {
	return substr( bin2hex( random_bytes( $length ) ), 0, $length );
}

/**
 * Minimal wpdb for the mutex: rows of the options table keyed by name.
 */
final class FakeWpdb {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	public string $options = 'wp_options';

	/**
	 * Rows.
	 *
	 * @var array<string, string>
	 */
	public array $rows = array();

	/**
	 * Every lock taken, in order.
	 *
	 * @var list<string>
	 */
	public array $taken = array();

	/**
	 * Keeps the query and its arguments together.
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Arguments.
	 */
	public function prepare( string $query, ...$args ): string {
		return (string) json_encode( array( $query, $args ) );
	}

	/**
	 * INSERT IGNORE only.
	 *
	 * @param string $sql Prepared query.
	 */
	public function query( string $sql ): int {
		[ $query, $args ] = json_decode( $sql, true );
		if ( ! str_starts_with( $query, 'INSERT IGNORE' ) || isset( $this->rows[ $args[0] ] ) ) {
			return 0;
		}
		$this->rows[ $args[0] ] = $args[1];
		$this->taken[]          = $args[0];
		return 1;
	}

	/**
	 * SELECT option_value only.
	 *
	 * @param string $sql Prepared query.
	 */
	public function get_var( string $sql ): ?string {
		[ , $args ] = json_decode( $sql, true );
		return $this->rows[ $args[0] ] ?? null;
	}

	/**
	 * Delete by name and value.
	 *
	 * @param string               $table Table.
	 * @param array<string, string> $where Conditions.
	 */
	public function delete( string $table, array $where ): int {
		if ( ( $this->rows[ $where['option_name'] ] ?? null ) !== $where['option_value'] ) {
			return 0;
		}
		unset( $this->rows[ $where['option_name'] ] );
		return 1;
	}
}

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Postiz\FakeWpdb;
use SocialRelayCvi\Postiz\RequestCounter;

/**
 * Counts requests in the last hour.
 */
final class RequestCounterTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['socialrelaycvi_test_options']       = array();
		$GLOBALS['socialrelaycvi_test_cache_deletes'] = array();
		unset( $GLOBALS['wpdb'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
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

	public function test_hit_reads_the_stored_value_under_a_lock_and_frees_it(): void {
		$db              = new FakeWpdb();
		$GLOBALS['wpdb'] = $db;

		RequestCounter::hit();

		$this->assertSame( array( RequestCounter::LOCK_PREFIX . 'post_requests' ), $db->taken );
		$this->assertSame( array(), $db->rows, 'The lock is released.' );
		$this->assertSame( array( 'options:' . RequestCounter::OPTION ), $GLOBALS['socialrelaycvi_test_cache_deletes'], 'The cached copy is dropped before reading.' );
		$this->assertSame( 1, RequestCounter::count() );
	}

	public function test_an_expired_lock_is_taken_over(): void {
		$db              = new FakeWpdb();
		$key             = RequestCounter::LOCK_PREFIX . 'post_requests';
		$db->rows[ $key ] = ( time() - 5 ) . ':crashed';
		$GLOBALS['wpdb'] = $db;

		RequestCounter::hit();

		$this->assertSame( array( $key ), $db->taken );
		$this->assertArrayNotHasKey( $key, $db->rows );
		$this->assertSame( 1, RequestCounter::count() );
	}

	public function test_a_lock_held_by_another_process_is_not_removed(): void {
		$db              = new FakeWpdb();
		$key             = RequestCounter::LOCK_PREFIX . 'busy';
		$other           = ( time() + 30 ) . ':other';
		$db->rows[ $key ] = $other;
		$GLOBALS['wpdb'] = $db;

		// After the wait the work still runs, and the other lock stays.
		$this->assertSame( 'done', RequestCounter::with_lock( 'busy', static fn (): string => 'done' ) );
		$this->assertSame( $other, $db->rows[ $key ] );
	}
}
