<?php
/**
 * Loads a React admin screen built with @wordpress/scripts.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

/**
 * Helper shared by the settings and log screens.
 */
final class AdminApp {

	/**
	 * Enqueues build/{name}.js (+ build/style-{name}.css) and prints the data for it.
	 *
	 * @param string               $name Build entry name: settings or log.
	 * @param array<string, mixed> $data Data exposed as window.socialRelayCviAdmin.
	 * @return bool False when the build is missing.
	 */
	public static function enqueue( string $name, array $data ): bool {
		$asset_file = SOCIALRELAYCVI_DIR . 'build/' . $name . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return false;
		}

		$asset  = require $asset_file;
		$handle = 'socialrelaycvi-' . $name;

		wp_enqueue_script(
			$handle,
			SOCIALRELAYCVI_URL . 'build/' . $name . '.js',
			(array) ( $asset['dependencies'] ?? array() ),
			(string) ( $asset['version'] ?? SOCIALRELAYCVI_VERSION ),
			true
		);

		// @wordpress/scripts writes CSS imported by the entry as style-{name}.css (+ -rtl.css).
		if ( is_readable( SOCIALRELAYCVI_DIR . 'build/style-' . $name . '.css' ) ) {
			wp_enqueue_style( $handle, SOCIALRELAYCVI_URL . 'build/style-' . $name . '.css', array( 'wp-components' ), (string) ( $asset['version'] ?? SOCIALRELAYCVI_VERSION ) );
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}

		wp_set_script_translations( $handle, 'social-relay-for-postiz', SOCIALRELAYCVI_DIR . 'languages' );
		wp_add_inline_script( $handle, 'window.socialRelayCviAdmin = ' . wp_json_encode( $data ) . ';', 'before' );

		return true;
	}

	/**
	 * Root element, or a notice when the build is missing.
	 *
	 * @param string $id     Root element id.
	 * @param bool   $loaded Whether the script was enqueued.
	 * @param string $title  Page title, shown while the app loads.
	 */
	public static function render_root( string $id, bool $loaded, string $title ): void {
		echo '<div class="wrap">';
		printf( '<h1 class="screen-reader-text">%s</h1>', esc_html( $title ) );

		if ( ! $loaded ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'The admin screens are missing. Run "npm ci && npm run build" or install a release build.', 'social-relay-for-postiz' )
			);
		} else {
			printf( '<div id="%s"><p>%s</p></div>', esc_attr( $id ), esc_html__( 'Loading…', 'social-relay-for-postiz' ) );
		}

		echo '</div>';
	}
}
