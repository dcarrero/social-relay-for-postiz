import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, Notice, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { config } from '../config';

/**
 * Options that are not part of a publishing profile.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Replaces the settings after saving.
 * @return {Element} Tab.
 */
export default function AdvancedTab( { settings, setSettings } ) {
	const { advanced } = settings;
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const save = ( data ) => {
		setBusy( true );
		apiFetch( {
			path: `/${ config.namespace }/admin/advanced`,
			method: 'POST',
			data,
		} )
			.then( setSettings )
			.catch( ( err ) => setError( err.message || String( err ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<Card>
			<CardBody>
				{ error && (
					<Notice status="error" onRemove={ () => setError( '' ) }>
						{ error }
					</Notice>
				) }
				<ToggleControl
					label={ __(
						'Email me when a share fails',
						'social-relay-for-postiz'
					) }
					help={ sprintf(
						/* translators: %s: email address. */
						__(
							'A summary to %s, at most once an hour, only for failures that were not retried with success. Admins also see a notice in the dashboard.',
							'social-relay-for-postiz'
						),
						advanced.admin_email
					) }
					checked={ !! advanced.error_email }
					disabled={ busy }
					onChange={ ( value ) => save( { error_email: value } ) }
					__nextHasNoMarginBottom
				/>
				<div style={ { height: 16 } } />
				<ToggleControl
					label={ __(
						'Delete all plugin data when the plugin is deleted',
						'social-relay-for-postiz'
					) }
					help={ __(
						'Removes the settings, the log, the record of what was sent and the per-post options. Nothing is deleted in Postiz.',
						'social-relay-for-postiz'
					) }
					checked={
						advanced.delete_on_uninstall || advanced.delete_constant
					}
					disabled={ busy || advanced.delete_constant }
					onChange={ ( value ) =>
						save( { delete_on_uninstall: value } )
					}
					__nextHasNoMarginBottom
				/>
				{ advanced.delete_constant && (
					<p className="description">
						{ __(
							'Forced by SOCIALRELAYCVI_DELETE_DATA in wp-config.php.',
							'social-relay-for-postiz'
						) }
					</p>
				) }
			</CardBody>
		</Card>
	);
}
