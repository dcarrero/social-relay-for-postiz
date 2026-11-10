import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TabPanel } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { config } from '../config';
import ProfileEditor from './ProfileEditor';

/**
 * One tab per post type, all saved together.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Replaces the settings after saving.
 * @return {Element} Tab.
 */
export default function ProfilesTab( { settings, setSettings } ) {
	const [ profiles, setProfiles ] = useState( settings.profiles );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ dirty, setDirty ] = useState( false );

	if ( ! settings.connection.configured ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Connect Postiz first (Connection tab).',
					'social-relay-for-postiz'
				) }
			</Notice>
		);
	}

	const save = () => {
		setBusy( true );
		apiFetch( {
			path: `/${ config.namespace }/admin/profiles`,
			method: 'POST',
			data: { profiles },
		} )
			.then( ( response ) => {
				setSettings( response );
				setProfiles( response.profiles );
				setDirty( false );
				setNotice( {
					status: 'success',
					text: __( 'Profiles saved.', 'social-relay-for-postiz' ),
				} );
			} )
			.catch( ( err ) =>
				setNotice( {
					status: 'error',
					text: err.message || String( err ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const tabs = Object.entries( settings.postTypes ).map(
		( [ name, info ] ) => ( {
			name,
			title: info.label + ( profiles[ name ]?.enabled ? ' ●' : '' ),
		} )
	);

	return (
		<>
			{ settings.channelsError && (
				<Notice status="error" isDismissible={ false }>
					{ settings.channelsError }
				</Notice>
			) }
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<TabPanel
				className="socialrelaycvi-settings__post-types"
				tabs={ tabs }
			>
				{ ( tab ) => (
					<ProfileEditor
						postType={ tab.name }
						profile={ profiles[ tab.name ] }
						settings={ settings }
						onChange={ ( profile ) => {
							setProfiles( {
								...profiles,
								[ tab.name ]: profile,
							} );
							setDirty( true );
						} }
					/>
				) }
			</TabPanel>

			<div className="socialrelaycvi-settings__save">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ busy }
					disabled={ busy || ! dirty }
					__next40pxDefaultSize
				>
					{ __( 'Save profiles', 'social-relay-for-postiz' ) }
				</Button>
				{ dirty && (
					<span className="description">
						{ __( 'Unsaved changes', 'social-relay-for-postiz' ) }
					</span>
				) }
			</div>
		</>
	);
}
