<?php
/**
 * Receives the Postiz "post published" webhook.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Rest;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public endpoint protected by a secret in the URL.
 *
 * Postiz webhooks are not signed, so the URL itself is the credential:
 * /wp-json/socialrelaycvi/v1/webhook/{secret}. The handler only queues a status
 * sync; it never trusts the payload.
 */
final class WebhookController implements Module {

	public const OPTION_SECRET = 'socialrelaycvi_webhook_secret';
	public const OPTION_LAST   = 'socialrelaycvi_webhook_last';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			ConnectionController::NAMESPACE,
			'/webhook/(?P<secret>[A-Za-z0-9]{20,64})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive' ),
				// Public on purpose: Postiz cannot authenticate. The secret is checked in receive().
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles a call.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function receive( WP_REST_Request $request ): WP_REST_Response {
		if ( ! hash_equals( self::secret(), (string) $request['secret'] ) ) {
			// Same answer as an unknown route, so the endpoint cannot be probed.
			return new WP_REST_Response( array( 'code' => 'rest_no_route' ), 404 );
		}

		update_option( self::OPTION_LAST, time(), false );
		$this->plugin->queue->enqueue_sync( 0 );

		return new WP_REST_Response( array( 'queued' => true ), 202 );
	}

	/**
	 * The secret, created on first use.
	 */
	public static function secret(): string {
		$secret = (string) get_option( self::OPTION_SECRET, '' );

		if ( '' === $secret ) {
			$secret = self::regenerate();
		}

		return $secret;
	}

	/**
	 * Creates a new secret; the old URL stops working.
	 */
	public static function regenerate(): string {
		$secret = wp_generate_password( 32, false, false );
		update_option( self::OPTION_SECRET, $secret, false );

		return $secret;
	}

	/**
	 * Full webhook URL to paste in Postiz.
	 */
	public static function url(): string {
		return rest_url( ConnectionController::NAMESPACE . '/webhook/' . self::secret() );
	}
}
