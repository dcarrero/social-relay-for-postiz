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
			$names = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
			return is_array( $names ) ? implode( ', ', array_map( 'strval', $names ) ) : '';
		};

		$context = array(
			'title'           => $plain( get_the_title( $post ) ),
			'excerpt'         => $plain( $excerpt ),
			'content'         => $plain( strip_shortcodes( $post->post_content ) ),
			'url'             => (string) get_permalink( $post ),
			'slug'            => (string) $post->post_name,
			'shortlink'       => (string) wp_get_shortlink( $post->ID ),
			'author'          => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
			'date'            => (string) get_the_date( '', $post ),
			'site_name'       => $plain( get_bloginfo( 'name' ) ),
			'categories'      => static fn (): string => $terms( 'category' ),
			'tags'            => static fn (): string => $terms( 'post_tag' ),
			'term'            => static fn ( string $taxonomy ): string => '' === $taxonomy ? '' : $terms( $taxonomy ),
			'hashtags'        => static fn ( string $taxonomy ): string => TemplateEngine::hashtags( $terms( '' === $taxonomy ? 'post_tag' : $taxonomy ) ),
			'meta'            => static fn ( string $key ): string => '' === $key || str_starts_with( $key, '_' ) ? '' : $plain( (string) get_post_meta( $post->ID, $key, true ) ),
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
