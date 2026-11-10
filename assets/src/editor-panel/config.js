/**
 * Shared configuration for the editor panel.
 *
 * Data comes from window.socialRelayCvi, printed by src/Admin/EditorPanel.php.
 */
import { __, sprintf } from '@wordpress/i18n';

export const config = window.socialRelayCvi || null;

export const ICON = 'share';

export const META = {
	enabled: '_socialrelaycvi_enabled',
	message: '_socialrelaycvi_message',
	overrides: '_socialrelaycvi_overrides',
	channels: '_socialrelaycvi_channels',
	reshare: '_socialrelaycvi_reshare',
};

export const channelsById = Object.fromEntries(
	( config?.channels || [] ).map( ( channel ) => [ channel.id, channel ] )
);

/**
 * "Name · network", so channels with the same name on different networks can be told apart.
 *
 * @param {Object} channel Channel.
 * @return {string} Label.
 */
export function channelLabel( channel ) {
	return channel ? `${ channel.name } · ${ channel.identifier }` : '';
}

export const STATUS_COLORS = {
	queued: '#3858e9',
	published: '#007a1f',
	error: '#cc1818',
	draft: '#646970',
	cancelled: '#646970',
};

/**
 * Human label for the profile timing.
 *
 * @param {string} mode now, schedule, slot or draft.
 * @return {string} Label.
 */
export function modeLabel( mode ) {
	switch ( mode ) {
		case 'schedule':
			return sprintf(
				/* translators: %d: minutes of delay. */
				__(
					'Scheduled %d minutes after publishing',
					'social-relay-for-postiz'
				),
				config.profile.delayMinutes
			);
		case 'slot':
			return __( 'Next free slot in Postiz', 'social-relay-for-postiz' );
		case 'draft':
			return __( 'Draft in Postiz', 'social-relay-for-postiz' );
		default:
			return __( 'Immediately', 'social-relay-for-postiz' );
	}
}

/**
 * Human label for a share status.
 *
 * @param {string} status Share status.
 * @return {string} Label.
 */
export function statusLabel( status ) {
	const labels = {
		queued: __( 'Queued', 'social-relay-for-postiz' ),
		published: __( 'Published', 'social-relay-for-postiz' ),
		error: __( 'Error', 'social-relay-for-postiz' ),
		draft: __( 'Draft in Postiz', 'social-relay-for-postiz' ),
		cancelled: __( 'Cancelled', 'social-relay-for-postiz' ),
		pending: __( 'Pending', 'social-relay-for-postiz' ),
	};
	return labels[ status ] || status;
}
