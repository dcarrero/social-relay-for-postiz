<?php
/**
 * Builds the body of POST /posts.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sharing;

use SocialRelayCvi\Postiz\ProviderRules;

/**
 * Pure builder for the Postiz CreatePostRequest.
 *
 * One request carries every channel, so a WordPress post costs a single call
 * against the instance's posts-per-hour limit.
 */
final class PayloadBuilder {

	public const TYPE_NOW      = 'now';
	public const TYPE_SCHEDULE = 'schedule';
	public const TYPE_DRAFT    = 'draft';

	/**
	 * Builds the request body.
	 *
	 * @param list<array{integration_id: string, identifier: string, parts: list<string>, images?: list<array{id: string, path: string}>, settings?: array<string, mixed>}> $items One entry per channel.
	 * @param string                                                                                                                                                        $type       now, schedule or draft.
	 * @param int                                                                                                                                                           $timestamp  Publish time (Unix). Postiz requires a date even for "now".
	 * @param string                                                                                                                                                        $group      Group id shared by every post of this batch.
	 * @param bool                                                                                                                                                          $short_link Let Postiz shorten links.
	 * @param list<string>                                                                                                                                                  $tags       Postiz tag names.
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException On an unknown type or an empty batch.
	 */
	public static function build( array $items, string $type, int $timestamp, string $group, bool $short_link = false, array $tags = array() ): array {
		if ( ! in_array( $type, array( self::TYPE_NOW, self::TYPE_SCHEDULE, self::TYPE_DRAFT ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown post type: ' . $type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output.
		}

		$posts = array();

		foreach ( $items as $item ) {
			$parts = array_values( array_filter( $item['parts'], static fn ( string $p ): bool => '' !== trim( $p ) ) );
			if ( array() === $parts ) {
				continue;
			}

			$images = array_map(
				static fn ( array $image ): array => array(
					'id'   => (string) $image['id'],
					'path' => (string) $image['path'],
				),
				$item['images'] ?? array()
			);

			$value = array();
			foreach ( $parts as $index => $content ) {
				$value[] = array(
					'content' => $content,
					'image'   => 0 === $index ? $images : array(),
				);
			}

			$posts[] = array(
				'integration' => array( 'id' => $item['integration_id'] ),
				'value'       => $value,
				'group'       => $group,
				'settings'    => array_merge( ProviderRules::default_settings( $item['identifier'] ), $item['settings'] ?? array() ),
			);
		}

		if ( array() === $posts ) {
			throw new \InvalidArgumentException( 'Nothing to send: every channel has an empty message.' );
		}

		return array(
			'type'      => $type,
			'date'      => self::iso_date( $timestamp ),
			'shortLink' => $short_link,
			'tags'      => array_map(
				static fn ( string $tag ): array => array(
					'value' => $tag,
					'label' => $tag,
				),
				array_values( array_unique( $tags ) )
			),
			'posts'     => $posts,
		);
	}

	/**
	 * UTC ISO 8601 date with milliseconds, as in the Postiz docs.
	 *
	 * @param int $timestamp Unix time.
	 */
	public static function iso_date( int $timestamp ): string {
		return gmdate( 'Y-m-d\TH:i:s.000\Z', $timestamp );
	}
}
