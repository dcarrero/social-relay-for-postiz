<?php
/**
 * Per-post settings stored as post meta.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi;

/**
 * Registers the post meta used by the editor panel (0.3) and the share service.
 *
 * Registered with show_in_rest so the block editor and real-time collaboration
 * read and write them like any other post field.
 */
final class PostMeta implements Module {

	public const ENABLED   = '_socialrelaycvi_enabled';
	public const MESSAGE   = '_socialrelaycvi_message';
	public const RESHARE   = '_socialrelaycvi_reshare';
	public const OVERRIDES = '_socialrelaycvi_overrides';
	public const CHANNELS  = '_socialrelaycvi_channels';

	/**
	 * Timing for this post: '' follows the profile, otherwise now, schedule, slot or draft.
	 */
	public const MODE = '_socialrelaycvi_mode';

	/**
	 * Date for MODE schedule, site time "Y-m-d\TH:i:s". Empty: the profile delay after publishing.
	 */
	public const PUBLISH_AT = '_socialrelaycvi_publish_at';

	/**
	 * Channel ids for the next share only ("Retry" from the editor). Internal, not in REST.
	 */
	public const ONLY = '_socialrelaycvi_only';

	public const MODES = array( 'now', 'schedule', 'slot', 'draft' );

	/**
	 * Stored alone in CHANNELS when the editor unticks every channel: an empty list means "use the profile".
	 */
	public const NO_CHANNELS = '__none';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Registers the meta keys for every post type that supports custom fields.
	 */
	public function register_meta(): void {
		$auth = static fn ( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id );

		register_post_meta(
			'',
			self::ENABLED,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);

		register_post_meta(
			'',
			self::MESSAGE,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => $auth,
			)
		);

		// Per-network text for this post: { "<channel id>": "text" }.
		register_post_meta(
			'',
			self::OVERRIDES,
			array(
				'type'              => 'object',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
					),
				),
				'sanitize_callback' => static fn ( mixed $value ): array => is_array( $value ) ? array_map( static fn ( mixed $text ): string => sanitize_textarea_field( (string) $text ), $value ) : array(),
				'auth_callback'     => $auth,
			)
		);

		// Channels chosen for this post; empty = the profile's channels.
		register_post_meta(
			'',
			self::CHANNELS,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'sanitize_callback' => static fn ( mixed $value ): array => is_array( $value ) ? array_values( array_unique( array_map( static fn ( mixed $id ): string => sanitize_text_field( (string) $id ), $value ) ) ) : array(),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			'',
			self::MODE,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => array_merge( array( '' ), self::MODES ),
					),
				),
				'sanitize_callback' => static fn ( mixed $value ): string => in_array( $value, self::MODES, true ) ? (string) $value : '',
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			'',
			self::PUBLISH_AT,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => static fn ( mixed $value ): string => self::sanitize_datetime( (string) $value ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			'',
			self::RESHARE,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
	}

	/**
	 * Keeps a local date and time as "Y-m-d\TH:i:s", or '' when it is not one.
	 *
	 * @param string $value Date from the editor (DateTimePicker) or the classic metabox (datetime-local).
	 */
	public static function sanitize_datetime( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		foreach ( array( 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i' ) as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() );
			if ( false !== $date ) {
				return $date->format( 'Y-m-d\TH:i:s' );
			}
		}

		return '';
	}
}
