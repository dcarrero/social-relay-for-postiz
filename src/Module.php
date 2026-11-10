<?php
/**
 * Module contract.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi;

/**
 * A unit of functionality that hooks itself into WordPress.
 */
interface Module {

	/**
	 * Adds the module's actions and filters. Called once, on plugins_loaded.
	 */
	public function register(): void;
}
