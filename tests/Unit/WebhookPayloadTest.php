<?php
/**
 * Tests for WebhookPayload.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Sync\WebhookPayload;

/**
 * What the Postiz webhook body says, and whether a site can be reached.
 */
final class WebhookPayloadTest extends TestCase {

	public function test_reads_the_list_postiz_sends(): void {
		$body = array(
			array(
				'id'          => 'cmwb3m08w000ert7n2nm6tb6l',
				'state'       => 'PUBLISHED',
				'releaseURL'  => 'https://example.com/p/1',
				'integration' => array( 'id' => 'abc' ),
			),
			array( 'id' => 'cmwb3m08w000ert7n2nm6tb6l' ),
			array( 'id' => 'bad id; drop table' ),
			'not a post',
		);

		$this->assertSame(
			array(
				'posts' => 3,
				'ids'   => array( 'cmwb3m08w000ert7n2nm6tb6l' ),
			),
			WebhookPayload::parse( $body )
		);
	}

	public function test_reads_a_single_post_or_a_posts_key(): void {
		$this->assertSame( array( 'abcdef12' ), WebhookPayload::parse( array( 'id' => 'abcdef12' ) )['ids'] );
		$this->assertSame( array( 'abcdef12' ), WebhookPayload::parse( array( 'posts' => array( array( 'id' => 'abcdef12' ) ) ) )['ids'] );
	}

	public function test_anything_else_gives_nothing(): void {
		$this->assertSame(
			array(
				'posts' => 0,
				'ids'   => array(),
			),
			WebhookPayload::parse( null )
		);
		$this->assertSame( array(), WebhookPayload::parse( array() )['ids'] );
	}

	public function test_local_addresses(): void {
		foreach ( array( 'http://localhost:8890', 'http://mysite.test', 'https://shop.local', 'http://192.168.1.20', 'http://10.0.0.5', 'http://127.0.0.1', 'http://intranet' ) as $url ) {
			$this->assertTrue( WebhookPayload::is_local_url( $url ), $url );
		}

		foreach ( array( 'https://example.org', 'https://news.example.co.uk/blog', 'http://8.8.8.8' ) as $url ) {
			$this->assertFalse( WebhookPayload::is_local_url( $url ), $url );
		}
	}
}
