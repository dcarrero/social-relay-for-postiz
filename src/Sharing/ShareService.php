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
		if ( ! $profile['enabled'] || array() === $profile['channels'] || ! get_post_meta( $post_id, PostMeta::ENABLED, true ) ) {
			return;
		}

		$log    = $this->plugin->logger;
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

		$template = (string) get_post_meta( $post_id, PostMeta::MESSAGE, true );
		$template = '' !== trim( $template ) ? $template : $profile['template'];
		$message  = $this->engine->render( $template, PostContext::for_post( $post ) );

		$items = array();
		foreach ( $profile['channels'] as $channel_id ) {
			$channel = $channels[ $channel_id ] ?? null;

			if ( null === $channel ) {
				$log->error( 'share', __( 'Channel no longer exists in Postiz; skipped.', 'social-relay-for-postiz' ), $post_id, $channel_id );
				continue;
			}

			if ( $channel['disabled'] ) {
				$log->error( 'share', __( 'Channel is disabled in Postiz; skipped.', 'social-relay-for-postiz' ), $post_id, $channel_id );
				continue;
			}

			$items[] = array(
				'integration_id' => $channel_id,
				'identifier'     => $channel['identifier'],
				'parts'          => $this->parts_for( $message, $channel['identifier'] ),
			);
		}

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

		$batch = sprintf( 'wp-%s-%d-%d', substr( md5( home_url() ), 0, 8 ), $post_id, $this->plugin->shares->batch_count( $post_id ) + 1 );

		if ( 'slot' === $profile['mode'] ) {
			foreach ( $items as $item ) {
				$this->send( $client, $post, array( $item ), $this->slot_time( $client, $item['integration_id'] ), 'schedule', $batch, $hash, $attempt, false );
			}
		} else {
			$type = match ( $profile['mode'] ) {
				'schedule' => PayloadBuilder::TYPE_SCHEDULE,
				'draft'    => PayloadBuilder::TYPE_DRAFT,
				default    => PayloadBuilder::TYPE_NOW,
			};
			$when = 'schedule' === $profile['mode'] ? time() + $profile['delay_minutes'] * MINUTE_IN_SECONDS : time();

			$this->send( $client, $post, $items, $when, $type, $batch, $hash, $attempt, true );
		}

		if ( $reshare ) {
			update_post_meta( $post_id, PostMeta::RESHARE, false );
		}
	}

	/**
	 * Creates the posts in Postiz and records the result.
	 *
	 * @param Gateway                   $client  Gateway to Postiz.
	 * @param WP_Post                   $post    Post.
	 * @param list<array<string,mixed>> $items   Channel items.
	 * @param int                       $when    Publish time.
	 * @param string                    $type    now, schedule or draft.
	 * @param string                    $batch   Group id.
	 * @param string                    $hash    Content hash.
	 * @param int                       $attempt Attempt number.
	 * @param bool                      $retry   Whether a retryable error re-queues the whole job.
	 */
	private function send( Gateway $client, WP_Post $post, array $items, int $when, string $type, string $batch, string $hash, int $attempt, bool $retry ): void {
		$log = $this->plugin->logger;

		try {
			$payload = PayloadBuilder::build( $items, $type, $when, $batch );
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
			/* translators: 1: number of channels, 2: Postiz post type (now, schedule, draft). */
			sprintf( __( 'Sent to Postiz: %1$d channel(s), type %2$s.', 'social-relay-for-postiz' ), count( $items ), $type ),
			$post->ID,
			'',
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
