/**
 * Social Relay for Postiz: settings screen.
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner, TabPanel } from '@wordpress/components';
import {
	createRoot,
	useEffect,
	useReducer,
	useState,
} from '@wordpress/element';
import { addAction, applyFilters, removeAction } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import AdvancedTab from './components/AdvancedTab';
import ConnectionTab from './components/ConnectionTab';
import ProfilesTab from './components/ProfilesTab';
import WebhookTab from './components/WebhookTab';
import { config } from './config';
import './style.css';

// Add-ons (such as the Pro add-on) add tabs with this filter: a list of
// { name, title, render( props ) }, shown before "Advanced". `props` holds settings,
// setSettings and goTo, like the built-in tabs.
const TABS_FILTER = 'socialrelaycvi.settingsTabs';

/**
 * Tabs added by add-ons, read again when one adds its filter after the app started.
 *
 * @return {Array<Object>} Extra tabs.
 */
function useExtraTabs() {
	const [ version, bump ] = useReducer( ( value ) => value + 1, 0 );

	useEffect( () => {
		addAction( 'hookAdded', 'socialrelaycvi/settings', ( name ) => {
			if ( name === TABS_FILTER ) {
				bump();
			}
		} );
		return () => removeAction( 'hookAdded', 'socialrelaycvi/settings' );
	}, [] );

	const tabs = applyFilters( TABS_FILTER, [], version );
	return Array.isArray( tabs )
		? tabs.filter(
				( tab ) =>
					tab &&
					typeof tab.name === 'string' &&
					typeof tab.render === 'function'
		  )
		: [];
}

function SettingsApp() {
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( '' );
	// Profile edits live here so switching tabs does not lose them.
	const [ draft, setDraft ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	// The tab panel is remounted to switch tabs from inside a tab (e.g. "Next: …").
	const [ tab, setTab ] = useState( { name: '', key: 0 } );
	const extraTabs = useExtraTabs();

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
		...extraTabs.map( ( extra ) => ( {
			name: extra.name,
			title: extra.title,
		} ) ),
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
					const extra = extraTabs.find(
						( item ) => item.name === current.name
					);
					if ( extra ) {
						return extra.render( props );
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
