<?php
/**
 * Plugin Name:       Social Relay for Postiz
 * Plugin URI:        https://github.com/dcarrero/social-relay-for-postiz
 * Description:       Auto-publish and schedule your WordPress content on social networks through your own Postiz instance (self-hosted or Postiz Cloud).
 * Version:           0.2.0-dev
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Color Vivo
 * Author URI:        https://colorvivo.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       social-relay-for-postiz
 * Domain Path:       /languages
 *
 * @package SocialRelayCvi
 */

defined( 'ABSPATH' ) || exit;

define( 'SOCIALRELAYCVI_VERSION', '0.2.0-dev' );
define( 'SOCIALRELAYCVI_FILE', __FILE__ );
define( 'SOCIALRELAYCVI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOCIALRELAYCVI_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Social Relay for Postiz requires PHP 8.2 or higher.', 'social-relay-for-postiz' );
			echo '</p></div>';
		}
	);
	return;
}

if ( ! is_readable( SOCIALRELAYCVI_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Social Relay for Postiz is missing its dependencies. Run "composer install" or install a release build.', 'social-relay-for-postiz' );
			echo '</p></div>';
		}
	);
	return;
}

require_once SOCIALRELAYCVI_DIR . 'vendor/autoload.php';

// Action Scheduler must be loaded before plugins_loaded; it picks the newest copy among active plugins.
if ( is_readable( SOCIALRELAYCVI_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once SOCIALRELAYCVI_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

register_activation_hook( __FILE__, array( \SocialRelayCvi\Lifecycle\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \SocialRelayCvi\Lifecycle\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \SocialRelayCvi\Plugin::class, 'boot' ) );
