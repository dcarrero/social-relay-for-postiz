<?php
/**
 * Log screen.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;

/**
 * The React log screen (assets/src/log), a DataViews table fed by socialrelaycvi/v1/admin/log.
 */
final class LogPage implements Module {

	public const SLUG = 'social-relay-log';

	/**
	 * Whether the script was enqueued for this request.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

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
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
	}

	/**
	 * Adds the submenu.
	 */
	public function add_menu(): void {
		$hook = add_submenu_page(
			SettingsPage::SLUG,
			__( 'Log', 'social-relay-for-postiz' ),
			__( 'Log', 'social-relay-for-postiz' ),
			SettingsPage::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'enqueue' ) );
		}
	}

	/**
	 * Enqueues the app on this screen only.
	 */
	public function enqueue(): void {
		$channels = array_values( $this->plugin->channels->known() );

		$this->loaded = AdminApp::enqueue(
			'log',
			array(
				'namespace' => 'socialrelaycvi/v1',
				'channels'  => array_combine(
					array_column( $channels, 'id' ),
					array_map(
						static fn ( array $channel ): array => array(
							'id'         => $channel['id'],
							'name'       => $channel['name'],
							'identifier' => $channel['identifier'],
							'picture'    => $channel['picture'],
						),
						$channels
					)
				),
			)
		);
	}

	/**
	 * Renders the root element.
	 */
	public function render(): void {
		if ( current_user_can( SettingsPage::CAPABILITY ) ) {
			AdminApp::render_root( 'socialrelaycvi-log', $this->loaded, __( 'Log', 'social-relay-for-postiz' ) );
		}
	}
}
