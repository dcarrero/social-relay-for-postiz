import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TabPanel } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Marked from '../../common/Marked';
import { config } from '../config';
import ProfileEditor from './ProfileEditor';
import TestProfile from './TestProfile';

/**
 * One tab per post type, all saved together.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Replaces the settings after saving.
 * @param {Object}   props.profiles    Profiles being edited (kept by the settings screen).
 * @param {Function} props.setProfiles Replaces the edited profiles (null: the saved ones).
 * @param {boolean}  props.dirty       Whether there are unsaved changes.
 * @param {Function} props.setDirty    Marks unsaved changes.
 * @return {Element} Tab.
 */
export default function ProfilesTab( {
	settings,
	setSettings,
	profiles,
	setProfiles,
	dirty,
	setDirty,
} ) {
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ saved, setSaved ] = useState( false );

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
				setProfiles( null );
				setDirty( false );
				setNotice( null );
				setSaved( true );
				setTimeout( () => setSaved( false ), 4000 );
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
			title: (
				<Marked
					label={ info.label }
					marked={ !! profiles[ name ]?.enabled }
					note={ __( 'sharing on', 'social-relay-for-postiz' ) }
				/>
			),
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

			<p className="description">
				{ __(
					'One profile per type of content: turn sharing on, pick the channels, adjust the message and save. Posts are then shared when you publish them.',
					'social-relay-for-postiz'
				) }
			</p>
			<TabPanel
				className="socialrelaycvi-settings__post-types"
				tabs={ tabs }
			>
				{ ( tab ) => (
					<div className="socialrelaycvi-settings__profile">
						<div className="socialrelaycvi-settings__profile-main">
							<ProfileEditor
								postType={ tab.name }
								profile={ profiles[ tab.name ] }
								settings={ settings }
								onChange={ ( profile ) => {
									setSaved( false );
									setProfiles( {
										...profiles,
										[ tab.name ]: profile,
									} );
									setDirty( true );
								} }
							/>
						</div>
						<aside className="socialrelaycvi-settings__profile-aside">
							<TestProfile
								postType={ settings.postTypes[ tab.name ] }
								dirty={ dirty }
							/>
						</aside>
					</div>
				) }
			</TabPanel>

			<div
				className={ `socialrelaycvi-settings__save${
					dirty ? ' is-dirty' : ''
				}` }
			>
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
				{ saved && ! dirty && (
					<span className="socialrelaycvi-settings__saved">
						{ __( 'Profiles saved.', 'social-relay-for-postiz' ) }
					</span>
				) }
			</div>
		</>
	);
}
