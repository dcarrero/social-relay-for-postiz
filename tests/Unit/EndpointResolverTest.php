<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Postiz\EndpointResolver;

final class EndpointResolverTest extends TestCase {

	/**
	 * @return array<string, array{string, list<string>}>
	 */
	public static function urls(): array {
		return array(
			'self-hosted root'       => array( 'https://postiz.example.com/', array( 'https://postiz.example.com/api/public/v1', 'https://postiz.example.com/public/v1' ) ),
			'no scheme'              => array( 'postiz.example.com', array( 'https://postiz.example.com/api/public/v1', 'https://postiz.example.com/public/v1' ) ),
			'backend /api'           => array( 'https://postiz.example.com/api', array( 'https://postiz.example.com/api/public/v1' ) ),
			'full base'              => array( 'https://api.example.com/public/v1/', array( 'https://api.example.com/public/v1' ) ),
			'cloud app'              => array( 'https://platform.postiz.com/launches', array( EndpointResolver::CLOUD_BASE ) ),
			'cloud api'              => array( 'https://api.postiz.com', array( EndpointResolver::CLOUD_BASE ) ),
			'query and fragment'     => array( 'https://postiz.example.com/?x=1#a', array( 'https://postiz.example.com/api/public/v1', 'https://postiz.example.com/public/v1' ) ),
			'local http with port'   => array( 'http://localhost:5000', array( 'http://localhost:5000/api/public/v1', 'http://localhost:5000/public/v1' ) ),
			'empty'                  => array( '  ', array() ),
		);
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider( 'urls' )]
	public function test_candidates( string $url, array $expected ): void {
		$this->assertSame( $expected, EndpointResolver::candidates( $url ) );
	}
}
