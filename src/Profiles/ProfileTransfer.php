<?php
/**
 * Export and import of publishing profiles as JSON.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Profiles;

use WP_Term;

/**
 * Moves profiles between sites.
 *
 * Term ids differ from site to site, so conditions travel as taxonomy + slug + name
 * and are matched again on import. Channel ids belong to the Postiz instance: they are
 * kept as they are, and channels the instance does not have show up in the profile
 * editor with "Remove them".
 *
 * pack() and unpack() are pure (terms come through callables) so they can be unit-tested.
 *
 * @phpstan-import-type Profile from ProfileRepository
 * @phpstan-type TermRef array{taxonomy: string, slug: string, name: string}
 */
final class ProfileTransfer {

	public const FORMAT = 'social-relay-for-postiz/profiles';

	public const FORMAT_VERSION = 1;

	/**
	 * Profiles as an exportable array.
	 *
	 * @param array<string, array<string, mixed>>         $profiles Profiles by post type.
	 * @param callable(int): (array<string, string>|null) $term_ref Term id => TermRef, null when the term is gone.
	 * @param string                                      $site     Site URL, for reference only.
	 * @param string                                      $version  Plugin version.
	 * @return array<string, mixed>
	 */
	public static function pack( array $profiles, callable $term_ref, string $site, string $version ): array {
		$out = array();

		foreach ( $profiles as $post_type => $profile ) {
			foreach ( array( 'include', 'exclude' ) as $kind ) {
				$refs = array();
				foreach ( (array) ( $profile[ $kind . '_terms' ] ?? array() ) as $term_id ) {
					$ref = $term_ref( (int) $term_id );
					if ( null !== $ref ) {
						$refs[] = $ref;
					}
				}
				unset( $profile[ $kind . '_terms' ] );
				$profile[ $kind ] = $refs;
			}

			$out[ (string) $post_type ] = $profile;
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::FORMAT_VERSION,
			'plugin'   => $version,
			'site'     => $site,
			'exported' => gmdate( 'c' ),
			'profiles' => $out,
		);
	}

	/**
	 * Reads an export and turns it back into raw profiles for ProfileRepository::sanitize().
	 *
	 * @param mixed                                  $data       Decoded JSON.
	 * @param list<string>                           $post_types Post types this site has.
	 * @param callable(string, string, string): ?int $term_id    (taxonomy, slug, name) => term id, null if missing.
	 * @return array{profiles: array<string, array<string, mixed>>, skipped_types: list<string>, missing_terms: list<string>}|null Null when it is not an export of this plugin.
	 */
	public static function unpack( mixed $data, array $post_types, callable $term_id ): ?array {
		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || ! isset( $data['profiles'] ) || ! is_array( $data['profiles'] ) ) {
			return null;
		}

		$profiles = array();
		$skipped  = array();
		$missing  = array();

		foreach ( $data['profiles'] as $post_type => $profile ) {
			$post_type = (string) $post_type;
			if ( ! is_array( $profile ) ) {
				continue;
			}
			if ( ! in_array( $post_type, $post_types, true ) ) {
				$skipped[] = $post_type;
				continue;
			}

			foreach ( array( 'include', 'exclude' ) as $kind ) {
				$ids = array();
				foreach ( (array) ( $profile[ $kind ] ?? array() ) as $ref ) {
					if ( ! is_array( $ref ) ) {
						continue;
					}
					$taxonomy = (string) ( $ref['taxonomy'] ?? '' );
					$slug     = (string) ( $ref['slug'] ?? '' );
					$name     = (string) ( $ref['name'] ?? '' );
					$id       = '' === $taxonomy ? null : $term_id( $taxonomy, $slug, $name );

					if ( null === $id ) {
						$missing[] = '' !== $name ? $name : $slug;
						continue;
					}
					$ids[] = $id;
				}
				unset( $profile[ $kind ] );
				$profile[ $kind . '_terms' ] = $ids;
			}

			$profiles[ $post_type ] = $profile;
		}

		return array(
			'profiles'      => $profiles,
			'skipped_types' => $skipped,
			'missing_terms' => array_values( array_unique( array_filter( $missing ) ) ),
		);
	}

	/**
	 * TermRef of a term on this site.
	 *
	 * @param int $term_id Term id.
	 * @phpstan-return TermRef|null
	 */
	public static function term_ref( int $term_id ): ?array {
		$term = get_term( $term_id );

		return $term instanceof WP_Term
			? array(
				'taxonomy' => $term->taxonomy,
				'slug'     => $term->slug,
				'name'     => $term->name,
			)
			: null;
	}

	/**
	 * Term id on this site by slug, then by name. Terms are never created.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Slug.
	 * @param string $name     Name.
	 */
	public static function find_term( string $taxonomy, string $slug, string $name ): ?int {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$term = '' !== $slug ? get_term_by( 'slug', $slug, $taxonomy ) : false;
		if ( ! $term instanceof WP_Term && '' !== $name ) {
			$term = get_term_by( 'name', $name, $taxonomy );
		}

		return $term instanceof WP_Term ? $term->term_id : null;
	}
}
