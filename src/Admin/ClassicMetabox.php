<?php
/**
 * Metabox for the classic editor.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\PostMeta;
use WP_Post;

/**
 * Reduced version of the block editor panel: on/off, channels, common text and status.
 *
 * Only shown when the block editor is not used for the post (classic editor plugin,
 * post types without block editor support).
 */
final class ClassicMetabox implements Module {

	private const NONCE = 'socialrelaycvi_metabox';

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
		add_action( 'add_meta_boxes', array( $this, 'add' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Adds the metabox when the post type has an enabled profile and the classic editor is used.
	 *
	 * @param string       $post_type Post type.
	 * @param WP_Post|null $post      Post.
	 */
	public function add( string $post_type, $post = null ): void {
		if ( ! $post instanceof WP_Post || use_block_editor_for_post( $post ) ) {
			return;
		}

		if ( ! $this->plugin->profiles->for_post_type( $post_type )['enabled'] ) {
			return;
		}

		add_meta_box( 'socialrelaycvi', __( 'Social networks (Postiz)', 'social-relay-for-postiz' ), array( $this, 'render' ), $post_type, 'side' );
	}

	/**
	 * Renders the metabox.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render( WP_Post $post ): void {
		$profile  = $this->plugin->profiles->for_post_type( $post->post_type );
		$channels = $this->plugin->channels->all();
		$channels = is_wp_error( $channels ) ? array() : $channels;
		$chosen   = get_post_meta( $post->ID, PostMeta::CHANNELS, true );
		$selected = is_array( $chosen ) && array() !== $chosen ? $chosen : $profile['channels'];
		$enabled  = (bool) get_post_meta( $post->ID, PostMeta::ENABLED, true );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<input type="hidden" name="socialrelaycvi[enabled]" value="0">
			<label><input type="checkbox" name="socialrelaycvi[enabled]" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Share when publishing', 'social-relay-for-postiz' ); ?></label>
		</p>
		<p><strong><?php esc_html_e( 'Channels', 'social-relay-for-postiz' ); ?></strong><br>
			<?php foreach ( $channels as $channel ) : ?>
				<label style="display:block;margin:2px 0">
					<input type="checkbox" name="socialrelaycvi[channels][]" value="<?php echo esc_attr( $channel['id'] ); ?>" <?php checked( in_array( $channel['id'], $selected, true ) ); ?> <?php disabled( $channel['disabled'] ); ?>>
					<?php echo esc_html( $channel['name'] ); ?> <span class="description">· <?php echo esc_html( $channel['identifier'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</p>
		<?php
		$mode       = (string) get_post_meta( $post->ID, PostMeta::MODE, true );
		$publish_at = (string) get_post_meta( $post->ID, PostMeta::PUBLISH_AT, true );
		$modes      = array(
			''         => __( 'As in the profile', 'social-relay-for-postiz' ),
			'now'      => __( 'On publish', 'social-relay-for-postiz' ),
			'schedule' => __( 'Schedule', 'social-relay-for-postiz' ),
			'slot'     => __( 'Next free slot', 'social-relay-for-postiz' ),
			'draft'    => __( 'Draft in Postiz', 'social-relay-for-postiz' ),
		);
		?>
		<p>
			<label for="socialrelaycvi-mode"><strong><?php esc_html_e( 'When', 'social-relay-for-postiz' ); ?></strong></label><br>
			<select id="socialrelaycvi-mode" name="socialrelaycvi[mode]" class="widefat">
				<?php foreach ( $modes as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="socialrelaycvi-publish-at" class="screen-reader-text"><?php esc_html_e( 'Date and time', 'social-relay-for-postiz' ); ?></label>
			<input type="datetime-local" id="socialrelaycvi-publish-at" name="socialrelaycvi[publish_at]" class="widefat" value="<?php echo esc_attr( substr( $publish_at, 0, 16 ) ); ?>" style="margin-top:4px">
			<span class="description"><?php esc_html_e( 'The date is used with "Schedule". Empty: the profile delay after publishing.', 'social-relay-for-postiz' ); ?></span>
		</p>
		<?php
		$images  = PostMeta::sanitize_images( get_post_meta( $post->ID, PostMeta::IMAGES, true ) );
		$choice  = array() === $images ? '' : ( array( PostMeta::NO_IMAGES ) === $images ? 'none' : 'keep' );
		$choices = array(
			''     => __( 'As in the profile', 'social-relay-for-postiz' ),
			'none' => __( 'No images', 'social-relay-for-postiz' ),
		);
		if ( 'keep' === $choice ) {
			/* translators: %d: number of images. */
			$choices['keep'] = sprintf( _n( '%d image chosen in the block editor', '%d images chosen in the block editor', count( $images ), 'social-relay-for-postiz' ), count( $images ) );
		}
		?>
		<p>
			<label for="socialrelaycvi-images"><strong><?php esc_html_e( 'Images', 'social-relay-for-postiz' ); ?></strong></label><br>
			<select id="socialrelaycvi-images" name="socialrelaycvi[images]" class="widefat">
				<?php foreach ( $choices as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $choice, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="socialrelaycvi-message"><strong><?php esc_html_e( 'Text for all networks', 'social-relay-for-postiz' ); ?></strong></label>
			<textarea id="socialrelaycvi-message" name="socialrelaycvi[message]" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Empty = profile template', 'social-relay-for-postiz' ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, PostMeta::MESSAGE, true ) ); ?></textarea>
		</p>
		<?php if ( 'publish' === $post->post_status && $profile['share_on_update'] ) : ?>
			<p><label><input type="checkbox" name="socialrelaycvi[reshare]" value="1"> <?php esc_html_e( 'Share again on the next update', 'social-relay-for-postiz' ); ?></label></p>
		<?php endif; ?>
		<?php
		$this->render_status( $post->ID, $channels );
	}

	/**
	 * Saves the metabox fields.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 */
	public function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['socialrelaycvi'] ) && is_array( $_POST['socialrelaycvi'] ) ? wp_unslash( $_POST['socialrelaycvi'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.

		update_post_meta( $post_id, PostMeta::ENABLED, ! empty( $raw['enabled'] ) );
		update_post_meta( $post_id, PostMeta::MESSAGE, sanitize_textarea_field( (string) ( $raw['message'] ?? '' ) ) );

		$mode = sanitize_key( (string) ( $raw['mode'] ?? '' ) );
		update_post_meta( $post_id, PostMeta::MODE, in_array( $mode, PostMeta::MODES, true ) ? $mode : '' );
		update_post_meta( $post_id, PostMeta::PUBLISH_AT, PostMeta::sanitize_datetime( (string) ( $raw['publish_at'] ?? '' ) ) );

		$images = sanitize_key( (string) ( $raw['images'] ?? '' ) );
		if ( 'none' === $images ) {
			update_post_meta( $post_id, PostMeta::IMAGES, array( PostMeta::NO_IMAGES ) );
		} elseif ( '' === $images ) {
			update_post_meta( $post_id, PostMeta::IMAGES, array() );
		}

		$profile  = $this->plugin->profiles->for_post_type( $post->post_type );
		$channels = isset( $raw['channels'] ) && is_array( $raw['channels'] ) ? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $raw['channels'] ) ) ) : array();
		sort( $channels );
		$default = $profile['channels'];
		sort( $default );

		// Same list as the profile: keep it empty so later profile changes still apply. Nothing ticked: no channels.
		if ( $channels === $default ) {
			$channels = array();
		} elseif ( array() === $channels ) {
			$channels = array( PostMeta::NO_CHANNELS );
		}
		update_post_meta( $post_id, PostMeta::CHANNELS, $channels );

		if ( ! empty( $raw['reshare'] ) ) {
			update_post_meta( $post_id, PostMeta::RESHARE, true );
		}
	}

	/**
	 * Latest status per channel.
	 *
	 * @param int                              $post_id  Post id.
	 * @param list<array<string, string|bool>> $channels Channels.
	 */
	private function render_status( int $post_id, array $channels ): void {
		$rows = $this->plugin->shares->for_post( $post_id );
		if ( array() === $rows ) {
			return;
		}

		$names = array_column( $channels, 'name', 'id' );
		$seen  = array();
		?>
		<p><strong><?php esc_html_e( 'Status', 'social-relay-for-postiz' ); ?></strong></p>
		<ul style="margin-top:0">
			<?php foreach ( $rows as $row ) : ?>
				<?php
				if ( isset( $seen[ $row['integration_id'] ] ) ) {
					continue;
				}
				$seen[ $row['integration_id'] ] = true;
				?>
				<li>
					<?php echo esc_html( (string) ( $names[ $row['integration_id'] ] ?? $row['integration_id'] ) ); ?>:
					<strong><?php echo esc_html( StatusLabels::label( (string) $row['status'] ) ); ?></strong>
					<?php if ( ! empty( $row['release_url'] ) ) : ?>
						· <a href="<?php echo esc_url( (string) $row['release_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View post', 'social-relay-for-postiz' ); ?></a>
					<?php endif; ?>
					<?php if ( ! empty( $row['error'] ) ) : ?>
						<br><span style="color:#cc1818"><?php echo esc_html( (string) $row['error'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
