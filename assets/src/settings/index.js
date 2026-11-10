/**
 * Social Relay for Postiz: settings screen.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner, TabPanel } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import AdvancedTab from './components/AdvancedTab';
import ConnectionTab from './components/ConnectionTab';
import ProfilesTab from './components/ProfilesTab';
import WebhookTab from './components/WebhookTab';
import { config } from './config';
import './style.css';

function SettingsApp() {
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( '' );
	// Profile edits live here so switching tabs does not lose them.
	const [ draft, setDraft ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	// The tab panel is remounted to switch tabs from inside a tab (e.g. "Next: …").
	const [ tab, setTab ] = useState( { name: '', key: 0 } );

	// Ask before leaving the page with unsaved profiles.
	useEffect( () => {
		if ( ! dirty ) {
			return;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	useEffect( () => {
		apiFetch( { path: `/${ config.namespace }/admin/settings` } )
			.then( setSettings )
			.catch( ( err ) => setError( err.message || String( err ) ) );
	}, [] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! settings ) {
		return <Spinner />;
	}

	const tabs = [
		{
			name: 'connection',
			title: __( 'Connection', 'social-relay-for-postiz' ),
		},
		{
			name: 'profiles',
			title:
				__( 'Publishing profiles', 'social-relay-for-postiz' ) +
				( dirty ? ' *' : '' ),
		},
		{ name: 'webhook', title: __( 'Webhook', 'social-relay-for-postiz' ) },
		{
			name: 'advanced',
			title: __( 'Advanced', 'social-relay-for-postiz' ),
		},
	];

	return (
		<div className="socialrelaycvi-settings">
			<h1>
				{ __( 'Social Relay for Postiz', 'social-relay-for-postiz' ) }
			</h1>
			<TabPanel
				key={ tab.key }
				tabs={ tabs }
				initialTabName={
					tab.name ||
					( settings.connection.configured
						? 'profiles'
						: 'connection' )
				}
			>
				{ ( current ) => {
					const props = {
						settings,
						setSettings,
						goTo: ( name ) =>
							setTab( ( old ) => ( { name, key: old.key + 1 } ) ),
					};
					if ( current.name === 'connection' ) {
						return <ConnectionTab { ...props } />;
					}
					if ( current.name === 'webhook' ) {
						return <WebhookTab { ...props } />;
					}
					if ( current.name === 'advanced' ) {
						return <AdvancedTab { ...props } />;
					}
					return (
						<ProfilesTab
							{ ...props }
							profiles={ draft || settings.profiles }
							setProfiles={ setDraft }
							dirty={ dirty }
							setDirty={ setDirty }
						/>
					);
				} }
			</TabPanel>
		</div>
	);
}

const root = document.getElementById( 'socialrelaycvi-settings' );
if ( root ) {
	createRoot( root ).render( <SettingsApp /> );
}
