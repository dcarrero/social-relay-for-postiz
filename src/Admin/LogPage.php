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
 * Shows the latest log entries. The DataViews table with filters comes in 0.3.
 */
final class LogPage implements Module {

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
		add_submenu_page(
			SettingsPage::SLUG,
			__( 'Log', 'social-relay-for-postiz' ),
			__( 'Log', 'social-relay-for-postiz' ),
			SettingsPage::CAPABILITY,
			'social-relay-log',
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			return;
		}

		$rows = $this->plugin->logger->latest( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Log', 'social-relay-for-postiz' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'social-relay-for-postiz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Level', 'social-relay-for-postiz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'social-relay-for-postiz' ); ?></th>
						<th scope="col"><?php echo esc_html_x( 'Post', 'WordPress post', 'social-relay-for-postiz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Channel', 'social-relay-for-postiz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'social-relay-for-postiz' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( array() === $rows ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Nothing logged yet.', 'social-relay-for-postiz' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php $post_id = (int) $row['post_id']; ?>
						<tr>
							<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
							<td><?php echo 'error' === $row['level'] ? '<strong style="color:#cc1818">error</strong>' : 'info'; ?></td>
							<td><?php echo esc_html( (string) $row['action'] ); ?></td>
							<td>
								<?php if ( $post_id > 0 ) : ?>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( (string) $row['integration_id'] ); ?></code></td>
							<td><?php echo esc_html( (string) $row['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
