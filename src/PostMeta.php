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

	/**
	 * Attachment ids chosen for this post. Empty: the profile setting. NO_IMAGES alone: no images.
	 */
	public const IMAGES = '_socialrelaycvi_images';

	public const NO_IMAGES = 0;

	public const MODES = array( 'now', 'schedule', 'slot', 'draft' );

	/**
	 * Stored alone in CHANNELS when the editor unticks every channel: an empty list means "use the profile".
	 */
	public const NO_CHANNELS = '__none';

	/**
	 * Whether the per-post channels were last chosen by someone who manages the site.
	 * Others can only pick among the profile's channels. Internal, not in REST.
	 */
	public const CHANNELS_BY_ADMIN = '_socialrelaycvi_channels_by_admin';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'added_post_meta', array( $this, 'remember_chooser' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'remember_chooser' ), 10, 3 );
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
				'show_in_rest'  => array(
					'schema' => array(
						'type'    => 'boolean',
						'context' => array( 'edit' ),
					),
				),
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
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'context' => array( 'edit' ),
					),
				),
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
						'context'              => array( 'edit' ),
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
						'type'    => 'array',
						'context' => array( 'edit' ),
						'items'   => array( 'type' => 'string' ),
					),
				),
				'sanitize_callback' => static fn ( mixed $value ): array => is_array( $value ) ? array_values( array_unique( array_map( static fn ( mixed $id ): string => sanitize_text_field( (string) $id ), $value ) ) ) : array(),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			'',
			self::IMAGES,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'context' => array( 'edit' ),
						'items'   => array( 'type' => 'integer' ),
					),
				),
				'sanitize_callback' => static fn ( mixed $value ): array => self::images_for_user( self::sanitize_images( $value ) ),
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
						'type'    => 'string',
						'context' => array( 'edit' ),
						'enum'    => array_merge( array( '' ), self::MODES ),
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
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'string',
						'context' => array( 'edit' ),
					),
				),
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
				'show_in_rest'  => array(
					'schema' => array(
						'type'    => 'boolean',
						'context' => array( 'edit' ),
					),
				),
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

	/**
	 * Unique attachment ids, at most 10; [NO_IMAGES] stays as it is.
	 *
	 * @param mixed $value Raw list.
	 * @return list<int>
	 */
	public static function sanitize_images( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_map( 'intval', $value );
		if ( array( self::NO_IMAGES ) === $ids ) {
			return $ids;
		}

		return array_slice( array_values( array_unique( array_filter( $ids, static fn ( int $id ): bool => $id > 0 ) ) ), 0, 10 );
	}

	/**
	 * Records who chose the per-post channels (see CHANNELS_BY_ADMIN).
	 *
	 * @param int    $meta_id   Meta id.
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Meta key.
	 */
	public function remember_chooser( int $meta_id, int $object_id, string $meta_key ): void {
		if ( self::CHANNELS === $meta_key ) {
			update_post_meta( $object_id, self::CHANNELS_BY_ADMIN, current_user_can( 'manage_options' ) );
		}
	}

	/**
	 * Keeps only images the current user may use: attachments they can edit.
	 *
	 * Without a logged-in user (code, WP-CLI) the list is kept as it is.
	 *
	 * @param list<int> $ids Attachment ids.
	 * @return list<int>
	 */
	private static function images_for_user( array $ids ): array {
		if ( array( self::NO_IMAGES ) === $ids || ! is_user_logged_in() ) {
			return $ids;
		}

		return array_values( array_filter( $ids, static fn ( int $id ): bool => current_user_can( 'edit_post', $id ) ) );
	}
}
