/**
 * Social Relay for Postiz: settings screen.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner, TabPanel } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import ConnectionTab from './components/ConnectionTab';
import ProfilesTab from './components/ProfilesTab';
import WebhookTab from './components/WebhookTab';
import { config } from './config';
import './style.css';

function SettingsApp() {
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( '' );

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
			title: __( 'Publishing profiles', 'social-relay-for-postiz' ),
		},
		{ name: 'webhook', title: __( 'Webhook', 'social-relay-for-postiz' ) },
	];

	return (
		<div className="socialrelaycvi-settings">
			<h1>
				{ __( 'Social Relay for Postiz', 'social-relay-for-postiz' ) }
			</h1>
			<TabPanel
				tabs={ tabs }
				initialTabName={
					settings.connection.configured ? 'profiles' : 'connection'
				}
			>
				{ ( tab ) => {
					const props = { settings, setSettings };
					if ( tab.name === 'connection' ) {
						return <ConnectionTab { ...props } />;
					}
					if ( tab.name === 'webhook' ) {
						return <WebhookTab { ...props } />;
					}
					return <ProfilesTab { ...props } />;
				} }
			</TabPanel>
		</div>
	);
}

const root = document.getElementById( 'socialrelaycvi-settings' );
if ( root ) {
	createRoot( root ).render( <SettingsApp /> );
}
