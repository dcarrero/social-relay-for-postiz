<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Sharing\Utm;

final class UtmTest extends TestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function urls(): array {
		return array(
			'plain'             => array( 'https://site.test/post/', 'https://site.test/post/?utm_source=x&utm_medium=social' ),
			'existing query'    => array( 'https://site.test/?p=1', 'https://site.test/?p=1&utm_source=x&utm_medium=social' ),
			'fragment kept'     => array( 'https://site.test/post/#top', 'https://site.test/post/?utm_source=x&utm_medium=social#top' ),
			'existing utm wins' => array( 'https://site.test/?utm_source=news', 'https://site.test/?utm_source=news&utm_medium=social' ),
			'empty url'         => array( '', '' ),
		);
	}

	#[DataProvider( 'urls' )]
	public function test_add( string $url, string $expected ): void {
		$this->assertSame( $expected, Utm::add( $url, array( 'utm_source' => 'x', 'utm_medium' => 'social', 'utm_campaign' => '' ) ) );
	}

	public function test_encodes_values(): void {
		$this->assertSame( 'https://s.test/?utm_campaign=colecci%C3%B3n%20oto%C3%B1o', Utm::add( 'https://s.test/', array( 'utm_campaign' => 'colección otoño' ) ) );
	}
}
