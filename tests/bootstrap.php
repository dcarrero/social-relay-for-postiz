<?php
/**
 * Unit test bootstrap: pure classes only, no WordPress loaded.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Minimal i18n stubs so pure classes with translatable labels can be tested.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( string $text, string $context, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}
