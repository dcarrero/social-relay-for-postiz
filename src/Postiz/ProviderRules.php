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
	 * Display name and accent colour of a network (same list as assets/src/common/networks.js).
	 *
	 * @param string $identifier Provider identifier.
	 * @return array{label: string, color: string}
	 */
	public static function network( string $identifier ): array {
		$network = match ( $identifier ) {
			'x'                                 => array( 'X', '#000000' ),
			'linkedin', 'linkedin-page'         => array( 'LinkedIn', '#0A66C2' ),
			'bluesky'                           => array( 'Bluesky', '#1185FE' ),
			'mastodon'                          => array( 'Mastodon', '#6364FF' ),
			'threads'                           => array( 'Threads', '#000000' ),
			'instagram', 'instagram-standalone' => array( 'Instagram', '#D62976' ),
			'facebook'                          => array( 'Facebook', '#1877F2' ),
			'pinterest'                         => array( 'Pinterest', '#E60023' ),
			'tiktok'                            => array( 'TikTok', '#000000' ),
			'youtube'                           => array( 'YouTube', '#FF0000' ),
			'telegram'                          => array( 'Telegram', '#229ED9' ),
			'discord'                           => array( 'Discord', '#5865F2' ),
			'gmb'                               => array( 'Google Business', '#4285F4' ),
			'reddit'                            => array( 'Reddit', '#FF4500' ),
			default                             => array( $identifier, '#5B3DF5' ),
		};

		return array(
			'label' => $network[0],
			'color' => $network[1],
		);
	}

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
	 * Most images a post can carry on the network.
	 *
	 * @param string $identifier Provider identifier.
	 */
	public static function max_images( string $identifier ): int {
		return match ( $identifier ) {
			'pinterest', 'gmb'                                                => 1,
			'linkedin', 'linkedin-page'                                       => 9,
			'instagram', 'instagram-standalone', 'facebook', 'threads', 'tiktok' => 10,
			default                                                           => 4,
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

	/**
	 * Editable settings per network, from the Postiz OpenAPI schemas.
	 *
	 * Each field: type (select|text|bool), label, options (select only), required.
	 *
	 * @param string $identifier Provider identifier.
	 * @return array<string, array{type: string, label: string, options?: array<string, string>, required?: bool}>
	 */
	public static function setting_fields( string $identifier ): array {
		return match ( $identifier ) {
			'x'                                 => array(
				'who_can_reply_post' => array(
					'type'    => 'select',
					'label'   => __( 'Who can reply', 'social-relay-for-postiz' ),
					'options' => array(
						'everyone'       => __( 'Everyone', 'social-relay-for-postiz' ),
						'following'      => __( 'Accounts you follow', 'social-relay-for-postiz' ),
						'mentionedUsers' => __( 'Mentioned accounts', 'social-relay-for-postiz' ),
						'subscribers'    => __( 'Subscribers', 'social-relay-for-postiz' ),
						'verified'       => __( 'Verified accounts', 'social-relay-for-postiz' ),
					),
				),
				'community'          => array(
					'type'  => 'text',
					'label' => __( 'Community URL (optional)', 'social-relay-for-postiz' ),
				),
			),
			'linkedin', 'linkedin-page'         => array(
				'post_as_images_carousel' => array(
					'type'  => 'bool',
					'label' => __( 'Show several images as a carousel', 'social-relay-for-postiz' ),
				),
			),
			'instagram', 'instagram-standalone' => array(
				'post_type' => array(
					'type'    => 'select',
					'label'   => __( 'Publish as', 'social-relay-for-postiz' ),
					'options' => array(
						'post'  => _x( 'Post', 'Instagram publication type', 'social-relay-for-postiz' ),
						'story' => _x( 'Story', 'Instagram publication type', 'social-relay-for-postiz' ),
					),
				),
			),
			'pinterest'                         => array(
				'board' => array(
					'type'     => 'text',
					'label'    => __( 'Board ID', 'social-relay-for-postiz' ),
					'required' => true,
				),
			),
			'gmb'                               => array(
				'topicType'        => array(
					'type'    => 'select',
					'label'   => __( 'Post type', 'social-relay-for-postiz' ),
					'options' => array( 'STANDARD' => _x( 'Update', 'Google Business publication type', 'social-relay-for-postiz' ) ),
				),
				'callToActionType' => array(
					'type'    => 'select',
					'label'   => __( 'Button', 'social-relay-for-postiz' ),
					'options' => array(
						'NONE'       => __( 'None', 'social-relay-for-postiz' ),
						'LEARN_MORE' => __( 'Learn more', 'social-relay-for-postiz' ),
						'BOOK'       => __( 'Book', 'social-relay-for-postiz' ),
						'ORDER'      => __( 'Order', 'social-relay-for-postiz' ),
						'SHOP'       => __( 'Shop', 'social-relay-for-postiz' ),
						'SIGN_UP'    => __( 'Sign up', 'social-relay-for-postiz' ),
					),
				),
			),
			default                             => array(),
		};
	}

	/**
	 * Keeps only known settings with valid values.
	 *
	 * @param string               $identifier Provider identifier.
	 * @param array<string, mixed> $raw        Submitted values.
	 * @return array<string, string|bool>
	 */
	public static function sanitize_settings( string $identifier, array $raw ): array {
		$clean = array();

		foreach ( self::setting_fields( $identifier ) as $key => $field ) {
			$value = $raw[ $key ] ?? null;

			switch ( $field['type'] ) {
				case 'bool':
					$clean[ $key ] = ! empty( $value ); // '0' from a hidden field counts as false.
					break;

				case 'select':
					if ( is_string( $value ) && array_key_exists( $value, $field['options'] ?? array() ) ) {
						$clean[ $key ] = $value;
					}
					break;

				default:
					if ( is_scalar( $value ) ) {
						$text = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value ) );
						if ( '' !== $text ) {
							$clean[ $key ] = mb_substr( $text, 0, 500 );
						}
					}
			}
		}

		return $clean;
	}

	/**
	 * Required settings that are missing.
	 *
	 * @param string               $identifier Provider identifier.
	 * @param array<string, mixed> $settings   Sanitized settings.
	 * @return list<string> Field labels.
	 */
	public static function missing_required( string $identifier, array $settings ): array {
		$missing = array();

		foreach ( self::setting_fields( $identifier ) as $key => $field ) {
			if ( ! empty( $field['required'] ) && ( ! isset( $settings[ $key ] ) || '' === $settings[ $key ] ) ) {
				$missing[] = $field['label'];
			}
		}

		return $missing;
	}

	/**
	 * Value for utm_source: the network name without the account-type suffix.
	 *
	 * @param string $identifier Provider identifier.
	 */
	public static function utm_source( string $identifier ): string {
		return match ( $identifier ) {
			'linkedin-page'        => 'linkedin',
			'instagram-standalone' => 'instagram',
			'gmb'                  => 'google_business',
			default                => $identifier,
		};
	}
}
