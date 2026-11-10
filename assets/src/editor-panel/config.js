/**
 * Shared configuration for the editor panel.
 *
 * Data comes from window.socialRelayCvi, printed by src/Admin/EditorPanel.php.
 */
import { dateI18n, getSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

export const config = window.socialRelayCvi || null;

export const ICON = 'share';

export const META = {
	enabled: '_socialrelaycvi_enabled',
	message: '_socialrelaycvi_message',
	overrides: '_socialrelaycvi_overrides',
	channels: '_socialrelaycvi_channels',
	reshare: '_socialrelaycvi_reshare',
	mode: '_socialrelaycvi_mode',
	publishAt: '_socialrelaycvi_publish_at',
};

/**
 * Timing for this post: its own mode and date, or the profile's.
 *
 * @param {Object} meta Post meta.
 * @return {{mode: string, publishAt: string, own: boolean}} Timing.
 */
export function postTiming( meta ) {
	const own = meta[ META.mode ] || '';
	return {
		mode: own || config.profile.mode,
		publishAt: meta[ META.publishAt ] || '',
		own: !! own,
	};
}

/**
 * Formats a site-time date ("Y-m-d\TH:i:s", no offset) in the site format.
 *
 * Without an offset dateI18n would read it in the browser's time zone.
 *
 * @param {string} value Site-time date.
 * @return {string} Date and time.
 */
export function siteDateLabel( value ) {
	const { formats, timezone } = getSettings();
	const offset = Number( timezone.offset ) || 0;
	const minutes = Math.round( Math.abs( offset ) * 60 );
	const suffix =
		( offset < 0 ? '-' : '+' ) +
		String( Math.floor( minutes / 60 ) ).padStart( 2, '0' ) +
		':' +
		String( minutes % 60 ).padStart( 2, '0' );
	return dateI18n( `${ formats.date } ${ formats.time }`, value + suffix );
}

/**
 * Human label for the timing of a post, with the date when it has one.
 *
 * @param {Object} meta Post meta.
 * @return {string} Label.
 */
export function timingLabel( meta ) {
	const { mode, publishAt } = postTiming( meta );
	if ( mode === 'schedule' && publishAt ) {
		return sprintf(
			/* translators: %s: date and time. */
			__( 'Scheduled for %s', 'social-relay-for-postiz' ),
			siteDateLabel( publishAt )
		);
	}
	// Schedule without a date and no profile delay goes out right away.
	return modeLabel(
		mode === 'schedule' && ! config.profile.delayMinutes ? 'now' : mode
	);
}

// Stored alone in the channels meta when every channel is unticked (empty = use the profile's).
export const NO_CHANNELS = '__none';

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
