<?php
/**
 * Publishing profiles, one per post type.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Profiles;

/**
 * Stores what to do when a post of each type is published.
 *
 * Shape of each profile:
 *  - enabled        bool
 *  - channels       list<string> Postiz integration ids
 *  - template       string       message template (TemplateEngine syntax)
 *  - mode           string       now | schedule | slot (next free slot per channel) | draft
 *  - delay_minutes  int          only for schedule
 *  - share_on_update bool        share again on update when the editor asks for it
 *  - use_featured_image bool
 */
final class ProfileRepository {

	public const OPTION = 'socialrelaycvi_profiles';
	public const MODES  = array( 'now', 'schedule', 'slot', 'draft' );

	/**
	 * Default profile.
	 *
	 * @return array{enabled: bool, channels: list<string>, template: string, mode: string, delay_minutes: int, share_on_update: bool, use_featured_image: bool}
	 */
	public static function defaults(): array {
		return array(
			'enabled'            => false,
			'channels'           => array(),
			'template'           => "{title}\n\n{excerpt|limit:200}\n\n{url}",
			'mode'               => 'now',
			'delay_minutes'      => 0,
			'share_on_update'    => false,
			'use_featured_image' => true,
		);
	}

	/**
	 * Profile for a post type, merged with the defaults.
	 *
	 * @param string $post_type Post type.
	 * @return array{enabled: bool, channels: list<string>, template: string, mode: string, delay_minutes: int, share_on_update: bool, use_featured_image: bool}
	 */
	public function for_post_type( string $post_type ): array {
		$all = $this->all();

		return $all[ $post_type ] ?? self::defaults();
	}

	/**
	 * All stored profiles.
	 *
	 * @return array<string, array{enabled: bool, channels: list<string>, template: string, mode: string, delay_minutes: int, share_on_update: bool, use_featured_image: bool}>
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
	 * @param array<string, mixed> $raw Raw values.
	 * @return array{enabled: bool, channels: list<string>, template: string, mode: string, delay_minutes: int, share_on_update: bool, use_featured_image: bool}
	 */
	public static function sanitize( array $raw ): array {
		$defaults = self::defaults();
		$mode     = isset( $raw['mode'] ) ? (string) $raw['mode'] : $defaults['mode'];
		$channels = isset( $raw['channels'] ) && is_array( $raw['channels'] ) ? $raw['channels'] : array();

		return array(
			'enabled'            => ! empty( $raw['enabled'] ),
			'channels'           => array_values( array_unique( array_map( 'sanitize_text_field', array_map( 'strval', $channels ) ) ) ),
			'template'           => isset( $raw['template'] ) ? sanitize_textarea_field( (string) $raw['template'] ) : $defaults['template'],
			'mode'               => in_array( $mode, self::MODES, true ) ? $mode : $defaults['mode'],
			'delay_minutes'      => isset( $raw['delay_minutes'] ) ? max( 0, min( 43200, (int) $raw['delay_minutes'] ) ) : 0,
			'share_on_update'    => ! empty( $raw['share_on_update'] ),
			'use_featured_image' => ! isset( $raw['use_featured_image'] ) || ! empty( $raw['use_featured_image'] ),
		);
	}
}
