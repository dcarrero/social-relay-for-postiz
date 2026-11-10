<?php
/**
 * Dashboard widget.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;

/**
 * Counters for the last 7 days, latest errors and the next scheduled posts.
 */
final class DashboardWidget implements Module {

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
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
	}

	/**
	 * Adds the widget for users who can manage the plugin.
	 */
	public function add(): void {
		if ( ! current_user_can( SettingsPage::CAPABILITY ) || ! $this->plugin->connection->is_configured() ) {
			return;
		}

		wp_add_dashboard_widget( 'socialrelaycvi_dashboard', __( 'Social networks (Postiz)', 'social-relay-for-postiz' ), array( $this, 'render' ) );
	}

	/**
	 * Renders the widget.
	 */
	public function render(): void {
		$counts   = $this->plugin->shares->counts( 7 );
		$errors   = $this->plugin->shares->latest( 'error', 3 );
		$upcoming = $this->plugin->shares->latest( 'queued', 5, 'upcoming' );
		$channels = $this->plugin->channels->all();
		$names    = is_wp_error( $channels ) ? array() : array_column( $channels, 'name', 'id' );

		$boxes = array(
			array( (int) ( $counts['published'] ?? 0 ), __( 'Published (7 days)', 'social-relay-for-postiz' ), '#007a1f' ),
			array( (int) ( $counts['queued'] ?? 0 ), __( 'Queued', 'social-relay-for-postiz' ), '#3858e9' ),
			array( (int) ( $counts['error'] ?? 0 ), __( 'Errors', 'social-relay-for-postiz' ), '#cc1818' ),
		);
		?>
		<div style="display:flex;gap:8px;margin-bottom:12px">
			<?php foreach ( $boxes as $box ) : ?>
				<div style="flex:1;background:#f6f7f7;border-radius:4px;padding:8px 10px">
					<div style="font-size:20px;font-weight:700;color:<?php echo esc_attr( $box[2] ); ?>"><?php echo esc_html( (string) $box[0] ); ?></div>
					<div style="font-size:12px;color:#646970"><?php echo esc_html( $box[1] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( array() !== $errors ) : ?>
			<p><strong><?php esc_html_e( 'Latest errors', 'social-relay-for-postiz' ); ?></strong></p>
			<ul>
				<?php foreach ( $errors as $row ) : ?>
					<li style="color:#cc1818">
						<?php echo esc_html( (string) ( $names[ $row['integration_id'] ] ?? $row['integration_id'] ) ); ?>:
						<?php echo esc_html( wp_trim_words( (string) $row['error'], 18 ) ); ?>
						— <a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['post_id'] ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p><strong><?php esc_html_e( 'Next', 'social-relay-for-postiz' ); ?></strong></p>
		<?php if ( array() === $upcoming ) : ?>
			<p class="description"><?php esc_html_e( 'Nothing scheduled.', 'social-relay-for-postiz' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $upcoming as $row ) : ?>
					<li>
						<?php echo esc_html( get_date_from_gmt( (string) $row['publish_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?>
						· <?php echo esc_html( (string) ( $names[ $row['integration_id'] ] ?? $row['integration_id'] ) ); ?>
						· <a href="<?php echo esc_url( (string) get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['post_id'] ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=social-relay-log' ) ); ?>"><?php esc_html_e( 'Log', 'social-relay-for-postiz' ); ?></a>
			· <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ); ?>"><?php esc_html_e( 'Settings', 'social-relay-for-postiz' ); ?></a>
			<?php if ( '' !== $this->plugin->connection->instance_url() ) : ?>
				· <a href="<?php echo esc_url( $this->plugin->connection->instance_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Postiz', 'social-relay-for-postiz' ); ?></a>
			<?php endif; ?>
		</p>
		<?php
	}
}
