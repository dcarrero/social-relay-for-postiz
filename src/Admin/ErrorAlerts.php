<?php
/**
 * Tells the site owner when shares fail: an admin notice and, if enabled, an email.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Postiz\ProviderRules;
use SocialRelayCvi\Queue\Queue;

/**
 * Only failures that are still open count (a later successful retry closes them).
 */
final class ErrorAlerts implements Module {

	public const OPTION_EMAIL = 'socialrelaycvi_error_email';

	public const OPTION_EMAIL_SENT = 'socialrelaycvi_error_email_sent';

	public const OPTION_EMAIL_CHECKED = 'socialrelaycvi_error_email_checked';

	public const USER_SEEN = 'socialrelaycvi_errors_seen';

	private const ACTION_HIDE = 'socialrelaycvi_hide_errors';

	/**
	 * Errors older than this never show in the notice.
	 */
	private const WINDOW = 7 * DAY_IN_SECONDS;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION_HIDE, array( $this, 'hide' ) );
		// After each status sync (every 15 minutes), at most one email per hour.
		add_action( Queue::HOOK_SYNC, array( $this, 'maybe_email' ), 20, 0 );
	}

	/**
	 * Admin notice with the open failures since the user last hid it.
	 */
	public function notice(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			return;
		}

		$screen = get_current_screen();
		// The block editor has its own status panel; the log already lists errors.
		if ( null === $screen || $screen->is_block_editor() || str_contains( (string) $screen->id, 'social-relay-log' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$since   = max( time() - self::WINDOW, (int) get_user_meta( $user_id, self::USER_SEEN, true ) );
		$errors  = $this->plugin->shares->open_errors( gmdate( 'Y-m-d H:i:s', $since ), 4 );

		if ( array() === $errors ) {
			return;
		}

		$hide_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_HIDE ), self::ACTION_HIDE );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Social Relay: some shares failed in Postiz.', 'social-relay-for-postiz' ); ?></strong>
			</p>
			<ul style="margin:0 0 8px 1.2em;list-style:disc">
				<?php foreach ( array_slice( $errors, 0, 3 ) as $row ) : ?>
					<li><?php echo wp_kses_post( $this->describe( $row, true ) ); ?></li>
				<?php endforeach; ?>
				<?php if ( count( $errors ) > 3 ) : ?>
					<li><?php esc_html_e( 'And more.', 'social-relay-for-postiz' ); ?></li>
				<?php endif; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?' . PostsColumn::FILTER . '=error' ) ); ?>"><?php esc_html_e( 'Posts with errors', 'social-relay-for-postiz' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=social-relay-log&level=error' ) ); ?>"><?php esc_html_e( 'Log', 'social-relay-for-postiz' ); ?></a>
				<a href="<?php echo esc_url( $hide_url ); ?>" style="margin-left:8px"><?php esc_html_e( 'Hide until new errors', 'social-relay-for-postiz' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * "Hide until new errors": remembers when this user last saw the notice.
	 */
	public function hide(): void {
		check_admin_referer( self::ACTION_HIDE );

		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'social-relay-for-postiz' ), 403 );
		}

		update_user_meta( get_current_user_id(), self::USER_SEEN, time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Emails a summary of new failures, when enabled. At most one email per hour.
	 */
	public function maybe_email(): void {
		if ( ! get_option( self::OPTION_EMAIL, false ) ) {
			return;
		}

		$now = time();
		if ( $now - (int) get_option( self::OPTION_EMAIL_SENT, 0 ) < HOUR_IN_SECONDS ) {
			return; // Failures wait for the next email: the "checked" mark does not move.
		}

		$checked = (int) get_option( self::OPTION_EMAIL_CHECKED, 0 );
		$errors  = $this->plugin->shares->open_errors( gmdate( 'Y-m-d H:i:s', max( $checked, $now - DAY_IN_SECONDS ) ), 20 );
		update_option( self::OPTION_EMAIL_CHECKED, $now, false );

		if ( array() === $errors ) {
			return;
		}

		$lines = array_map( fn ( array $row ): string => '- ' . $this->describe( $row, false ), $errors );

		/**
		 * Filters who gets the failure emails.
		 *
		 * @param string|list<string> $to Default: the site admin email.
		 */
		$to = apply_filters( 'socialrelaycvi_error_email_recipient', (string) get_option( 'admin_email' ) );

		$sent = wp_mail(
			$to,
			/* translators: %s: site name. */
			sprintf( __( '[%s] Some social media shares failed', 'social-relay-for-postiz' ), wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) ),
			implode(
				"\n\n",
				array(
					__( 'These shares to Postiz failed and were not retried with success:', 'social-relay-for-postiz' ),
					implode( "\n", $lines ),
					/* translators: %s: URL of the posts list filtered by errors. */
					sprintf( __( 'Posts with errors: %s', 'social-relay-for-postiz' ), admin_url( 'edit.php?' . PostsColumn::FILTER . '=error' ) ),
					__( 'You get this email because failure emails are on in Social Relay → Settings → Advanced.', 'social-relay-for-postiz' ),
				)
			)
		);

		if ( $sent ) {
			update_option( self::OPTION_EMAIL_SENT, $now, false );
		}
	}

	/**
	 * "Post title — Channel (network): error".
	 *
	 * @param array<string, mixed> $row  Share row.
	 * @param bool                 $html Link the title to the editor (escaped HTML) or plain text.
	 */
	private function describe( array $row, bool $html ): string {
		$post_id  = (int) $row['post_id'];
		$channels = $this->plugin->channels->by_id();
		$channel  = is_wp_error( $channels ) ? null : ( $channels[ (string) $row['integration_id'] ] ?? null );
		$network  = ProviderRules::network( (string) $row['identifier'] )['label'];
		$where    = null === $channel ? $network : $channel['name'] . ' (' . $network . ')';
		$title    = get_the_title( $post_id );
		$title    = '' !== $title ? $title : __( '(no title)', 'social-relay-for-postiz' );
		$error    = '' !== (string) $row['error'] ? (string) $row['error'] : __( 'Error', 'social-relay-for-postiz' );

		if ( ! $html ) {
			return sprintf( '%1$s — %2$s: %3$s', wp_strip_all_tags( $title ), $where, $error );
		}

		$link = get_edit_post_link( $post_id );

		return sprintf(
			'%1$s — %2$s: %3$s',
			$link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ),
			esc_html( $where ),
			esc_html( $error )
		);
	}
}
