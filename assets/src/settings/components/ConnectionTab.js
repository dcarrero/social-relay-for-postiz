import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Notice,
	TextControl,
	__experimentalConfirmDialog as ConfirmDialog, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { config } from '../config';
import { webhookStatus } from './WebhookTab';

/**
 * Postiz URL and API key.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Replaces the settings after saving.
 * @param {Function} props.goTo        Opens another tab of the settings.
 * @return {Element} Tab.
 */
export default function ConnectionTab( { settings, setSettings, goTo } ) {
	const { connection } = settings;
	const [ url, setUrl ] = useState( connection.instance_url );
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ hostWarning, setHostWarning ] = useState( '' );

	const keyHelp = {
		env: __(
			'Read from the POSTIZ_API_KEY environment variable.',
			'social-relay-for-postiz'
		),
		constant: __(
			'Defined in wp-config.php (SOCIALRELAYCVI_POSTIZ_API_KEY).',
			'social-relay-for-postiz'
		),
	}[ connection.key_source ];

	const save = ( confirmHostChange = false ) => {
		setBusy( true );
		setResult( null );
		setHostWarning( '' );
		apiFetch( {
			path: `/${ config.namespace }/admin/connection`,
			method: 'POST',
			data: {
				instance_url: url,
				api_key: key,
				confirm_host_change: confirmHostChange,
			},
		} )
			.then( ( response ) => {
				setResult( response );
				setSettings( response.settings );
				setKey( '' );
			} )
			.catch( ( err ) => {
				// A key from the server configuration needs consent before going to a new host.
				if ( err.code === 'socialrelaycvi_confirm_host_change' ) {
					setHostWarning( err.message );
					return;
				}
				setResult( {
					ok: false,
					message: err.message || String( err ),
				} );
			} )
			.finally( () => setBusy( false ) );
	};

	const active = settings.channels.filter(
		( channel ) => ! channel.disabled
	);
	let status = {
		kind: 'off',
		text: __( 'Not connected', 'social-relay-for-postiz' ),
	};
	if ( connection.configured && settings.channelsError ) {
		status = {
			kind: 'error',
			text: __( 'Cannot reach Postiz', 'social-relay-for-postiz' ),
		};
	} else if ( connection.configured ) {
		status = {
			kind: 'ok',
			text: sprintf(
				/* translators: %d: number of channels. */
				_n(
					'Connected · %d channel',
					'Connected · %d channels',
					active.length,
					'social-relay-for-postiz'
				),
				active.length
			),
		};
	}

	return (
		<Card className="socialrelaycvi-connection">
			<CardHeader>
				<h3>{ __( 'Postiz instance', 'social-relay-for-postiz' ) }</h3>
				<span
					className={ `socialrelaycvi-connection__status is-${ status.kind }` }
				>
					{ status.text }
				</span>
			</CardHeader>
			<CardBody>
				{ connection.configured && (
					<p className="description">
						{ webhookStatus( settings.webhook.last ).kind === 'ok'
							? __(
									'Statuses update at once through the Postiz webhook.',
									'social-relay-for-postiz'
							  )
							: __(
									'Statuses update every 15 minutes. Set up the webhook (Webhook tab) to get them at once.',
									'social-relay-for-postiz'
							  ) }
					</p>
				) }
				{ connection.configured && settings.channelsError && (
					<Notice status="error" isDismissible={ false }>
						{ settings.channelsError }
					</Notice>
				) }
				{ result && (
					<Notice
						status={ result.ok ? 'success' : 'error' }
						onRemove={ () => setResult( null ) }
					>
						{ result.ok
							? sprintf(
									/* translators: %d: number of channels. */
									_n(
										'Connected to Postiz. %d channel available.',
										'Connected to Postiz. %d channels available.',
										result.channels,
										'social-relay-for-postiz'
									),
									result.channels
							  )
							: result.message }
					</Notice>
				) }

				<TextControl
					label={ __( 'Postiz URL', 'social-relay-for-postiz' ) }
					type="url"
					value={ url }
					placeholder="https://postiz.example.com"
					onChange={ setUrl }
					help={
						connection.api_base
							? sprintf(
									/* translators: %s: detected API URL. */
									__(
										'Detected API: %s',
										'social-relay-for-postiz'
									),
									connection.api_base
							  )
							: __(
									'Your self-hosted Postiz address. For Postiz Cloud, use https://api.postiz.com.',
									'social-relay-for-postiz'
							  )
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>

				<div style={ { height: 16 } } />

				{ connection.key_editable ? (
					<TextControl
						label={ __( 'API key', 'social-relay-for-postiz' ) }
						type="password"
						autoComplete="off"
						value={ key }
						placeholder={
							connection.masked_key ||
							__(
								'Paste your API key',
								'social-relay-for-postiz'
							)
						}
						onChange={ setKey }
						help={ __(
							'In Postiz: Settings → Developers → Public API. Leave it empty to keep the current key.',
							'social-relay-for-postiz'
						) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				) : (
					<>
						<p>
							<strong>
								{ __( 'API key', 'social-relay-for-postiz' ) }
							</strong>
							<br />
							<code>{ connection.masked_key }</code>
						</p>
						<p className="description">{ keyHelp }</p>
					</>
				) }

				<div className="socialrelaycvi-settings__buttons">
					<Button
						variant="primary"
						onClick={ () => save() }
						isBusy={ busy }
						disabled={ busy || ! url }
						__next40pxDefaultSize
					>
						{ __(
							'Save and test connection',
							'social-relay-for-postiz'
						) }
					</Button>
					{ status.kind === 'ok' &&
						! Object.values( settings.profiles ).some(
							( profile ) => profile.enabled
						) && (
							<Button
								variant="secondary"
								onClick={ () => goTo( 'profiles' ) }
								__next40pxDefaultSize
							>
								{ __(
									'Next: set up a publishing profile',
									'social-relay-for-postiz'
								) }
							</Button>
						) }
					<ExternalLink href={ config.docsUrl }>
						{ __(
							'Postiz public API documentation',
							'social-relay-for-postiz'
						) }
					</ExternalLink>
				</div>
				<ConfirmDialog
					isOpen={ !! hostWarning }
					onConfirm={ () => save( true ) }
					onCancel={ () => setHostWarning( '' ) }
					confirmButtonText={ __(
						'Use this address',
						'social-relay-for-postiz'
					) }
				>
					{ hostWarning }
				</ConfirmDialog>
				{ connection.key_editable && (
					<p className="description">
						{ __(
							'Developers can keep the key out of the database with SOCIALRELAYCVI_POSTIZ_API_KEY in wp-config.php or the POSTIZ_API_KEY environment variable.',
							'social-relay-for-postiz'
						) }
					</p>
				) }
			</CardBody>
		</Card>
	);
}
