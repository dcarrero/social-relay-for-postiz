<?php
/**
 * Translated names of share statuses.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

/**
 * Labels shared by the metabox, the dashboard widget and the log.
 */
final class StatusLabels {

	/**
	 * Label for a status.
	 *
	 * @param string $status queued, published, error, draft, cancelled.
	 */
	public static function label( string $status ): string {
		return match ( $status ) {
			'queued'    => __( 'Queued', 'social-relay-for-postiz' ),
			'published' => __( 'Published', 'social-relay-for-postiz' ),
			'error'     => __( 'Error', 'social-relay-for-postiz' ),
			'draft'     => __( 'Draft in Postiz', 'social-relay-for-postiz' ),
			'cancelled' => __( 'Cancelled', 'social-relay-for-postiz' ),
			default     => $status,
		};
	}
}
