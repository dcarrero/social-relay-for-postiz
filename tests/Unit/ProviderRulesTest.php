<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Postiz\ProviderRules;

final class ProviderRulesTest extends TestCase {

	public function test_sanitize_keeps_valid_values_only(): void {
		$clean = ProviderRules::sanitize_settings(
			'x',
			array(
				'who_can_reply_post' => 'hackers',
				'community'          => "  https://x.com/i/communities/1\n ",
				'unknown'            => 'drop me',
			)
		);

		$this->assertSame( array( 'community' => 'https://x.com/i/communities/1' ), $clean );
	}

	public function test_sanitize_select_and_bool(): void {
		$this->assertSame( array( 'post_type' => 'story' ), ProviderRules::sanitize_settings( 'instagram', array( 'post_type' => 'story' ) ) );
		$this->assertSame( array( 'post_as_images_carousel' => false ), ProviderRules::sanitize_settings( 'linkedin-page', array( 'post_as_images_carousel' => '0' ) ) );
		$this->assertSame( array( 'post_as_images_carousel' => true ), ProviderRules::sanitize_settings( 'linkedin-page', array( 'post_as_images_carousel' => '1' ) ) );
	}

	public function test_required_fields(): void {
		$this->assertSame( array( 'Board ID' ), ProviderRules::missing_required( 'pinterest', array() ) );
		$this->assertSame( array(), ProviderRules::missing_required( 'pinterest', array( 'board' => '123' ) ) );
	}

	public function test_utm_source_names(): void {
		$this->assertSame( 'linkedin', ProviderRules::utm_source( 'linkedin-page' ) );
		$this->assertSame( 'bluesky', ProviderRules::utm_source( 'bluesky' ) );
	}

	public function test_default_settings_always_carry_type(): void {
		$this->assertSame( array( '__type' => 'mastodon' ), ProviderRules::default_settings( 'mastodon' ) );
	}
}
