<?php
/**
 * Message templates with {tags} and |filters.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Templates;

/**
 * Renders templates such as "{title|upper}\n\n{excerpt|limit:180}\n{url}".
 *
 * Syntax:
 *  - {tag}            value of the tag
 *  - {tag:argument}   tag with an argument, e.g. {meta:price}, {hashtags:post_tag}
 *  - {tag|filter}     filters chain left to right: upper, lower, strip, trim, hashtag, limit:N
 *  - a line containing only --- splits the message into thread parts (see split_thread()).
 *
 * The engine is pure: values come from the context, so it is unit-testable without WordPress.
 */
final class TemplateEngine {

	private const TAG_PATTERN = '/\{([a-z][a-z0-9_]*)(?::([^|}]*))?((?:\|[a-z]+(?::[^|}]*)?)*)\}/i';

	/**
	 * Renders a template.
	 *
	 * @param string                                         $template Template text.
	 * @param array<string, string|callable(string): string> $context  Tag values. Callables receive the tag argument.
	 */
	public function render( string $template, array $context ): string {
		$output = (string) preg_replace_callback(
			self::TAG_PATTERN,
			function ( array $m ) use ( $context ): string {
				$name     = strtolower( $m[1] );
				$argument = trim( $m[2] );
				$filters  = $m[3];

				if ( ! array_key_exists( $name, $context ) ) {
					return '';
				}

				$value = $context[ $name ];
				$value = is_callable( $value ) ? (string) $value( $argument ) : (string) $value;

				return $this->apply_filters( $value, $filters );
			},
			$template
		);

		$output = str_replace( array( "\r\n", "\r" ), "\n", $output );
		$output = (string) preg_replace( "/[ \t]+\n/", "\n", $output );
		$output = (string) preg_replace( "/\n{3,}/", "\n\n", $output );

		return trim( $output );
	}

	/**
	 * Splits a rendered message into thread parts on lines that contain only ---.
	 *
	 * @param string $message Rendered message.
	 * @return list<string> Non-empty parts; a single part when there is no separator.
	 */
	public function split_thread( string $message ): array {
		$parts = preg_split( '/^\s*-{3,}\s*$/m', $message );
		$parts = array_map( 'trim', false === $parts ? array( $message ) : $parts );

		return array_values( array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
	}

	/**
	 * Shortens text to a maximum length in characters, on a word boundary when possible.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Maximum characters, ellipsis included.
	 */
	public static function limit( string $text, int $limit ): string {
		if ( $limit <= 0 || mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		$cut        = mb_substr( $text, 0, max( 1, $limit - 1 ) );
		$last_space = mb_strrpos( $cut, ' ' );

		if ( false !== $last_space && $last_space > (int) ( $limit * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $last_space );
		}

		return rtrim( $cut, " \t\n.,;:" ) . '…';
	}

	/**
	 * Turns "Moda sostenible, Hecho en Valencia" into "#ModaSostenible #HechoEnValencia".
	 *
	 * @param string $text Comma-separated terms.
	 */
	public static function hashtags( string $text ): string {
		$tags = array();

		foreach ( explode( ',', $text ) as $term ) {
			$words = preg_split( '/[^\p{L}\p{N}]+/u', trim( $term ) );
			$tag   = implode(
				'',
				array_map(
					static fn ( string $w ): string => mb_strtoupper( mb_substr( $w, 0, 1 ) ) . mb_substr( $w, 1 ),
					array_filter( false === $words ? array() : $words, static fn ( string $w ): bool => '' !== $w )
				)
			);

			if ( '' !== $tag ) {
				$tags[] = '#' . $tag;
			}
		}

		return implode( ' ', array_unique( $tags ) );
	}

	/**
	 * Applies a "|filter|filter:arg" chain.
	 *
	 * @param string $value   Tag value.
	 * @param string $filters Filter chain, including the leading pipe.
	 */
	private function apply_filters( string $value, string $filters ): string {
		if ( '' === $filters ) {
			return $value;
		}

		foreach ( explode( '|', ltrim( $filters, '|' ) ) as $filter ) {
			$pieces = explode( ':', $filter, 2 );
			$name   = strtolower( $pieces[0] );
			$arg    = $pieces[1] ?? '';

			$value = match ( $name ) {
				'upper'   => mb_strtoupper( $value ),
				'lower'   => mb_strtolower( $value ),
				'trim'    => trim( $value ),
				'strip'   => trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( (string) preg_replace( '/<[^>]*>/', ' ', $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ),
				'hashtag' => self::hashtags( $value ),
				'limit'   => self::limit( $value, (int) $arg ),
				default   => $value,
			};
		}

		return $value;
	}
}
