/**
 * Social Relay for Postiz: block editor panel.
 *
 * Built with @wordpress/scripts into build/editor-panel.js. The @wordpress/*
 * packages are not bundled: they come from WordPress (see build/editor-panel.asset.php).
 */
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import PrePublish from './components/PrePublish';
import Sidebar from './components/Sidebar';
import { config, ICON } from './config';
import './style.css';

const SIDEBAR = 'socialrelaycvi-sidebar';

if ( config ) {
	registerPlugin( 'socialrelaycvi', {
		icon: ICON,
		render: () => (
			<>
				<PluginSidebarMoreMenuItem target={ SIDEBAR } icon={ ICON }>
					{ __( 'Social Relay', 'social-relay-for-postiz' ) }
				</PluginSidebarMoreMenuItem>
				<PluginSidebar
					name={ SIDEBAR }
					title={ __( 'Social networks', 'social-relay-for-postiz' ) }
					icon={ ICON }
				>
					<Sidebar />
				</PluginSidebar>
				<PrePublish />
			</>
		),
	} );
}
