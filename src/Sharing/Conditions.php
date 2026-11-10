<?php
/**
 * Basic include/exclude conditions on terms.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Sharing;

/**
 * Pure rule check.
 *
 * - Exclude wins: a post with any excluded term is never shared.
 * - Include: when the list is not empty, the post needs at least one of those terms.
 *
 * Advanced conditions (author, custom fields, AND/OR groups) belong to the Pro add-on,
 * which can extend the decision through the `socialrelaycvi_should_share` filter.
 */
final class Conditions {

	/**
	 * Whether a post with these terms passes the rules.
	 *
	 * @param list<int> $post_terms Term ids of the post.
	 * @param list<int> $required   Required terms (any of them); empty = no requirement.
	 * @param list<int> $forbidden  Forbidden terms.
	 */
	public static function passes( array $post_terms, array $required, array $forbidden ): bool {
		if ( array() !== array_intersect( $post_terms, $forbidden ) ) {
			return false;
		}

		return array() === $required || array() !== array_intersect( $post_terms, $required );
	}
}
