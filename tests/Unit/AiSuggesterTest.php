<?php
/**
 * AiSuggester tests.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Sharing\AiSuggester;

/**
 * Cleaning of the model output.
 */
final class AiSuggesterTest extends TestCase {

	/**
	 * Quotes and code fences around the answer are removed.
	 */
	public function test_strips_quotes_and_fences(): void {
		$this->assertSame( "Hola mundo\n\n{url}", AiSuggester::clean( "```\n\"Hola mundo\n\n{url}\"\n```" ) );
		$this->assertSame( "Nueva colección\n{url}", AiSuggester::clean( "«Nueva colección\n{url}»" ) );
	}

	/**
	 * The link tag is always there, so UTM and short links apply.
	 */
	public function test_adds_url_tag_when_missing(): void {
		$this->assertSame( "Sin enlace\n\n{url}", AiSuggester::clean( 'Sin enlace' ) );
		$this->assertSame( "Con enlace {url} #tag", AiSuggester::clean( 'Con enlace {url} #tag' ) );
	}
}
