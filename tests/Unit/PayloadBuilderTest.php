<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Sharing\PayloadBuilder;

final class PayloadBuilderTest extends TestCase {

	public function test_builds_one_request_for_all_channels(): void {
		$payload = PayloadBuilder::build(
			array(
				array(
					'integration_id' => 'x1',
					'identifier'     => 'x',
					'parts'          => array( 'Parte 1', 'Parte 2' ),
					'images'         => array( array( 'id' => 'img', 'path' => 'https://u/1.png' ) ),
				),
				array(
					'integration_id' => 'b1',
					'identifier'     => 'bluesky',
					'parts'          => array( 'Hola' ),
				),
			),
			PayloadBuilder::TYPE_SCHEDULE,
			1765706400,
			'wp-abc-1-1',
			false,
			array( 'otoño', 'otoño' )
		);

		$this->assertSame( 'schedule', $payload['type'] );
		$this->assertSame( '2025-12-14T10:00:00.000Z', $payload['date'] );
		$this->assertFalse( $payload['shortLink'] );
		$this->assertSame( array( array( 'value' => 'otoño', 'label' => 'otoño' ) ), $payload['tags'] );
		$this->assertCount( 2, $payload['posts'] );

		$x = $payload['posts'][0];
		$this->assertSame( array( 'id' => 'x1' ), $x['integration'] );
		$this->assertSame( 'wp-abc-1-1', $x['group'] );
		$this->assertCount( 2, $x['value'] );
		$this->assertCount( 1, $x['value'][0]['image'], 'Images go on the first part only.' );
		$this->assertSame( array(), $x['value'][1]['image'] );
		$this->assertSame( 'x', $x['settings']['__type'] );
		$this->assertSame( 'everyone', $x['settings']['who_can_reply_post'] );

		$this->assertSame( array( '__type' => 'bluesky' ), $payload['posts'][1]['settings'] );
	}

	public function test_custom_settings_override_defaults(): void {
		$payload = PayloadBuilder::build(
			array(
				array(
					'integration_id' => 'ig',
					'identifier'     => 'instagram',
					'parts'          => array( 'Foto' ),
					'settings'       => array( 'post_type' => 'story' ),
				),
			),
			PayloadBuilder::TYPE_NOW,
			0,
			'g'
		);

		$this->assertSame( 'story', $payload['posts'][0]['settings']['post_type'] );
		$this->assertSame( 'instagram', $payload['posts'][0]['settings']['__type'] );
	}

	public function test_skips_channels_with_empty_messages(): void {
		$payload = PayloadBuilder::build(
			array(
				array( 'integration_id' => 'a', 'identifier' => 'mastodon', 'parts' => array( '  ' ) ),
				array( 'integration_id' => 'b', 'identifier' => 'mastodon', 'parts' => array( 'Texto' ) ),
			),
			PayloadBuilder::TYPE_NOW,
			0,
			'g'
		);

		$this->assertCount( 1, $payload['posts'] );
		$this->assertSame( array( 'id' => 'b' ), $payload['posts'][0]['integration'] );
	}

	public function test_rejects_empty_batches_and_unknown_types(): void {
		$this->expectException( \InvalidArgumentException::class );
		PayloadBuilder::build( array(), PayloadBuilder::TYPE_NOW, 0, 'g' );
	}

	public function test_rejects_unknown_type(): void {
		$this->expectException( \InvalidArgumentException::class );
		PayloadBuilder::build( array( array( 'integration_id' => 'a', 'identifier' => 'x', 'parts' => array( 'a' ) ) ), 'later', 0, 'g' );
	}
}
