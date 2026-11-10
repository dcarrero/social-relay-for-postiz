<?php
/**
 * Tag values for a WordPress post.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Templates;

use WP_Post;

/**
 * Builds the TemplateEngine context for a post.
 */
final class PostContext {

	/**
	 * Context for a post. Values that need an argument are callables.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, string|callable(string): string>
	 */
	public static function for_post( WP_Post $post ): array {
		$plain = static fn ( string $html ): string => trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( strip_shortcodes( $post->post_content ), 55, '…' );

		$terms = static function ( string $taxonomy ) use ( $post ): string {
			// Only public taxonomies: internal or editorial ones must not reach public networks.
			$object = get_taxonomy( $taxonomy );
			if ( ! $object instanceof \WP_Taxonomy || ! $object->public ) {
				return '';
			}
			$names = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
			return is_array( $names ) ? implode( ', ', array_map( 'strval', $names ) ) : '';
		};

		$context = array(
			'title'           => $plain( get_the_title( $post ) ),
			'excerpt'         => $plain( $excerpt ),
			'content'         => $plain( strip_shortcodes( $post->post_content ) ),
			'url'             => (string) get_permalink( $post ),
			// Drafts have no slug yet: fall back to the title, as WordPress will do on publish.
			'slug'            => '' !== $post->post_name ? (string) $post->post_name : sanitize_title( get_the_title( $post ) ),
			'shortlink'       => (string) wp_get_shortlink( $post->ID ),
			'author'          => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
			'date'            => (string) get_the_date( '', $post ),
			'site_name'       => $plain( get_bloginfo( 'name' ) ),
			'categories'      => static fn (): string => $terms( 'category' ),
			'first_category'  => static fn (): string => self::first_category( $post ),
			'reading_time'    => static fn (): string => (string) self::reading_minutes( $post->post_content ),
			'tags'            => static fn (): string => $terms( 'post_tag' ),
			'term'            => static fn ( string $taxonomy ): string => '' === $taxonomy ? '' : $terms( $taxonomy ),
			'hashtags'        => static fn ( string $taxonomy ): string => TemplateEngine::hashtags( $terms( '' === $taxonomy ? 'post_tag' : $taxonomy ) ),
			// Protected meta (leading "_" or marked protected by a plugin) is never readable from a template.
			'meta'            => static fn ( string $key ): string => '' === $key || is_protected_meta( $key, 'post' ) ? '' : $plain( (string) get_post_meta( $post->ID, $key, true ) ),
			'seo_title'       => static fn (): string => self::seo_value( $post, array( '_yoast_wpseo_title', 'rank_math_title', '_aioseo_title', '_seopress_titles_title' ), get_the_title( $post ) ),
			'seo_description' => static fn (): string => self::seo_value( $post, array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_seopress_titles_desc' ), $excerpt ),
		);

		/**
		 * Filters the template tags available for a post.
		 *
		 * @param array<string, string|callable(string): string> $context Tag values.
		 * @param WP_Post                                        $post    Post being shared.
		 */
		return (array) apply_filters( 'socialrelaycvi_template_context', $context, $post );
	}

	/**
	 * Primary category from Yoast SEO or Rank Math, else the first category.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function first_category( WP_Post $post ): string {
		foreach ( array( '_yoast_wpseo_primary_category', 'rank_math_primary_category' ) as $key ) {
			$term = get_term( (int) get_post_meta( $post->ID, $key, true ), 'category' );
			if ( $term instanceof \WP_Term ) {
				return $term->name;
			}
		}

		$names = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );

		return is_array( $names ) && array() !== $names ? (string) reset( $names ) : '';
	}

	/**
	 * Minutes to read the content at 200 words a minute, at least 1.
	 *
	 * @param string $content Post content.
	 */
	public static function reading_minutes( string $content ): int {
		$text  = wp_strip_all_tags( strip_shortcodes( $content ) );
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );

		return max( 1, (int) ceil( ( false === $words ? 0 : count( $words ) ) / 200 ) );
	}

	/**
	 * First non-empty SEO plugin value, or the fallback.
	 *
	 * SEO titles may contain plugin variables (%%title%%); they are dropped rather than expanded.
	 *
	 * @param WP_Post      $post     Post.
	 * @param list<string> $keys     Meta keys in priority order.
	 * @param string       $fallback Fallback value.
	 */
	private static function seo_value( WP_Post $post, array $keys, string $fallback ): string {
		foreach ( $keys as $key ) {
			$value = (string) get_post_meta( $post->ID, $key, true );
			$value = trim( (string) preg_replace( '/%%[^%]+%%|%[a-z_]+%|#[a-z_]+/i', '', $value ) );
			if ( '' !== $value ) {
				return wp_strip_all_tags( $value );
			}
		}

		return wp_strip_all_tags( $fallback );
	}
}
