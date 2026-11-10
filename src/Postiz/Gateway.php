<?php
/**
 * What the plugin needs from Postiz, whatever the transport.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Postiz;

use WP_Error;

/**
 * Postiz operations used by the plugin.
 *
 * Implementations:
 *  - Client: talks directly to a Postiz instance with its API key.
 *  - Hub gateway (planned): talks to a Social Relay Agency Hub, which holds the
 *    only Postiz key, limits the channels each site may use and forwards the call.
 *
 * Every method returns the decoded response or a WP_Error with `status`,
 * `retryable` and `retry_after` in its data.
 */
interface Gateway {

	/**
	 * Checks the credentials.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public function is_connected(): array|WP_Error;

	/**
	 * Channels this site may use.
	 *
	 * @param string $group Optional customer/group id.
	 * @return array<mixed>|WP_Error
	 */
	public function integrations( string $group = '' ): array|WP_Error;

	/**
	 * Next free slot for a channel.
	 *
	 * @param string $integration_id Channel id.
	 * @return array<mixed>|WP_Error
	 */
	public function find_slot( string $integration_id ): array|WP_Error;

	/**
	 * Creates posts (POST /posts body).
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<mixed>|WP_Error
	 */
	public function create_posts( array $payload ): array|WP_Error;

	/**
	 * Deletes every post of a group.
	 *
	 * @param string $group Group id.
	 * @return array<mixed>|WP_Error
	 */
	public function delete_group( string $group ): array|WP_Error;

	/**
	 * Imports a file from a public URL.
	 *
	 * @param string $url    File URL.
	 * @param bool   $adjust Resize for the networks.
	 * @return array<mixed>|WP_Error
	 */
	public function upload_from_url( string $url, bool $adjust = false ): array|WP_Error;

	/**
	 * Posts between two UTC dates.
	 *
	 * @param string $start_iso Start date.
	 * @param string $end_iso   End date.
	 * @return array<mixed>|WP_Error
	 */
	public function posts( string $start_iso, string $end_iso ): array|WP_Error;
}
