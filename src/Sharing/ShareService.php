<?php
/**
 * Sends a WordPress post to Postiz.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sharing;

use SocialRelayCvi\Module;
use SocialRelayCvi\Plugin;
use SocialRelayCvi\PostMeta;
use SocialRelayCvi\Postiz\Gateway;
use SocialRelayCvi\Postiz\ProviderRules;
use SocialRelayCvi\Queue\Queue;
use SocialRelayCvi\Templates\PostContext;
use SocialRelayCvi\Templates\TemplateEngine;
use WP_Error;
use WP_Post;

/**
 * Runs the share job: builds one message per channel and creates the posts in Postiz.
 */
final class ShareService implements Module {

	public const MAX_ATTEMPTS = 3;

	/**
	 * Networks where Postiz publishes several value[] items as a thread.
	 */
	private const THREAD_NETWORKS = array( 'x', 'bluesky', 'mastodon', 'threads', 'nostr', 'warpcast' );

	/**
	 * Template engine.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $engine;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( private readonly Plugin $plugin ) {
		$this->engine = new TemplateEngine();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( Queue::HOOK_SHARE, array( $this, 'handle' ), 10, 2 );
		add_action( Queue::HOOK_CANCEL, array( $this, 'cancel' ) );
	}

	/**
	 * Job handler.
	 *
	 * @param int $post_id Post id.
	 * @param int $attempt Attempt number.
	 */
	public function handle( int $post_id, int $attempt = 1 ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		$profile = $this->plugin->profiles->for_post_type( $post->post_type );
		if ( ! $profile['enabled'] || ! get_post_meta( $post_id, PostMeta::ENABLED, true ) ) {
			return;
		}

		$log = $this->plugin->logger;

		if ( ! $this->passes_conditions( $post, $profile['include_terms'], $profile['exclude_terms'] ) ) {
			$log->info( 'share', __( 'The post does not match the profile conditions; not shared.', 'social-relay-for-postiz' ), $post_id );
			return;
		}

		$client = $this->plugin->gateway();
		if ( null === $client ) {
			$log->error( 'share', __( 'Postiz is not configured; nothing was sent.', 'social-relay-for-postiz' ), $post_id );
			return;
		}

		$channels = $this->plugin->channels->by_id();
		if ( is_wp_error( $channels ) ) {
			$this->fail( $post_id, $attempt, $channels );
			return;
		}

		$prepared = $this->prepare( $post, $profile, $channels );
		foreach ( $prepared['skipped'] as $skip ) {
			$log->error( 'share', $skip['reason'], $post_id, $skip['integration_id'] );
		}
		$items = $prepared['items'];

		if ( array() === $items ) {
			return;
		}

		$reshare = (bool) get_post_meta( $post_id, PostMeta::RESHARE, true );
		$hash    = sha1( (string) wp_json_encode( array( $items, $profile['mode'] ) ) );

		if ( ! $reshare && $this->plugin->shares->was_sent( $post_id, $hash ) ) {
			$log->info( 'share', __( 'Same content was already sent; skipped to avoid a duplicate.', 'social-relay-for-postiz' ), $post_id );
			return;
		}

		$images = $profile['use_featured_image'] ? $this->featured_image( $client, $post ) : array();

		$sendable = array();
		foreach ( $items as $item ) {
			if ( array() === $images && ProviderRules::requires_media( $item['identifier'] ) ) {
				$log->error( 'share', __( 'This network needs an image and the post has none; skipped.', 'social-relay-for-postiz' ), $post_id, $item['integration_id'] );
				continue;
			}
			$item['images'] = $images;
			$sendable[]     = $item;
		}
		$items = $sendable;

		if ( array() === $items ) {
			return;
		}

		$batch   = sprintf( 'wp-%s-%d-%d', substr( md5( home_url() ), 0, 8 ), $post_id, $this->plugin->shares->batch_count( $post_id ) + 1 );
		$request = array(
			'short_link' => $profile['short_link'],
			'tags'       => $profile['postiz_tags'] ? $this->category_names( $post ) : array(),
		);

		if ( 'slot' === $profile['mode'] ) {
			foreach ( $items as $item ) {
				$this->send( $client, $post, array( $item ), $this->slot_time( $client, $item['integration_id'] ), 'schedule', $batch, $hash, $attempt, false, $request );
			}
		} else {
			$type = match ( $profile['mode'] ) {
				'schedule' => PayloadBuilder::TYPE_SCHEDULE,
				'draft'    => PayloadBuilder::TYPE_DRAFT,
				default    => PayloadBuilder::TYPE_NOW,
			};
			$when = 'schedule' === $profile['mode'] ? time() + $profile['delay_minutes'] * MINUTE_IN_SECONDS : time();

			$this->send( $client, $post, $items, $when, $type, $batch, $hash, $attempt, true, $request );
		}

		if ( $reshare ) {
			update_post_meta( $post_id, PostMeta::RESHARE, false );
		}
	}

	/**
	 * Builds the message of every channel of the profile, without calling Postiz.
	 *
	 * @param WP_Post                             $post     Post.
	 * @param array<string, mixed>                $profile  Profile (see ProfileRepository).
	 * @param array<string, array<string, mixed>> $channels Channels by id.
	 * @return array{items: list<array{integration_id: string, identifier: string, parts: list<string>, settings: array<string, mixed>}>, skipped: list<array{integration_id: string, reason: string}>}
	 */
	public function prepare( WP_Post $post, array $profile, array $channels ): array {
		$context   = PostContext::for_post( $post );
		$post_text = (string) get_post_meta( $post->ID, PostMeta::MESSAGE, true );
		$overrides = get_post_meta( $post->ID, PostMeta::OVERRIDES, true );
		$overrides = is_array( $overrides ) ? $overrides : array();

		$items   = array();
		$skipped = array();

		// Channels picked in the editor for this post replace the profile's list.
		$chosen      = get_post_meta( $post->ID, PostMeta::CHANNELS, true );
		$channel_ids = is_array( $chosen ) && array() !== $chosen ? $chosen : (array) $profile['channels'];

		foreach ( $channel_ids as $channel_id ) {
			$channel_id = (string) $channel_id;
			$channel    = $channels[ $channel_id ] ?? null;

			if ( null === $channel ) {
				$skipped[] = array(
					'integration_id' => $channel_id,
					'reason'         => __( 'Channel no longer exists in Postiz; skipped.', 'social-relay-for-postiz' ),
				);
				continue;
			}

			if ( ! empty( $channel['disabled'] ) ) {
				$skipped[] = array(
					'integration_id' => $channel_id,
					'reason'         => __( 'Channel is disabled in Postiz; skipped.', 'social-relay-for-postiz' ),
				);
				continue;
			}

			$identifier = (string) $channel['identifier'];
			$options    = $profile['channel_options'][ $channel_id ] ?? array(
				'template' => '',
				'settings' => array(),
			);
			$settings   = (array) $options['settings'];
			$missing    = ProviderRules::missing_required( $identifier, $settings );

			if ( array() !== $missing ) {
				$skipped[] = array(
					'integration_id' => $channel_id,
					/* translators: %s: list of missing settings. */
					'reason'         => sprintf( __( 'Missing network settings (%s); skipped.', 'social-relay-for-postiz' ), implode( ', ', $missing ) ),
				);
				continue;
			}

			// Most specific wins: post text for this network, post text, profile text for this network, profile text.
			$template = self::first_filled( (string) ( $overrides[ $channel_id ] ?? '' ), $post_text, (string) $options['template'], (string) $profile['template'] );
			$message  = $this->engine->render( $template, $this->channel_context( $context, $profile, $identifier ) );

			$items[] = array(
				'integration_id' => $channel_id,
				'identifier'     => $identifier,
				'parts'          => $this->parts_for( $message, $identifier ),
				'settings'       => $settings,
			);
		}

		return array(
			'items'   => $items,
			'skipped' => $skipped,
		);
	}

	/**
	 * Dry run for a post: what would be sent, without calling Postiz except to read channels.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>|WP_Error
	 */
	public function preview( WP_Post $post ): array|WP_Error {
		$profile  = $this->plugin->profiles->for_post_type( $post->post_type );
		$channels = $this->plugin->channels->by_id();

		if ( is_wp_error( $channels ) ) {
			return $channels;
		}

		$prepared = $this->prepare( $post, $profile, $channels );

		return array(
			'enabled'           => $profile['enabled'] && (bool) get_post_meta( $post->ID, PostMeta::ENABLED, true ),
			'passes_conditions' => $this->passes_conditions( $post, $profile['include_terms'], $profile['exclude_terms'] ),
			'mode'              => $profile['mode'],
			'short_link'        => $profile['short_link'],
			'tags'              => $profile['postiz_tags'] ? $this->category_names( $post ) : array(),
			'items'             => array_map(
				static fn ( array $item ): array => $item + array(
					'name'  => (string) ( $channels[ $item['integration_id'] ]['name'] ?? '' ),
					'limit' => ProviderRules::char_limit( $item['identifier'] ),
				),
				$prepared['items']
			),
			'skipped'           => $prepared['skipped'],
		);
	}

	/**
	 * Cancels in Postiz what is still pending for a post (drafts and future schedules).
	 *
	 * @param int $post_id Post id.
	 */
	public function cancel( int $post_id ): void {
		$batches = $this->plugin->shares->cancellable_batches( $post_id );
		if ( array() === $batches ) {
			return;
		}

		$client = $this->plugin->gateway();
		if ( null === $client ) {
			return;
		}

		foreach ( $batches as $batch ) {
			$result = $client->delete_group( $batch );

			if ( is_wp_error( $result ) ) {
				$this->plugin->logger->error( 'cancel', $result->get_error_message(), $post_id, '', array( 'batch' => $batch ) );
				continue;
			}

			$this->plugin->shares->set_batch_status( $batch, 'cancelled' );
			$this->plugin->logger->info( 'cancel', __( 'Pending posts cancelled in Postiz.', 'social-relay-for-postiz' ), $post_id, '', array( 'batch' => $batch ) );
		}
	}

	/**
	 * Creates the posts in Postiz and records the result.
	 *
	 * @param Gateway                                     $client  Gateway to Postiz.
	 * @param WP_Post                                     $post    Post.
	 * @param list<array<string,mixed>>                   $items   Channel items.
	 * @param int                                         $when    Publish time.
	 * @param string                                      $type    now, schedule or draft.
	 * @param string                                      $batch   Group id.
	 * @param string                                      $hash    Content hash.
	 * @param int                                         $attempt Attempt number.
	 * @param bool                                        $retry   Whether a retryable error re-queues the whole job.
	 * @param array{short_link: bool, tags: list<string>} $request Request-level options.
	 */
	private function send( Gateway $client, WP_Post $post, array $items, int $when, string $type, string $batch, string $hash, int $attempt, bool $retry, array $request ): void {
		$log = $this->plugin->logger;

		try {
			$payload = PayloadBuilder::build( $items, $type, $when, $batch, $request['short_link'], $request['tags'] );
		} catch ( \InvalidArgumentException $e ) {
			$log->error( 'share', $e->getMessage(), $post->ID );
			return;
		}

		/**
		 * Filters the body sent to POST /posts.
		 *
		 * @param array<string, mixed> $payload Request body.
		 * @param WP_Post              $post    Post being shared.
		 */
		$payload  = (array) apply_filters( 'socialrelaycvi_payload', $payload, $post );
		$response = $client->create_posts( $payload );

		if ( is_wp_error( $response ) ) {
			if ( $retry ) {
				$this->fail( $post->ID, $attempt, $response, $items, $batch, $hash, $type, $when );
			} else {
				$this->record_error( $post->ID, $items, $batch, $hash, $type, $when, $response );
			}
			return;
		}

		$postiz_ids = array();
		foreach ( $response as $created ) {
			if ( is_array( $created ) && isset( $created['integration'], $created['postId'] ) ) {
				$postiz_ids[ (string) $created['integration'] ] = (string) $created['postId'];
			}
		}

		foreach ( $items as $item ) {
			$this->plugin->shares->insert(
				array(
					'post_id'        => $post->ID,
					'integration_id' => (string) $item['integration_id'],
					'identifier'     => (string) $item['identifier'],
					'batch'          => $batch,
					'postiz_post_id' => $postiz_ids[ $item['integration_id'] ] ?? '',
					'type'           => $type,
					'status'         => PayloadBuilder::TYPE_DRAFT === $type ? 'draft' : 'queued',
					'publish_at'     => gmdate( 'Y-m-d H:i:s', $when ),
					'content_hash'   => $hash,
				)
			);
		}

		$log->info(
			'share',
			sprintf(
				/* translators: 1: number of channels, 2: Postiz post type (now, schedule, draft). */
				_n( 'Sent to Postiz: %1$d channel, type %2$s.', 'Sent to Postiz: %1$d channels, type %2$s.', count( $items ), 'social-relay-for-postiz' ),
				count( $items ),
				$type
			),
			$post->ID,
			isset( $items[1] ) ? '' : (string) $items[0]['integration_id'], // One channel per request in slot mode: name it.
			array(
				'batch'   => $batch,
				'attempt' => $attempt,
			)
		);

		/**
		 * Fires after a batch was accepted by Postiz.
		 *
		 * @param int                   $post_id    Post id.
		 * @param string                $batch      Group id sent to Postiz.
		 * @param array<string, string> $postiz_ids Postiz post id per channel id.
		 */
		do_action( 'socialrelaycvi_shared', $post->ID, $batch, $postiz_ids );
	}

	/**
	 * Re-queues a retryable failure, or records it as final.
	 *
	 * @param int                       $post_id Post id.
	 * @param int                       $attempt Attempt number.
	 * @param WP_Error                  $error   Error.
	 * @param list<array<string,mixed>> $items   Channel items, when known.
	 * @param string                    $batch   Group id.
	 * @param string                    $hash    Content hash.
	 * @param string                    $type    Post type.
	 * @param int                       $when    Publish time.
	 */
	private function fail( int $post_id, int $attempt, WP_Error $error, array $items = array(), string $batch = '', string $hash = '', string $type = '', int $when = 0 ): void {
		$data      = (array) $error->get_error_data();
		$retryable = ! empty( $data['retryable'] );

		if ( $retryable && $attempt < self::MAX_ATTEMPTS ) {
			$delay = max(
				(int) ( $data['retry_after'] ?? 0 ),
				array(
					1 => 5,
					2 => 15,
				)[ $attempt ] * MINUTE_IN_SECONDS
			);
			$this->plugin->queue->enqueue_share( $post_id, $delay, $attempt + 1 );

			$this->plugin->logger->error(
				'share',
				/* translators: 1: error message, 2: attempt number, 3: max attempts. */
				sprintf( __( '%1$s Retrying (%2$d of %3$d).', 'social-relay-for-postiz' ), $error->get_error_message(), $attempt + 1, self::MAX_ATTEMPTS ),
				$post_id
			);
			return;
		}

		if ( array() !== $items ) {
			$this->record_error( $post_id, $items, $batch, $hash, $type, $when, $error );
			return;
		}

		$this->plugin->logger->error( 'share', $error->get_error_message(), $post_id );
	}

	/**
	 * Stores a final error for each channel of the batch.
	 *
	 * @param int                       $post_id Post id.
	 * @param list<array<string,mixed>> $items   Channel items.
	 * @param string                    $batch   Group id.
	 * @param string                    $hash    Content hash.
	 * @param string                    $type    Post type.
	 * @param int                       $when    Publish time.
	 * @param WP_Error                  $error   Error.
	 */
	private function record_error( int $post_id, array $items, string $batch, string $hash, string $type, int $when, WP_Error $error ): void {
		foreach ( $items as $item ) {
			$this->plugin->shares->insert(
				array(
					'post_id'        => $post_id,
					'integration_id' => (string) $item['integration_id'],
					'identifier'     => (string) $item['identifier'],
					'batch'          => $batch,
					'postiz_post_id' => '',
					'type'           => $type,
					'status'         => 'error',
					'publish_at'     => gmdate( 'Y-m-d H:i:s', $when ),
					'content_hash'   => $hash,
					'error'          => $error->get_error_message(),
				)
			);

			$this->plugin->logger->error( 'share', $error->get_error_message(), $post_id, (string) $item['integration_id'] );
		}
	}

	/**
	 * Context for one channel: adds UTM parameters to {url} when the profile asks for it.
	 *
	 * @param array<string, string|callable(string): string> $context    Post context.
	 * @param array<string, mixed>                           $profile    Profile.
	 * @param string                                         $identifier Provider identifier.
	 * @return array<string, string|callable(string): string>
	 */
	private function channel_context( array $context, array $profile, string $identifier ): array {
		if ( empty( $profile['utm'] ) || ! is_string( $context['url'] ?? null ) ) {
			return $context;
		}

		$context['url'] = Utm::add(
			$context['url'],
			array(
				'utm_source'   => ProviderRules::utm_source( $identifier ),
				'utm_medium'   => 'social',
				'utm_campaign' => $this->engine->render( (string) $profile['utm_campaign'], $context ),
			)
		);

		return $context;
	}

	/**
	 * Whether the post passes the include/exclude term rules.
	 *
	 * @param WP_Post   $post    Post.
	 * @param list<int> $required  Required term ids (any).
	 * @param list<int> $forbidden Forbidden term ids.
	 */
	private function passes_conditions( WP_Post $post, array $required, array $forbidden ): bool {
		if ( array() === $required && array() === $forbidden ) {
			return true;
		}

		$terms = wp_get_post_terms( $post->ID, array( 'category', 'post_tag' ), array( 'fields' => 'ids' ) );
		$terms = is_array( $terms ) ? array_values( array_map( 'intval', $terms ) ) : array();

		return Conditions::passes( $terms, $required, $forbidden );
	}

	/**
	 * Category names of the post, used as Postiz tags.
	 *
	 * @param WP_Post $post Post.
	 * @return list<string>
	 */
	private function category_names( WP_Post $post ): array {
		$names = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );

		return is_array( $names ) ? array_values( array_map( 'strval', $names ) ) : array();
	}

	/**
	 * First non-blank string.
	 *
	 * @param string ...$candidates Candidates in priority order.
	 */
	private static function first_filled( string ...$candidates ): string {
		foreach ( $candidates as $candidate ) {
			if ( '' !== trim( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Splits and trims a message for a network.
	 *
	 * Thread-capable networks get one item per part; the rest get the parts joined.
	 * Each part is cut to the network's character limit.
	 *
	 * @param string $message    Rendered message.
	 * @param string $identifier Provider identifier.
	 * @return list<string>
	 */
	private function parts_for( string $message, string $identifier ): array {
		$parts = $this->engine->split_thread( $message );

		if ( ! in_array( $identifier, self::THREAD_NETWORKS, true ) ) {
			$parts = array( implode( "\n\n", $parts ) );
		}

		$limit = ProviderRules::char_limit( $identifier );
		if ( null !== $limit ) {
			$parts = array_map( static fn ( string $part ): string => TemplateEngine::limit( $part, $limit ), $parts );
		}

		return $parts;
	}

	/**
	 * Uploads the featured image to Postiz.
	 *
	 * @param Gateway $client Gateway to Postiz.
	 * @param WP_Post $post   Post.
	 * @return list<array{id: string, path: string}>
	 */
	private function featured_image( Gateway $client, WP_Post $post ): array {
		$thumbnail_id = get_post_thumbnail_id( $post );
		if ( ! $thumbnail_id ) {
			return array();
		}

		$url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
		if ( ! $url ) {
			return array();
		}

		$upload = $client->upload_from_url( $url, true );
		if ( is_wp_error( $upload ) || empty( $upload['id'] ) || empty( $upload['path'] ) ) {
			$message = is_wp_error( $upload ) ? $upload->get_error_message() : __( 'Unexpected upload response.', 'social-relay-for-postiz' );
			$this->plugin->logger->error(
				'upload',
				/* translators: %s: error message. */
				sprintf( __( 'Postiz could not fetch the featured image (%s). Sent without image.', 'social-relay-for-postiz' ), $message ),
				$post->ID,
				'',
				array( 'url' => $url )
			);
			return array();
		}

		return array(
			array(
				'id'   => (string) $upload['id'],
				'path' => (string) $upload['path'],
			),
		);
	}

	/**
	 * Next free slot for a channel, or now + 5 minutes if Postiz cannot tell.
	 *
	 * @param Gateway $client         Gateway to Postiz.
	 * @param string  $integration_id Channel id.
	 */
	private function slot_time( Gateway $client, string $integration_id ): int {
		$slot = $client->find_slot( $integration_id );
		$time = ! is_wp_error( $slot ) && isset( $slot['date'] ) ? strtotime( (string) $slot['date'] ) : false;

		return false === $time ? time() + 5 * MINUTE_IN_SECONDS : $time;
	}
}
