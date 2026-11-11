<?php
/**
 * Settings screen.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;

/**
 * Top-level menu and the React settings screen (assets/src/settings).
 *
 * Data is read and saved through the socialrelaycvi/v1/admin REST routes.
 */
final class SettingsPage implements Module {

	public const SLUG       = 'social-relay';
	public const CAPABILITY = 'manage_options';

	/**
	 * Whether the script was enqueued for this request.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SOCIALRELAYCVI_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the top-level menu.
	 */
	public function add_menu(): void {
		$hook = add_menu_page(
			__( 'Social Relay for Postiz', 'social-relay-for-postiz' ),
			__( 'Social Relay', 'social-relay-for-postiz' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-share',
			30
		);

		add_submenu_page( self::SLUG, __( 'Settings', 'social-relay-for-postiz' ), __( 'Settings', 'social-relay-for-postiz' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );

		add_action( 'load-' . $hook, array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the app on this screen only.
	 */
	public function enqueue(): void {
		$this->loaded = AdminApp::enqueue(
			'settings',
			array(
				'namespace' => 'socialrelaycvi/v1',
				'logUrl'    => admin_url( 'admin.php?page=' . LogPage::SLUG ),
				'docsUrl'   => 'https://docs.postiz.com/public-api/introduction',
			)
		);

		if ( $this->loaded ) {
			/**
			 * Fires after the settings screen enqueued its app, for add-ons that add tabs.
			 *
			 * Enqueue the add-on script with `socialrelaycvi-settings` as a dependency and add
			 * its tabs with the `socialrelaycvi.settingsTabs` JavaScript filter.
			 *
			 * @param string $handle Script handle of the settings app.
			 */
			do_action( 'socialrelaycvi_settings_enqueue', 'socialrelaycvi-settings' );
		}
	}

	/**
	 * Adds "Settings" to the plugins list.
	 *
	 * @param array<string> $links Links.
	 * @return array<string>
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'social-relay-for-postiz' ) ) );

		return $links;
	}

	/**
	 * Renders the root element.
	 */
	public function render(): void {
		if ( current_user_can( self::CAPABILITY ) ) {
			AdminApp::render_root( 'socialrelaycvi-settings', $this->loaded, __( 'Social Relay for Postiz', 'social-relay-for-postiz' ) );
		}
	}
}
