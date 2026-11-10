import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	TextControl,
	__experimentalConfirmDialog as ConfirmDialog, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { humanTimeDiff } from '@wordpress/date';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { config } from '../config';

/**
 * Webhook URL to paste in Postiz, last call and regeneration.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Updates the settings.
 * @return {Element} Tab.
 */
export default function WebhookTab( { settings, setSettings } ) {
	const { webhook } = settings;
	const [ copied, setCopied ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ regenerated, setRegenerated ] = useState( false );

	const copyRef = useCopyToClipboard( webhook.url, () => {
		setCopied( true );
		setTimeout( () => setCopied( false ), 2000 );
	} );

	const regenerate = () => {
		setConfirming( false );
		apiFetch( {
			path: `/${ config.namespace }/admin/webhook/regenerate`,
			method: 'POST',
		} ).then( ( response ) => {
			setSettings( { ...settings, webhook: response } );
			setRegenerated( true );
		} );
	};

	return (
		<Card>
			<CardHeader>
				<h3>{ __( 'Postiz webhook', 'social-relay-for-postiz' ) }</h3>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'Paste this URL in Postiz → Settings → Webhooks to update the status of your posts as soon as they are published. Without it, the plugin checks Postiz every 15 minutes.',
						'social-relay-for-postiz'
					) }
				</p>

				<div className="socialrelaycvi-settings__copy">
					<TextControl
						label={ __( 'Webhook URL', 'social-relay-for-postiz' ) }
						value={ webhook.url }
						readOnly
						onChange={ () => {} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<Button
						variant="secondary"
						ref={ copyRef }
						__next40pxDefaultSize
					>
						{ copied
							? __( 'Copied', 'social-relay-for-postiz' )
							: __( 'Copy', 'social-relay-for-postiz' ) }
					</Button>
				</div>

				<p className="description">
					{ webhook.last
						? sprintf(
								/* translators: %s: time since the last webhook call, e.g. "5 minutes ago". */
								__(
									'Last call received %s.',
									'social-relay-for-postiz'
								),
								humanTimeDiff( webhook.last * 1000 )
						  )
						: __(
								'No call received yet. Use “Send Test” in Postiz to check it.',
								'social-relay-for-postiz'
						  ) }
				</p>

				{ regenerated && (
					<p className="description">
						{ __(
							'New webhook URL created. Update it in Postiz: the old one no longer works.',
							'social-relay-for-postiz'
						) }
					</p>
				) }

				<Button
					variant="tertiary"
					isDestructive
					onClick={ () => setConfirming( true ) }
				>
					{ __( 'Create a new URL', 'social-relay-for-postiz' ) }
				</Button>

				<ConfirmDialog
					isOpen={ confirming }
					onConfirm={ regenerate }
					onCancel={ () => setConfirming( false ) }
				>
					{ __(
						'The current URL will stop working. Continue?',
						'social-relay-for-postiz'
					) }
				</ConfirmDialog>
			</CardBody>
		</Card>
	);
}
