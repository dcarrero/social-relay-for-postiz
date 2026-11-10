<?php
/**
 * Suggested privacy policy text.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi\Admin;

use SocialRelayCvi\Module;

/**
 * Adds a section to Settings → Privacy → Policy Guide explaining what is sent to Postiz.
 */
final class Privacy implements Module {

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Registers the suggested text.
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p class="privacy-policy-tutorial">%1$s</p><p>%2$s</p><p>%3$s</p>',
			esc_html__( 'Social Relay for Postiz does not collect data from your visitors. It only acts when you publish content.', 'social-relay-for-postiz' ),
			esc_html__( 'When we publish a post, the message we wrote for social networks, the link to the post and its featured image address are sent to our Postiz instance, which publishes them on the social networks we connected. Postiz then makes that content public on those networks.', 'social-relay-for-postiz' ),
			esc_html__( 'If we use Postiz Cloud, that information is processed by Postiz according to its privacy policy: https://postiz.com/privacy', 'social-relay-for-postiz' )
		);

		wp_add_privacy_policy_content( 'Social Relay for Postiz', wp_kses_post( $content ) );
	}
}
