<?php
/**
 * Tests for ProfileTransfer.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Profiles\ProfileTransfer;

/**
 * Export and import of profiles.
 */
final class ProfileTransferTest extends TestCase {

	/**
	 * Terms of the "source" site.
	 *
	 * @var array<int, array{taxonomy: string, slug: string, name: string}>
	 */
	private const SOURCE_TERMS = array(
		3 => array(
			'taxonomy' => 'category',
			'slug'     => 'news',
			'name'     => 'News',
		),
		7 => array(
			'taxonomy' => 'post_tag',
			'slug'     => 'draft-only',
			'name'     => 'Draft only',
		),
	);

	/**
	 * A packed export.
	 *
	 * @return array<string, mixed>
	 */
	private function export(): array {
		return ProfileTransfer::pack(
			array(
				'post' => array(
					'enabled'       => true,
					'channels'      => array( 'abc' ),
					'include_terms' => array( 3, 99 ),
					'exclude_terms' => array( 7 ),
				),
			),
			static fn ( int $id ): ?array => self::SOURCE_TERMS[ $id ] ?? null,
			'https://example.com',
			'1.0.0'
		);
	}

	public function test_pack_replaces_term_ids_with_references(): void {
		$export = $this->export();

		$this->assertSame( ProfileTransfer::FORMAT, $export['format'] );
		$this->assertSame( 1, $export['version'] );
		$this->assertArrayNotHasKey( 'include_terms', $export['profiles']['post'] );
		// Term 99 no longer exists and is dropped.
		$this->assertSame( array( self::SOURCE_TERMS[3] ), $export['profiles']['post']['include'] );
		$this->assertSame( array( self::SOURCE_TERMS[7] ), $export['profiles']['post']['exclude'] );
		$this->assertSame( array( 'abc' ), $export['profiles']['post']['channels'] );
	}

	public function test_unpack_maps_terms_to_the_ids_of_this_site(): void {
		$data   = json_decode( (string) wp_json_encode_for_test( $this->export() ), true );
		$result = ProfileTransfer::unpack(
			$data,
			array( 'post', 'page' ),
			static fn ( string $taxonomy, string $slug ): ?int => 'category' === $taxonomy && 'news' === $slug ? 40 : null
		);

		$this->assertNotNull( $result );
		$this->assertSame( array( 40 ), $result['profiles']['post']['include_terms'] );
		$this->assertSame( array(), $result['profiles']['post']['exclude_terms'] );
		$this->assertSame( array( 'Draft only' ), $result['missing_terms'] );
		$this->assertSame( array(), $result['skipped_types'] );
	}

	public function test_unpack_skips_post_types_the_site_does_not_have(): void {
		$export                       = $this->export();
		$export['profiles']['course'] = array( 'enabled' => true );

		$result = ProfileTransfer::unpack( $export, array( 'post' ), static fn (): ?int => null );

		$this->assertNotNull( $result );
		$this->assertSame( array( 'post' ), array_keys( $result['profiles'] ) );
		$this->assertSame( array( 'course' ), $result['skipped_types'] );
	}

	public function test_unpack_rejects_other_files(): void {
		$finder = static fn (): ?int => null;

		$this->assertNull( ProfileTransfer::unpack( array( 'profiles' => array() ), array( 'post' ), $finder ) );
		$this->assertNull( ProfileTransfer::unpack( 'text', array( 'post' ), $finder ) );
		$this->assertNull(
			ProfileTransfer::unpack(
				array(
					'format'   => 'other-plugin',
					'profiles' => array(),
				),
				array( 'post' ),
				$finder
			)
		);
	}
}

/**
 * JSON round trip, as the browser does it.
 *
 * @param mixed $value Value.
 */
function wp_json_encode_for_test( mixed $value ): string|false {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WordPress in unit tests.
}
