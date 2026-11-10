<?php
/**
 * Block editor sidebar panel.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Postiz\ProviderRules;

/**
 * Loads assets/js/editor-panel.js and passes it the profile and channels.
 *
 * The script uses the packages WordPress already loads in the editor
 * (wp.plugins, wp.editor, wp.components, wp.data…), so there is no build step.
 */
final class EditorPanel implements Module {

	public const HANDLE = 'socialrelaycvi-editor-panel';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the panel for post types with a profile.
	 */
	public function enqueue(): void {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen && $screen->post_type ? (string) $screen->post_type : '';

		if ( '' === $post_type || ! current_user_can( 'edit_posts' ) || ! post_type_supports( $post_type, 'custom-fields' ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			SOCIALRELAYCVI_URL . 'assets/js/editor-panel.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
			SOCIALRELAYCVI_VERSION,
			true
		);

		wp_set_script_translations( self::HANDLE, 'social-relay-for-postiz', SOCIALRELAYCVI_DIR . 'languages' );

		wp_add_inline_script(
			self::HANDLE,
			'window.socialRelayCvi = ' . wp_json_encode( $this->data( $post_type ) ) . ';',
			'before'
		);
	}

	/**
	 * Data for the panel. Never includes the API key.
	 *
	 * @param string $post_type Post type being edited.
	 * @return array<string, mixed>
	 */
	private function data( string $post_type ): array {
		$connection = $this->plugin->connection;
		$profile    = $this->plugin->profiles->for_post_type( $post_type );
		$channels   = $connection->is_configured() ? $this->plugin->channels->all() : array();
		$channels   = is_wp_error( $channels ) ? array() : $channels;

		$limits = array();
		foreach ( $channels as $channel ) {
			$limits[ $channel['identifier'] ] = ProviderRules::char_limit( $channel['identifier'] );
		}

		return array(
			'namespace'   => 'socialrelaycvi/v1',
			'configured'  => $connection->is_configured(),
			'settingsUrl' => admin_url( 'admin.php?page=' . SettingsPage::SLUG ),
			'logUrl'      => admin_url( 'admin.php?page=social-relay-log' ),
			'canManage'   => current_user_can( SettingsPage::CAPABILITY ),
			'profile'     => array(
				'enabled'       => $profile['enabled'],
				'channels'      => $profile['channels'],
				'mode'          => $profile['mode'],
				'delayMinutes'  => $profile['delay_minutes'],
				'shareOnUpdate' => $profile['share_on_update'],
				'templates'     => array_map( static fn ( array $option ): string => $option['template'], $profile['channel_options'] ),
				'template'      => $profile['template'],
			),
			'channels'    => array_map(
				static fn ( array $channel ): array => array(
					'id'         => $channel['id'],
					'name'       => $channel['name'],
					'identifier' => $channel['identifier'],
					'picture'    => $channel['picture'],
					'disabled'   => $channel['disabled'],
				),
				$channels
			),
			'limits'      => $limits,
		);
	}
}
