<?php
/**
 * Publishing profiles, one per post type.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Profiles;

use SocialRelayCvi\Postiz\ProviderRules;

/**
 * Stores what to do when a post of each type is published.
 *
 * @phpstan-type ChannelOptions array{template: string, settings: array<string, string|bool>}
 * @phpstan-type Profile array{
 *     enabled: bool,
 *     channels: list<string>,
 *     template: string,
 *     channel_options: array<string, ChannelOptions>,
 *     mode: string,
 *     delay_minutes: int,
 *     share_on_update: bool,
 *     images: string,
 *     include_terms: list<int>,
 *     exclude_terms: list<int>,
 *     utm: bool,
 *     utm_campaign: string,
 *     short_link: bool,
 *     postiz_tags: bool
 * }
 *
 * Fields:
 *  - channels         Postiz integration ids.
 *  - template         common message template (TemplateEngine syntax).
 *  - channel_options  per channel: its own template (empty = common) and network settings.
 *  - mode             now | schedule | slot (next free slot per channel) | draft.
 *  - delay_minutes    only for schedule.
 *  - share_on_update  share again on update when the editor asks for it.
 *  - images           featured | content (featured image and the images in the post) | none.
 *  - include_terms    category/tag ids; the post needs at least one (empty = any post).
 *  - exclude_terms    category/tag ids that block sharing.
 *  - utm, utm_campaign  add utm_source/medium/campaign to {url}; campaign accepts tags.
 *  - short_link       let Postiz shorten links.
 *  - postiz_tags      send the post categories as Postiz tags.
 */
final class ProfileRepository {

	public const OPTION = 'socialrelaycvi_profiles';
	public const MODES  = array( 'now', 'schedule', 'slot', 'draft' );
	public const IMAGES = array( 'featured', 'content', 'none' );

	/**
	 * Default profile.
	 *
	 * @phpstan-return Profile
	 */
	public static function defaults(): array {
		return array(
			'enabled'         => false,
			'channels'        => array(),
			'template'        => "{title}\n\n{excerpt|limit:200}\n\n{url}",
			'channel_options' => array(),
			'mode'            => 'now',
			'delay_minutes'   => 0,
			'share_on_update' => false,
			'images'          => 'featured',
			'include_terms'   => array(),
			'exclude_terms'   => array(),
			'utm'             => false,
			'utm_campaign'    => '{slug}',
			'short_link'      => false,
			'postiz_tags'     => false,
		);
	}

	/**
	 * Profile for a post type, merged with the defaults.
	 *
	 * @param string $post_type Post type.
	 * @phpstan-return Profile
	 */
	public function for_post_type( string $post_type ): array {
		$all = $this->all();

		return $all[ $post_type ] ?? self::defaults();
	}

	/**
	 * All stored profiles.
	 *
	 * @phpstan-return array<string, Profile>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$profiles = array();
		foreach ( $stored as $post_type => $profile ) {
			$profiles[ (string) $post_type ] = self::sanitize( is_array( $profile ) ? $profile : array() );
		}

		return $profiles;
	}

	/**
	 * Sanitizes one profile.
	 *
	 * @param array<string, mixed>  $raw         Raw values.
	 * @param array<string, string> $identifiers Channel id => provider identifier, to validate network settings.
	 *                                           Channels not listed keep their stored settings.
	 * @phpstan-return Profile
	 */
	public static function sanitize( array $raw, array $identifiers = array() ): array {
		$defaults = self::defaults();
		$mode     = isset( $raw['mode'] ) ? (string) $raw['mode'] : $defaults['mode'];
		$channels = isset( $raw['channels'] ) && is_array( $raw['channels'] ) ? $raw['channels'] : array();
		$channels = array_values( array_unique( array_map( 'sanitize_text_field', array_map( 'strval', $channels ) ) ) );

		$options = array();
		if ( isset( $raw['channel_options'] ) && is_array( $raw['channel_options'] ) ) {
			foreach ( $raw['channel_options'] as $channel_id => $option ) {
				$channel_id = sanitize_text_field( (string) $channel_id );
				if ( '' === $channel_id || ! is_array( $option ) ) {
					continue;
				}

				$settings = isset( $option['settings'] ) && is_array( $option['settings'] ) ? $option['settings'] : array();
				$settings = isset( $identifiers[ $channel_id ] )
					? ProviderRules::sanitize_settings( $identifiers[ $channel_id ], $settings )
					: self::keep_scalars( $settings );

				$template = isset( $option['template'] ) ? sanitize_textarea_field( (string) $option['template'] ) : '';

				if ( '' !== trim( $template ) || array() !== $settings ) {
					$options[ $channel_id ] = array(
						'template' => $template,
						'settings' => $settings,
					);
				}
			}
		}

		return array(
			'enabled'         => ! empty( $raw['enabled'] ),
			'channels'        => $channels,
			'template'        => isset( $raw['template'] ) ? sanitize_textarea_field( (string) $raw['template'] ) : $defaults['template'],
			'channel_options' => $options,
			'mode'            => in_array( $mode, self::MODES, true ) ? $mode : $defaults['mode'],
			'delay_minutes'   => isset( $raw['delay_minutes'] ) ? max( 0, min( 43200, (int) $raw['delay_minutes'] ) ) : 0,
			'share_on_update' => ! empty( $raw['share_on_update'] ),
			'images'          => self::images_mode( $raw ),
			'include_terms'   => self::term_ids( $raw['include_terms'] ?? array() ),
			'exclude_terms'   => self::term_ids( $raw['exclude_terms'] ?? array() ),
			'utm'             => ! empty( $raw['utm'] ),
			'utm_campaign'    => isset( $raw['utm_campaign'] ) ? sanitize_text_field( (string) $raw['utm_campaign'] ) : $defaults['utm_campaign'],
			'short_link'      => ! empty( $raw['short_link'] ),
			'postiz_tags'     => ! empty( $raw['postiz_tags'] ),
		);
	}

	/**
	 * Image mode, reading the old use_featured_image switch of profiles saved before 1.0.
	 *
	 * @param array<string, mixed> $raw Raw profile.
	 */
	private static function images_mode( array $raw ): string {
		if ( isset( $raw['images'] ) && in_array( $raw['images'], self::IMAGES, true ) ) {
			return (string) $raw['images'];
		}

		return isset( $raw['use_featured_image'] ) && empty( $raw['use_featured_image'] ) ? 'none' : 'featured';
	}

	/**
	 * Positive unique integer ids.
	 *
	 * @param mixed $value Raw list.
	 * @return list<int>
	 */
	private static function term_ids( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * Keeps already-stored settings when the channel identifier is unknown.
	 *
	 * @param array<mixed> $settings Settings.
	 * @return array<string, string|bool>
	 */
	private static function keep_scalars( array $settings ): array {
		$clean = array();

		foreach ( $settings as $key => $value ) {
			// Not sanitize_key(): it lowercases, and Postiz uses camelCase keys such as callToActionType.
			$key = (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $key );
			if ( '' !== $key && ( is_bool( $value ) || is_string( $value ) ) ) {
				$clean[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		return $clean;
	}
}
