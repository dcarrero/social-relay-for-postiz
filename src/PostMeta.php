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
}
