<?php
/**
 * Per-network rules: character limits, image requirements and default settings.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

/**
 * Static knowledge about each Postiz provider identifier.
 */
final class ProviderRules {

	/**
	 * Character limit per post, or null when the network has no practical limit.
	 *
	 * @param string $identifier Postiz provider identifier (x, bluesky, linkedin-page…).
	 */
	public static function char_limit( string $identifier ): ?int {
		return match ( $identifier ) {
			'x'                                    => 280,
			'bluesky'                              => 300,
			'mastodon', 'threads'                  => 500,
			'instagram', 'instagram-standalone'    => 2200,
			'tiktok'                               => 2200,
			'linkedin', 'linkedin-page'            => 3000,
			'pinterest'                            => 500,
			'gmb'                                  => 1500,
			'discord'                              => 2000,
			'telegram'                             => 4096,
			default                                => null,
		};
	}

	/**
	 * Whether the network refuses posts without media.
	 *
	 * @param string $identifier Provider identifier.
	 */
	public static function requires_media( string $identifier ): bool {
		return in_array( $identifier, array( 'instagram', 'instagram-standalone', 'pinterest', 'tiktok', 'youtube' ), true );
	}

	/**
	 * Default `settings` object for POST /posts, always including __type.
	 *
	 * @param string $identifier Provider identifier.
	 * @return array<string, mixed>
	 */
	public static function default_settings( string $identifier ): array {
		$settings = match ( $identifier ) {
			'x'                                 => array(
				'who_can_reply_post' => 'everyone',
				'community'          => '',
			),
			'instagram', 'instagram-standalone' => array(
				'post_type'     => 'post',
				'is_trial_reel' => false,
				'collaborators' => array(),
			),
			'linkedin', 'linkedin-page'         => array( 'post_as_images_carousel' => false ),
			default                             => array(),
		};

		return array( '__type' => $identifier ) + $settings;
	}
}
