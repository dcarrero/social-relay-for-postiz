<?php
/**
 * "Social networks" column and filter in the posts list.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\PostMeta;
use SocialRelayCvi\Postiz\ProviderRules;
use WP_Query;

/**
 * One badge per channel with the latest status, and a filter for posts with errors or pending shares.
 *
 * Only on post types whose publishing profile is on.
 */
final class PostsColumn implements Module {

	public const COLUMN = 'socialrelaycvi';

	public const FILTER = 'socialrelaycvi_status';

	private const COLORS = array(
		'queued'    => '#3858e9',
		'published' => '#007a1f',
		'error'     => '#cc1818',
		'draft'     => '#646970',
		'cancelled' => '#646970',
	);

	/**
	 * A sign per status inside the dot, so it does not rely on colour alone.
	 */
	private const GLYPHS = array(
		'queued'    => '…',
		'published' => '✓',
		'error'     => '!',
		'draft'     => '✎',
		'cancelled' => '×',
	);

	/**
	 * Channels by id, loaded once per request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $channels = null;

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
		add_action( 'load-edit.php', array( $this, 'setup' ) );
	}

	/**
	 * Hooks the column and the filter on the posts list of an enabled post type.
	 */
	public function setup(): void {
		$screen = get_current_screen();
		if ( null === $screen || ! $this->enabled( $screen->post_type ) ) {
			return;
		}

		add_filter( "manage_{$screen->post_type}_posts_columns", array( $this, 'add_column' ) );
		add_action( "manage_{$screen->post_type}_posts_custom_column", array( $this, 'render' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Adds the column before the date.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$label = __( 'Social networks', 'social-relay-for-postiz' );
		$out   = array();

		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$out[ self::COLUMN ] = $label;
			}
			$out[ $key ] = $title;
		}

		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = $label;
		}

		return $out;
	}

	/**
	 * Prints the badges of a post.
	 *
	 * @param string $column  Column id.
	 * @param int    $post_id Post id.
	 */
	public function render( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$latest = array();
		foreach ( $this->plugin->shares->for_post( $post_id ) as $row ) {
			$latest[ (string) $row['integration_id'] ] ??= $row; // Newest first.
		}

		if ( array() === $latest ) {
			$off = ! get_post_meta( $post_id, PostMeta::ENABLED, true );
			printf(
				'<span class="socialrelaycvi-col__none">%s</span>',
				esc_html( $off ? __( 'Sharing off', 'social-relay-for-postiz' ) : '—' )
			);
			return;
		}

		echo '<span class="socialrelaycvi-col">';
		foreach ( $latest as $id => $row ) {
			$channel = $this->channels()[ $id ] ?? null;
			$net     = ProviderRules::network( (string) $row['identifier'] );
			$status  = (string) $row['status'];
			$name    = null === $channel ? $net['label'] : (string) $channel['name'];
			$title   = sprintf( '%1$s · %2$s: %3$s', $name, $net['label'], StatusLabels::label( $status ) );

			if ( '' !== (string) $row['error'] ) {
				$title .= ' — ' . $row['error'];
			}

			$picture = null === $channel ? '' : (string) $channel['picture'];
			$face    = '' !== $picture
				? sprintf( '<img src="%s" alt="" loading="lazy">', esc_url( $picture ) )
				: sprintf( '<span aria-hidden="true">%s</span>', esc_html( mb_strtoupper( mb_substr( $net['label'], 0, 1 ) ) ) );

			printf(
				'<span class="socialrelaycvi-col__badge" style="background:%1$s" title="%2$s">%3$s<i style="background:%4$s" aria-hidden="true">%5$s</i><span class="screen-reader-text">%2$s</span></span>',
				esc_attr( $net['color'] ),
				esc_attr( $title ),
				$face, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				esc_attr( self::COLORS[ $status ] ?? '#646970' ),
				esc_html( self::GLYPHS[ $status ] ?? '' )
			);
		}
		echo '</span>';
	}

	/**
	 * "All social statuses / With errors / Queued or draft" above the list.
	 *
	 * @param string $post_type Post type of the list.
	 */
	public function filter_dropdown( string $post_type ): void {
		if ( ! $this->enabled( $post_type ) ) {
			return;
		}

		$current = $this->current_filter();
		$options = array(
			''        => __( 'All social statuses', 'social-relay-for-postiz' ),
			'error'   => __( 'Social: with errors', 'social-relay-for-postiz' ),
			'pending' => __( 'Social: queued or draft', 'social-relay-for-postiz' ),
		);
		?>
		<label for="socialrelaycvi-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by social network status', 'social-relay-for-postiz' ); ?></label>
		<select name="<?php echo esc_attr( self::FILTER ); ?>" id="socialrelaycvi-filter">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Limits the main list query to the posts of the chosen status.
	 *
	 * @param WP_Query $query Query.
	 */
	public function filter_query( WP_Query $query ): void {
		$filter = $this->current_filter();
		if ( '' === $filter || ! $query->is_main_query() ) {
			return;
		}

		$ids = 'error' === $filter
			? $this->plugin->shares->posts_with_status( 'error' )
			: array_merge( $this->plugin->shares->posts_with_status( 'queued' ), $this->plugin->shares->posts_with_status( 'draft' ) );

		// No match must show no posts, not every post.
		$query->set( 'post__in', array() === $ids ? array( 0 ) : array_values( array_unique( $ids ) ) );
	}

	/**
	 * Badge styles, only on the posts list.
	 */
	public function styles(): void {
		wp_register_style( 'socialrelaycvi-posts-column', false, array(), SOCIALRELAYCVI_VERSION );
		wp_enqueue_style( 'socialrelaycvi-posts-column' );
		wp_add_inline_style(
			'socialrelaycvi-posts-column',
			'.column-socialrelaycvi{width:9em}'
			. '.socialrelaycvi-col{display:flex;flex-wrap:wrap;gap:4px}'
			. '.socialrelaycvi-col__badge{position:relative;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;color:#fff;font-size:11px;font-weight:600;line-height:1}'
			. '.socialrelaycvi-col__badge img{width:22px;height:22px;border-radius:50%;object-fit:cover;background:#fff}'
			. '.socialrelaycvi-col__badge i{position:absolute;right:-4px;bottom:-4px;display:flex;align-items:center;justify-content:center;width:12px;height:12px;border:2px solid #fff;border-radius:50%;color:#fff;font-size:9px;font-style:normal;font-weight:700;line-height:1}'
			. '.socialrelaycvi-col__none{color:#646970}'
		);
	}

	/**
	 * Whether the publishing profile of a post type is on.
	 *
	 * @param string $post_type Post type.
	 */
	private function enabled( string $post_type ): bool {
		return '' !== $post_type && (bool) $this->plugin->profiles->for_post_type( $post_type )['enabled'];
	}

	/**
	 * Filter chosen in the list ('' when none).
	 */
	private function current_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter, like core's own filters.
		$value = isset( $_GET[ self::FILTER ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER ] ) ) : '';

		return in_array( $value, array( 'error', 'pending' ), true ) ? $value : '';
	}

	/**
	 * Channels by id from the cache; empty when Postiz cannot be reached.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function channels(): array {
		if ( null === $this->channels ) {
			$channels       = $this->plugin->channels->by_id();
			$this->channels = is_wp_error( $channels ) ? array() : $channels;
		}

		return $this->channels;
	}
}
