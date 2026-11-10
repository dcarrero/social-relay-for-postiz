<?php
/**
 * Tests for ShareService::content_image_ids() and the per-network image limit.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Postiz\ProviderRules;
use SocialRelayCvi\Sharing\ShareService;

/**
 * Which images of the content go with the post.
 */
final class ContentImagesTest extends TestCase {

	public function test_finds_block_and_classic_images_in_order(): void {
		$content = implode(
			"\n",
			array(
				'<!-- wp:image {"id":12,"sizeSlug":"large"} --><figure><img class="wp-image-12" src="a.jpg"></figure><!-- /wp:image -->',
				'<!-- wp:gallery {"ids":[20,21]} /-->',
				'<!-- wp:media-text {"mediaId":30} --><!-- /wp:media-text -->',
				'<p><img class="alignnone wp-image-40" src="b.jpg"></p>',
				'<!-- wp:paragraph --><p>No image</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( array( 12, 20, 21, 30, 40 ), ShareService::content_image_ids( $content ) );
	}

	public function test_no_images(): void {
		$this->assertSame( array(), ShareService::content_image_ids( '<p>Text only</p>' ) );
	}

	public function test_image_limits_per_network(): void {
		$this->assertSame( 4, ProviderRules::max_images( 'x' ) );
		$this->assertSame( 4, ProviderRules::max_images( 'bluesky' ) );
		$this->assertSame( 9, ProviderRules::max_images( 'linkedin-page' ) );
		$this->assertSame( 10, ProviderRules::max_images( 'instagram' ) );
		$this->assertSame( 1, ProviderRules::max_images( 'pinterest' ) );
	}
}
