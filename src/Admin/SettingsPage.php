<?php
/**
 * Settings screen: connection and publishing profiles.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\Postiz\EndpointResolver;
use SocialRelayCvi\Profiles\ProfileRepository;
use SocialRelayCvi\Settings\Connection;
use SocialRelayCvi\Settings\ConnectionTester;

/**
 * Server-rendered settings page for 0.1. The React version (DataForm) comes in 0.3.
 */
final class SettingsPage implements Module {

	public const SLUG       = 'social-relay';
	public const CAPABILITY = 'manage_options';

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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_socialrelaycvi_save_connection', array( $this, 'save_connection' ) );
		add_action( 'admin_post_socialrelaycvi_save_profiles', array( $this, 'save_profiles' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SOCIALRELAYCVI_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the top-level menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Social Relay for Postiz', 'social-relay-for-postiz' ),
			__( 'Social Relay', 'social-relay-for-postiz' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-share',
			30
		);

		add_submenu_page( self::SLUG, __( 'Settings', 'social-relay-for-postiz' ), __( 'Settings', 'social-relay-for-postiz' ), self::CAPABILITY, self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Adds "Settings" to the plugins list.
	 *
	 * @param array<string> $links Links.
	 * @return array<string>
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'social-relay-for-postiz' ) ) );

		return $links;
	}

	/**
	 * Saves URL and key, then tests the connection.
	 */
	public function save_connection(): void {
		$this->guard( 'socialrelaycvi_save_connection' );

		$url = isset( $_POST['instance_url'] ) ? EndpointResolver::normalize( sanitize_text_field( wp_unslash( $_POST['instance_url'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		update_option( Connection::OPTION_URL, esc_url_raw( $url ), false );

		$connection = $this->plugin->connection;
		$new_key    = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().

		if ( '' !== $new_key && $connection->key_is_editable() ) {
			update_option( Connection::OPTION_API_KEY, $new_key, false );
		}

		$result = ( new ConnectionTester( $this->plugin ) )->run();

		$this->redirect(
			is_wp_error( $result )
				? array(
					'socialrelaycvi_notice' => 'error',
					'socialrelaycvi_msg'    => rawurlencode( $result->get_error_message() ), // Decoded by PHP into $_GET.
				)
				: array(
					'socialrelaycvi_notice' => 'connected',
					'socialrelaycvi_count'  => $result['channels'],
				)
		);
	}

	/**
	 * Saves the publishing profiles.
	 */
	public function save_profiles(): void {
		$this->guard( 'socialrelaycvi_save_profiles' );

		$raw      = isset( $_POST['profiles'] ) && is_array( $_POST['profiles'] ) ? wp_unslash( $_POST['profiles'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in guard(); sanitized per field below.
		$profiles = array();

		foreach ( $this->post_types() as $post_type => $label ) {
			$profiles[ $post_type ] = ProfileRepository::sanitize( isset( $raw[ $post_type ] ) && is_array( $raw[ $post_type ] ) ? $raw[ $post_type ] : array() );
		}

		update_option( ProfileRepository::OPTION, $profiles, false );

		$this->redirect( array( 'socialrelaycvi_notice' => 'profiles_saved' ) );
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$connection = $this->plugin->connection;
		$channels   = $connection->is_configured() ? $this->plugin->channels->all() : array();
		$profiles   = $this->plugin->profiles->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Social Relay for Postiz', 'social-relay-for-postiz' ); ?></h1>
			<?php $this->render_notice(); ?>

			<h2><?php esc_html_e( 'Postiz connection', 'social-relay-for-postiz' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="socialrelaycvi_save_connection">
				<?php wp_nonce_field( 'socialrelaycvi_save_connection' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="socialrelaycvi-url"><?php esc_html_e( 'Postiz URL', 'social-relay-for-postiz' ); ?></label></th>
						<td>
							<input type="url" id="socialrelaycvi-url" name="instance_url" class="regular-text" value="<?php echo esc_attr( $connection->instance_url() ); ?>" placeholder="https://postiz.example.com" required>
							<p class="description">
								<?php esc_html_e( 'Your self-hosted Postiz address. For Postiz Cloud, use https://api.postiz.com.', 'social-relay-for-postiz' ); ?>
								<?php if ( '' !== $connection->api_base() ) : ?>
									<br><?php esc_html_e( 'Detected API:', 'social-relay-for-postiz' ); ?> <code><?php echo esc_html( $connection->api_base() ); ?></code>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="socialrelaycvi-key"><?php esc_html_e( 'API key', 'social-relay-for-postiz' ); ?></label></th>
						<td>
							<?php if ( $connection->key_is_editable() ) : ?>
								<input type="password" id="socialrelaycvi-key" name="api_key" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $connection->masked_key() ? $connection->masked_key() : __( 'Paste your API key', 'social-relay-for-postiz' ) ); ?>">
								<p class="description"><?php esc_html_e( 'Postiz → Settings → Developers → Public API. Leave empty to keep the current key. For better security define SOCIALRELAYCVI_POSTIZ_API_KEY in wp-config.php or the POSTIZ_API_KEY environment variable.', 'social-relay-for-postiz' ); ?></p>
							<?php else : ?>
								<code><?php echo esc_html( $connection->masked_key() ); ?></code>
								<p class="description">
									<?php
									echo esc_html(
										'env' === $connection->key_source()
											? __( 'Read from the POSTIZ_API_KEY environment variable.', 'social-relay-for-postiz' )
											: __( 'Defined in wp-config.php (SOCIALRELAYCVI_POSTIZ_API_KEY).', 'social-relay-for-postiz' )
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save and test connection', 'social-relay-for-postiz' ) ); ?>
			</form>

			<?php if ( $connection->is_configured() ) : ?>
				<h2><?php esc_html_e( 'Publishing profiles', 'social-relay-for-postiz' ); ?></h2>
				<?php if ( is_wp_error( $channels ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $channels->get_error_message() ); ?></p></div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="socialrelaycvi_save_profiles">
						<?php wp_nonce_field( 'socialrelaycvi_save_profiles' ); ?>
						<?php foreach ( $this->post_types() as $post_type => $label ) : ?>
							<?php $this->render_profile( $post_type, $label, $profiles[ $post_type ] ?? ProfileRepository::defaults(), $channels ); ?>
						<?php endforeach; ?>
						<?php submit_button( __( 'Save profiles', 'social-relay-for-postiz' ) ); ?>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders one profile block.
	 *
	 * @param string                           $post_type Post type.
	 * @param string                           $label     Post type label.
	 * @param array<string, mixed>             $profile   Profile.
	 * @param list<array<string, string|bool>> $channels  Channels.
	 */
	private function render_profile( string $post_type, string $label, array $profile, array $channels ): void {
		$name  = static fn ( string $field ): string => sprintf( 'profiles[%s][%s]', $post_type, $field );
		$id    = static fn ( string $field ): string => sprintf( 'socialrelaycvi-%s-%s', $post_type, $field );
		$modes = array(
			'now'      => __( 'Publish immediately', 'social-relay-for-postiz' ),
			'schedule' => __( 'Schedule with a delay', 'social-relay-for-postiz' ),
			'slot'     => __( 'Next free slot in Postiz', 'social-relay-for-postiz' ),
			'draft'    => __( 'Create as draft in Postiz', 'social-relay-for-postiz' ),
		);
		?>
		<fieldset class="socialrelaycvi-profile" style="background:#fff;border:1px solid #dcdcde;padding:12px 20px;margin:0 0 16px;max-width:900px">
			<legend style="font-weight:600;font-size:14px;padding:0 6px"><?php echo esc_html( $label ); ?></legend>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name( 'enabled' ) ); ?>" value="1" <?php checked( ! empty( $profile['enabled'] ) ); ?>>
					<?php
					/* translators: %s: post type label, e.g. Posts. */
					printf( esc_html__( 'Share %s when they are published', 'social-relay-for-postiz' ), esc_html( strtolower( $label ) ) );
					?>
				</label>
			</p>
			<p><strong><?php esc_html_e( 'Channels', 'social-relay-for-postiz' ); ?></strong><br>
				<?php if ( array() === $channels ) : ?>
					<em><?php esc_html_e( 'No channels in Postiz yet. Add them in Postiz and reload this page.', 'social-relay-for-postiz' ); ?></em>
				<?php endif; ?>
				<?php foreach ( $channels as $channel ) : ?>
					<label style="display:inline-block;margin:4px 16px 4px 0">
						<input type="checkbox" name="<?php echo esc_attr( $name( 'channels' ) ); ?>[]" value="<?php echo esc_attr( (string) $channel['id'] ); ?>" <?php checked( in_array( $channel['id'], (array) $profile['channels'], true ) ); ?> <?php disabled( (bool) $channel['disabled'] ); ?>>
						<?php echo esc_html( sprintf( '%s (%s)', (string) $channel['name'], (string) $channel['identifier'] ) ); ?>
					</label>
				<?php endforeach; ?>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id( 'template' ) ); ?>"><strong><?php esc_html_e( 'Message template', 'social-relay-for-postiz' ); ?></strong></label><br>
				<textarea id="<?php echo esc_attr( $id( 'template' ) ); ?>" name="<?php echo esc_attr( $name( 'template' ) ); ?>" rows="5" class="large-text code"><?php echo esc_textarea( (string) $profile['template'] ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Tags: {title} {excerpt} {content} {url} {shortlink} {author} {date} {site_name} {categories} {tags} {hashtags:post_tag} {term:taxonomy} {meta:key} {seo_title} {seo_description}. Filters: |upper |lower |strip |hashtag |limit:100. A line with --- starts a new thread part.', 'social-relay-for-postiz' ); ?></span>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id( 'mode' ) ); ?>"><strong><?php esc_html_e( 'When', 'social-relay-for-postiz' ); ?></strong></label>
				<select id="<?php echo esc_attr( $id( 'mode' ) ); ?>" name="<?php echo esc_attr( $name( 'mode' ) ); ?>">
					<?php foreach ( $modes as $value => $text ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $profile['mode'], $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="<?php echo esc_attr( $id( 'delay' ) ); ?>"><?php esc_html_e( 'Delay (minutes, for “Schedule”)', 'social-relay-for-postiz' ); ?></label>
				<input type="number" min="0" max="43200" id="<?php echo esc_attr( $id( 'delay' ) ); ?>" name="<?php echo esc_attr( $name( 'delay_minutes' ) ); ?>" value="<?php echo esc_attr( (string) $profile['delay_minutes'] ); ?>" class="small-text">
			</p>
			<p>
				<input type="hidden" name="<?php echo esc_attr( $name( 'use_featured_image' ) ); ?>" value="0">
				<label><input type="checkbox" name="<?php echo esc_attr( $name( 'use_featured_image' ) ); ?>" value="1" <?php checked( ! empty( $profile['use_featured_image'] ) ); ?>> <?php esc_html_e( 'Attach the featured image', 'social-relay-for-postiz' ); ?></label>
				<br>
				<label><input type="checkbox" name="<?php echo esc_attr( $name( 'share_on_update' ) ); ?>" value="1" <?php checked( ! empty( $profile['share_on_update'] ) ); ?>> <?php esc_html_e( 'Allow sharing again on update (when requested from the editor)', 'social-relay-for-postiz' ); ?></label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Shows the result of the last action.
	 */
	private function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only flags set by our own redirect.
		$notice = isset( $_GET['socialrelaycvi_notice'] ) ? sanitize_key( wp_unslash( $_GET['socialrelaycvi_notice'] ) ) : '';

		if ( 'connected' === $notice ) {
			$count = isset( $_GET['socialrelaycvi_count'] ) ? absint( $_GET['socialrelaycvi_count'] ) : 0;
			/* translators: %d: number of channels. */
			$text = sprintf( _n( 'Connected to Postiz. %d channel available.', 'Connected to Postiz. %d channels available.', $count, 'social-relay-for-postiz' ), $count );
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
		} elseif ( 'profiles_saved' === $notice ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Profiles saved.', 'social-relay-for-postiz' ) );
		} elseif ( 'error' === $notice ) {
			$message = isset( $_GET['socialrelaycvi_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['socialrelaycvi_msg'] ) ) : '';
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
		// phpcs:enable
	}

	/**
	 * Public post types with an admin UI.
	 *
	 * @return array<string, string> Name => label.
	 */
	private function post_types(): array {
		$types = array();

		foreach ( get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$types[ $type->name ] = (string) $type->labels->name;
			}
		}

		/**
		 * Filters the post types that get a publishing profile.
		 *
		 * @param array<string, string> $types Post type name => label.
		 */
		return (array) apply_filters( 'socialrelaycvi_post_types', $types );
	}

	/**
	 * Capability and nonce check for admin-post handlers.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'social-relay-for-postiz' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirects back to the settings page.
	 *
	 * @param array<string, string|int> $args Query arguments.
	 */
	private function redirect( array $args ): never {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}
}
