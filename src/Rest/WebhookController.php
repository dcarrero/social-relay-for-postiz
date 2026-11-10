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
use SocialRelayCvi\Sync\WebhookPayload;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public endpoint protected by a secret in the URL.
 *
 * Postiz webhooks are not signed, so the URL itself is the credential:
 * /wp-json/socialrelaycvi/v1/webhook/{secret}. The body only says which shares to
 * check first (WebhookPayload); statuses always come from the Postiz API.
 */
final class WebhookController implements Module {

	public const OPTION_SECRET = 'socialrelaycvi_webhook_secret';
	public const OPTION_LAST   = 'socialrelaycvi_webhook_last';

	/**
	 * Last calls: list of { time, posts, rows } (rows = share row ids the call named).
	 */
	public const OPTION_HISTORY = 'socialrelaycvi_webhook_history';

	/**
	 * One-time token of "Check URL" calls, so they are not counted as Postiz calls.
	 */
	public const CHECK_TOKEN = 'socialrelaycvi_webhook_check';

	public const CHECK_HEADER = 'X-Socialrelaycvi-Check';

	private const HISTORY_SIZE = 20;

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

		// "Check URL" from the settings: Postiz never sends this header, so such a call is
		// never counted as one from Postiz, even when its token does not match.
		$check = (string) $request->get_header( self::CHECK_HEADER );
		if ( '' !== $check ) {
			$token = (string) get_transient( self::CHECK_TOKEN );
			$valid = '' !== $token && hash_equals( $token, $check );
			return new WP_REST_Response( array( 'check' => $valid ), $valid ? 200 : 409 );
		}

		$payload = WebhookPayload::parse( json_decode( (string) $request->get_body(), true ) );
		$rows    = $this->plugin->shares->by_postiz_ids( $payload['ids'] );

		update_option( self::OPTION_LAST, time(), false );
		$this->remember( $payload['posts'], array_map( static fn ( array $row ): int => (int) $row['id'], $rows ) );

		// Ask Postiz about the shares this call named; the status itself comes from the API.
		if ( array() !== $rows ) {
			$this->plugin->queue->enqueue_named_sync( array_values( array_unique( array_map( 'strval', array_column( $rows, 'postiz_post_id' ) ) ) ) );
		} else {
			$this->plugin->queue->enqueue_sync( 0 );
		}

		return new WP_REST_Response( array( 'queued' => true ), 202 );
	}

	/**
	 * Adds a call to the history (newest first).
	 *
	 * @param int       $posts Posts in the body.
	 * @param list<int> $rows  Share rows they match.
	 */
	private function remember( int $posts, array $rows ): void {
		$history = get_option( self::OPTION_HISTORY, array() );
		$history = is_array( $history ) ? $history : array();

		array_unshift(
			$history,
			array(
				'time'  => time(),
				'posts' => $posts,
				'rows'  => array_slice( $rows, 0, 10 ),
			)
		);

		update_option( self::OPTION_HISTORY, array_slice( $history, 0, self::HISTORY_SIZE ), false );
	}

	/**
	 * Calls the webhook URL from this server, as Postiz would, to see whether it answers.
	 *
	 * @return array{ok: bool, code: string, status: int}
	 *         code: ok, not_found (route blocked or permalinks), forbidden (security plugin or firewall),
	 *         unreachable (the server cannot call itself) or unexpected.
	 */
	public static function check(): array {
		$token = wp_generate_password( 24, false, false );
		set_transient( self::CHECK_TOKEN, $token, MINUTE_IN_SECONDS );

		$response = wp_remote_post(
			self::url(),
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'     => 'application/json',
					self::CHECK_HEADER => $token,
				),
				'body'    => '[]',
			)
		);
		delete_transient( self::CHECK_TOKEN );

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'code'   => 'unreachable',
				'status' => 0,
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code   = match ( true ) {
			200 === $status && is_array( $body ) && ! empty( $body['check'] ) => 'ok',
			404 === $status => 'not_found',
			401 === $status, 403 === $status => 'forbidden',
			default => 'unexpected',
		};

		return array(
			'ok'     => 'ok' === $code,
			'code'   => $code,
			'status' => $status,
		);
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
