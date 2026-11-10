<?php
/**
 * PostMeta tests.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi;

use PHPUnit\Framework\TestCase;

/**
 * Test double for wp_timezone() in the plugin namespace (no WordPress in unit tests).
 */
function wp_timezone(): \DateTimeZone {
	return new \DateTimeZone( 'Europe/Madrid' );
}

/**
 * Per-post date of the "schedule" mode.
 */
final class PostMetaTest extends TestCase {

	/**
	 * Formats from the block editor and the classic metabox are kept as site time.
	 */
	public function test_accepts_editor_and_metabox_formats(): void {
		$this->assertSame( '2026-11-13T10:00:00', PostMeta::sanitize_datetime( '2026-11-13T10:00:00' ) );
		$this->assertSame( '2026-11-13T10:00:00', PostMeta::sanitize_datetime( '2026-11-13T10:00' ) );
		$this->assertSame( '2026-11-13T10:00:00', PostMeta::sanitize_datetime( ' 2026-11-13 10:00 ' ) );
	}

	/**
	 * Anything else is dropped, so the profile delay applies.
	 */
	public function test_rejects_other_values(): void {
		$this->assertSame( '', PostMeta::sanitize_datetime( '' ) );
		$this->assertSame( '', PostMeta::sanitize_datetime( 'tomorrow' ) );
		$this->assertSame( '', PostMeta::sanitize_datetime( '13/11/2026 10:00' ) );
	}
}
