<?php
/**
 * "Suggest with AI": a social message written by the site's own AI provider.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sharing;

use SocialRelayCvi\Postiz\ProviderRules;
use SocialRelayCvi\Templates\PostContext;
use WP_Error;
use WP_Post;

/**
 * Uses the WordPress 7 AI Client (wp_ai_client_prompt) with whatever provider the site
 * set up in Settings → Connectors. The plugin has no AI keys of its own, and the text
 * only goes into the editor for review: nothing is published without a person.
 */
final class AiSuggester {

	private const AVAILABLE_CACHE = 'socialrelaycvi_ai_available';

	/**
	 * Whether a text-generation provider is set up. Cached: the check may call the provider.
	 */
	public function available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return false;
		}

		$cached = get_transient( self::AVAILABLE_CACHE );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$available = true === wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation();
		set_transient( self::AVAILABLE_CACHE, $available ? 'yes' : 'no', $available ? 12 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );

		return $available;
	}

	/**
	 * Forgets the cached availability (after connectors change).
	 */
	public static function flush(): void {
		delete_transient( self::AVAILABLE_CACHE );
	}

	/**
	 * Writes a message for a post, for every network or for one.
	 *
	 * @param WP_Post $post       Post.
	 * @param string  $identifier Postiz provider identifier, '' for a text for all networks.
	 * @return string|WP_Error Message, with {url} where the link goes.
	 */
	public function suggest( WP_Post $post, string $identifier = '' ): string|WP_Error {
		/**
		 * Short-circuits the suggestion, e.g. to use another service or a fixed tone.
		 *
		 * @param string|WP_Error|null $text       Null to let the AI Client write it.
		 * @param WP_Post              $post       Post.
		 * @param string               $identifier Network, '' for all networks.
		 */
		$text = apply_filters( 'socialrelaycvi_ai_suggestion', null, $post, $identifier );
		if ( is_string( $text ) ) {
			return self::clean( $text );
		}
		if ( $text instanceof WP_Error ) {
			return $text;
		}

		if ( ! $this->available() ) {
			return new WP_Error( 'socialrelaycvi_ai_unavailable', __( 'No AI provider is set up. Add one in Settings → Connectors.', 'social-relay-for-postiz' ), array( 'status' => 409 ) );
		}

		$result = wp_ai_client_prompt( $this->prompt( $post ) )
			->using_system_instruction( $this->instructions( $identifier ) )
			->using_temperature( 0.7 )
			->using_max_tokens( 600 )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::clean( (string) $result );
	}

	/**
	 * What the model is told to do.
	 *
	 * @param string $identifier Network, '' for all networks.
	 */
	private function instructions( string $identifier ): string {
		$limit   = '' === $identifier ? 280 : ( ProviderRules::char_limit( $identifier ) ?? 1000 );
		$network = '' === $identifier ? 'several social networks (X, LinkedIn, Bluesky, Mastodon…)' : ProviderRules::network( $identifier )['label'];

		return implode(
			"\n",
			array(
				'You write the social media post that announces a new article of a website.',
				'Network: ' . $network . '. Keep the whole post under ' . min( $limit, 600 ) . ' characters, counting a link as 23.',
				'Write in the same language as the article. Match the tone of the network: professional on LinkedIn, short and direct on X, friendly elsewhere.',
				'Do not invent facts that are not in the article. At most three relevant hashtags, at the end.',
				'Put the literal placeholder {url} where the link goes, on its own line at the end. Do not write any other URL.',
				'Answer with the post text only: no quotes, no title, no explanations.',
			)
		);
	}

	/**
	 * The article, plain text and trimmed.
	 *
	 * @param WP_Post $post Post.
	 */
	private function prompt( WP_Post $post ): string {
		$context = PostContext::for_post( $post );
		$content = (string) $context['content'];
		if ( mb_strlen( $content ) > 4000 ) {
			$content = mb_substr( $content, 0, 4000 ) . '…';
		}

		$categories = is_callable( $context['categories'] ) ? (string) ( $context['categories'] )() : '';

		return implode(
			"\n\n",
			array_filter(
				array(
					'Site: ' . $context['site_name'],
					'Title: ' . $context['title'],
					'' !== (string) $context['excerpt'] ? 'Summary: ' . $context['excerpt'] : '',
					'' !== $categories ? 'Categories: ' . $categories : '',
					'Article:' . "\n" . $content,
				)
			)
		);
	}

	/**
	 * Removes wrapping quotes and code fences some models add.
	 *
	 * @param string $text Model output.
	 */
	public static function clean( string $text ): string {
		$text = trim( $text );
		$text = (string) preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $text );
		$text = trim( $text );

		if ( preg_match( '/^(["“«])(.*)(["”»])$/su', $text, $match ) ) {
			$text = trim( $match[2] );
		}

		// The link always goes in, as a tag, so UTM and short links still apply.
		if ( ! str_contains( $text, '{url}' ) ) {
			$text .= "\n\n{url}";
		}

		return $text;
	}
}
