<?php
declare( strict_types=1 );

namespace SocialRelayCvi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialRelayCvi\Templates\TemplateEngine;

final class TemplateEngineTest extends TestCase {

	private TemplateEngine $engine;

	protected function setUp(): void {
		$this->engine = new TemplateEngine();
	}

	public function test_replaces_tags_and_drops_unknown_ones(): void {
		$out = $this->engine->render( '{title} {nope} {url}', array( 'title' => 'Hola', 'url' => 'https://x.test' ) );

		$this->assertSame( 'Hola  https://x.test', $out );
	}

	public function test_callable_tags_receive_the_argument(): void {
		$out = $this->engine->render( '{meta:price}€', array( 'meta' => static fn ( string $k ): string => 'price' === $k ? '19' : '' ) );

		$this->assertSame( '19€', $out );
	}

	public function test_filters_chain_left_to_right(): void {
		$out = $this->engine->render( '{t|strip|upper}', array( 't' => '<b>otoño</b>' ) );

		$this->assertSame( 'OTOÑO', $out );
	}

	public function test_limit_cuts_on_a_word_and_adds_ellipsis(): void {
		$out = TemplateEngine::limit( 'Ya está aquí la colección de otoño', 20 );

		$this->assertSame( 'Ya está aquí la…', $out );
		$this->assertLessThanOrEqual( 20, mb_strlen( $out ) );
	}

	public function test_limit_keeps_short_text(): void {
		$this->assertSame( 'corto', TemplateEngine::limit( 'corto', 20 ) );
	}

	public function test_hashtags_from_terms(): void {
		$this->assertSame( '#ModaSostenible #HechoEnValencia', TemplateEngine::hashtags( 'moda sostenible, Hecho en Valencia, moda sostenible' ) );
	}

	public function test_collapses_blank_lines_left_by_empty_tags(): void {
		$out = $this->engine->render( "{title}\n\n{excerpt}\n\n{url}", array( 'title' => 'T', 'excerpt' => '', 'url' => 'U' ) );

		$this->assertSame( "T\n\nU", $out );
	}

	public function test_split_thread_on_separator_lines(): void {
		$parts = $this->engine->split_thread( "Uno\n---\nDos\n\n ---- \nTres" );

		$this->assertSame( array( 'Uno', 'Dos', 'Tres' ), $parts );
	}

	public function test_split_thread_without_separator_returns_one_part(): void {
		$this->assertSame( array( 'Solo' ), $this->engine->split_thread( 'Solo' ) );
	}
}
