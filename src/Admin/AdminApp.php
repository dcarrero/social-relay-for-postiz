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

		self::enqueue_styles( $name, $handle, (string) ( $asset['version'] ?? SOCIALRELAYCVI_VERSION ) );

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

	/**
	 * Enqueues the CSS of a build entry.
	 *
	 * @wordpress/scripts writes files named style.css as build/style-{name}.css and any
	 * other imported CSS as build/{name}.css, each with an -rtl.css twin.
	 *
	 * @param string $name    Build entry name.
	 * @param string $handle  Script handle, reused as style handle prefix.
	 * @param string $version Asset version.
	 */
	public static function enqueue_styles( string $name, string $handle, string $version ): void {
		foreach ( array( $name, 'style-' . $name ) as $file ) {
			$path = SOCIALRELAYCVI_DIR . 'build/' . $file . '.css';
			if ( ! is_readable( $path ) ) {
				continue;
			}

			// The asset version only follows the JavaScript; the file time also catches CSS-only changes.
			$style = $handle . ( $file === $name ? '' : '-style' );
			wp_enqueue_style( $style, SOCIALRELAYCVI_URL . 'build/' . $file . '.css', array( 'wp-components' ), $version . '.' . (int) filemtime( $path ) );
			wp_style_add_data( $style, 'rtl', 'replace' );
		}
	}
}
