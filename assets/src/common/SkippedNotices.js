import { Notice } from '@wordpress/components';
import { _n, sprintf } from '@wordpress/i18n';

/**
 * One notice per reason, not per channel: five removed channels are one warning.
 *
 * @param {Object}   props         Props.
 * @param {Object[]} props.skipped Skipped channels: integration_id, code, reason.
 * @param {Object}   props.names   Channel names by id, when known.
 * @return {Element|null} Notices.
 */
export default function SkippedNotices( { skipped, names = {} } ) {
	if ( ! skipped?.length ) {
		return null;
	}

	const groups = {};
	skipped.forEach( ( skip ) => {
		const key = skip.reason;
		groups[ key ] = groups[ key ] || [];
		groups[ key ].push( names[ skip.integration_id ] || '' );
	} );

	return Object.entries( groups ).map( ( [ reason, list ] ) => {
		const known = list.filter( Boolean );
		return (
			<Notice key={ reason } status="warning" isDismissible={ false }>
				{ list.length > 1
					? sprintf(
							/* translators: 1: reason, 2: number of channels. */
							_n(
								'%1$s (%2$d channel)',
								'%1$s (%2$d channels)',
								list.length,
								'social-relay-for-postiz'
							),
							reason,
							list.length
					  )
					: reason }
				{ known.length > 0 && (
					<>
						<br />
						<small>{ known.join( ', ' ) }</small>
					</>
				) }
			</Notice>
		);
	} );
}
