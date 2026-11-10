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
