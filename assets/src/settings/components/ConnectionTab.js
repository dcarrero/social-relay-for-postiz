import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	ExternalLink,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { config } from '../config';

/**
 * Postiz URL and API key.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Replaces the settings after saving.
 * @return {Element} Tab.
 */
export default function ConnectionTab( { settings, setSettings } ) {
	const { connection } = settings;
	const [ url, setUrl ] = useState( connection.instance_url );
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );

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

	const save = () => {
		setBusy( true );
		setResult( null );
		apiFetch( {
			path: `/${ config.namespace }/admin/connection`,
			method: 'POST',
			data: { instance_url: url, api_key: key },
		} )
			.then( ( response ) => {
				setResult( response );
				setSettings( response.settings );
				setKey( '' );
			} )
			.catch( ( err ) =>
				setResult( {
					ok: false,
					message: err.message || String( err ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<Card>
			<CardBody>
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
							'Postiz → Settings → Developers → Public API. Leave empty to keep the current key. For better security define SOCIALRELAYCVI_POSTIZ_API_KEY in wp-config.php or the POSTIZ_API_KEY environment variable.',
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
						onClick={ save }
						isBusy={ busy }
						disabled={ busy || ! url }
						__next40pxDefaultSize
					>
						{ __(
							'Save and test connection',
							'social-relay-for-postiz'
						) }
					</Button>
					<ExternalLink href={ config.docsUrl }>
						{ __(
							'Postiz public API documentation',
							'social-relay-for-postiz'
						) }
					</ExternalLink>
				</div>
			</CardBody>
		</Card>
	);
}
