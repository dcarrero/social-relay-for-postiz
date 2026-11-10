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
import { useCopyToClipboard } from '@wordpress/compose';
import { dateI18n, getSettings, humanTimeDiff } from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { ChannelLabel } from '../../common/ChannelAvatar';
import { network } from '../../common/networks';
import { config } from '../config';

// Without a call for this long the webhook is probably not set up any more.
const STALE_DAYS = 3;

// The history is read again this often while the tab is open, so a "Send test" shows up.
const POLL = 10000;

/**
 * State of the webhook from its last call.
 *
 * @param {number} last Unix time of the last call, 0 when never.
 * @return {{kind: string, text: string}} Pill.
 */
export function webhookStatus( last ) {
	if ( ! last ) {
		return {
			kind: 'off',
			text: __( 'Never received', 'social-relay-for-postiz' ),
		};
	}
	const days = ( Date.now() / 1000 - last ) / 86400;
	const text = sprintf(
		/* translators: %s: time since the last call, e.g. "5 minutes ago". */
		__( 'Last call %s', 'social-relay-for-postiz' ),
		humanTimeDiff( last * 1000 )
	);
	return { kind: days > STALE_DAYS ? 'warning' : 'ok', text };
}

/**
 * What "Check URL" found, as a notice.
 *
 * @param {Object} result { ok, code, status } from the REST route.
 * @return {{status: string, text: string}} Notice.
 */
function checkNotice( result ) {
	switch ( result.code ) {
		case 'ok':
			return {
				status: 'success',
				text: __(
					'The URL answers. Postiz can use it if it can reach this site.',
					'social-relay-for-postiz'
				),
			};
		case 'not_found':
			return {
				status: 'error',
				text: __(
					'The URL answers “not found” (404). The REST API may be off or rewritten: check the permalinks and any plugin that limits the REST API.',
					'social-relay-for-postiz'
				),
			};
		case 'forbidden':
			return {
				status: 'error',
				text: sprintf(
					/* translators: %d: HTTP status code. */
					__(
						'The URL is blocked (%d). A security plugin, Cloudflare or a firewall is stopping calls to the REST API: allow /wp-json/socialrelaycvi/v1/webhook/.',
						'social-relay-for-postiz'
					),
					result.status
				),
			};
		case 'unreachable':
			return {
				status: 'warning',
				text: __(
					'This server could not call its own address. Many hosts block that, so it does not mean Postiz cannot: use “Send test” in Postiz to be sure.',
					'social-relay-for-postiz'
				),
			};
		default:
			return {
				status: 'warning',
				text: sprintf(
					/* translators: %d: HTTP status code. */
					__(
						'Unexpected answer (%d). Use “Send test” in Postiz to check it.',
						'social-relay-for-postiz'
					),
					result.status
				),
			};
	}
}

/**
 * One call in the history: the shares it named, or what kind of call it was.
 *
 * @param {Object} props          Props.
 * @param {Object} props.call     { time, posts, items }.
 * @param {Object} props.channels Channels by id.
 * @return {Element} Row.
 */
function HistoryRow( { call, channels } ) {
	const { formats } = getSettings();
	let what = __(
		'Call without posts (a test from Postiz?).',
		'social-relay-for-postiz'
	);
	if ( call.posts && ! call.items.length ) {
		what = sprintf(
			/* translators: %d: number of posts. */
			_n(
				'%d post not sent from this site.',
				'%d posts not sent from this site.',
				call.posts,
				'social-relay-for-postiz'
			),
			call.posts
		);
	}

	return (
		<li className="socialrelaycvi-webhook__call">
			<span className="socialrelaycvi-webhook__time">
				{ dateI18n( formats.datetimeAbbreviated, call.time * 1000 ) }
			</span>
			<div className="socialrelaycvi-webhook__items">
				{ ! call.items.length && (
					<span className="description">{ what }</span>
				) }
				{ call.items.map( ( item, index ) => {
					const channel = channels[ item.integration_id ] || {
						name: network( item.identifier ).label,
						identifier: item.identifier,
					};
					return (
						<div
							key={ index }
							className="socialrelaycvi-webhook__item"
						>
							<ChannelLabel channel={ channel } />
							<span>
								{ item.edit_link ? (
									<a href={ item.edit_link }>
										{ item.post_title ||
											__(
												'(no title)',
												'social-relay-for-postiz'
											) }
									</a>
								) : (
									item.post_title
								) }
								{ item.status_label && (
									<span className="socialrelaycvi-webhook__status">
										{ ' · ' + item.status_label }
									</span>
								) }
								{ item.release_url && (
									<>
										{ ' · ' }
										<ExternalLink href={ item.release_url }>
											{ __(
												'View post',
												'social-relay-for-postiz'
											) }
										</ExternalLink>
									</>
								) }
							</span>
						</div>
					);
				} ) }
			</div>
		</li>
	);
}

/**
 * Webhook URL to paste in Postiz, its state, a step-by-step guide, a self-check and the last calls.
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
	const [ checking, setChecking ] = useState( false );
	const [ check, setCheck ] = useState( null );

	const copyRef = useCopyToClipboard( webhook.url, () => {
		setCopied( true );
		setTimeout( () => setCopied( false ), 2000 );
	} );

	// Read the history again while the tab is open, so a "Send test" from Postiz shows up.
	useEffect( () => {
		const timer = setInterval( () => {
			apiFetch( { path: `/${ config.namespace }/admin/webhook` } )
				.then( ( response ) =>
					setSettings( ( current ) => ( {
						...current,
						webhook: response,
					} ) )
				)
				.catch( () => {} );
		}, POLL );
		return () => clearInterval( timer );
	}, [ setSettings ] );

	const regenerate = () => {
		setConfirming( false );
		apiFetch( {
			path: `/${ config.namespace }/admin/webhook/regenerate`,
			method: 'POST',
		} ).then( ( response ) => {
			setSettings( { ...settings, webhook: response } );
			setRegenerated( true );
			setCheck( null );
		} );
	};

	const runCheck = () => {
		setChecking( true );
		setCheck( null );
		apiFetch( {
			path: `/${ config.namespace }/admin/webhook/check`,
			method: 'POST',
		} )
			.then( ( result ) => setCheck( checkNotice( result ) ) )
			.catch( ( err ) =>
				setCheck( { status: 'error', text: err.message } )
			)
			.finally( () => setChecking( false ) );
	};

	const status = webhookStatus( webhook.last );
	const channels = Object.fromEntries(
		settings.channels.map( ( channel ) => [ channel.id, channel ] )
	);

	return (
		<>
			<Card className="socialrelaycvi-webhook">
				<CardHeader>
					<h3>
						{ __( 'Postiz webhook', 'social-relay-for-postiz' ) }
					</h3>
					<span
						className={ `socialrelaycvi-connection__status is-${ status.kind }` }
					>
						{ status.text }
					</span>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Postiz calls this URL as soon as a post goes out, and the status in WordPress updates right away. Without it, the plugin asks Postiz every 15 minutes.',
							'social-relay-for-postiz'
						) }
					</p>

					{ webhook.local && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %s: site host name, e.g. localhost. */
								__(
									'This site (%s) is not reachable from the internet, so Postiz can only call it if both run on the same machine or network. Statuses still update every 15 minutes.',
									'social-relay-for-postiz'
								),
								webhook.host
							) }
						</Notice>
					) }

					{ status.kind === 'warning' && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'No call for several days. If you published in the meantime, check that the webhook still exists in Postiz with this URL.',
								'social-relay-for-postiz'
							) }
						</Notice>
					) }

					<div className="socialrelaycvi-settings__copy">
						<TextControl
							label={ __(
								'Webhook URL',
								'social-relay-for-postiz'
							) }
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

					{ regenerated && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'New webhook URL created. Update it in Postiz: the old one no longer works.',
								'social-relay-for-postiz'
							) }
						</Notice>
					) }

					<h4 className="socialrelaycvi-webhook__title">
						{ __(
							'Set it up in Postiz',
							'social-relay-for-postiz'
						) }
					</h4>
					<ol className="socialrelaycvi-webhook__steps">
						<li>
							{ __(
								'In Postiz, open Settings → Webhooks and add a webhook.',
								'social-relay-for-postiz'
							) }
						</li>
						<li>
							{ __(
								'Give it a name (for example “WordPress”), paste the URL above and choose all channels, or the ones used on this site.',
								'social-relay-for-postiz'
							) }
						</li>
						<li>
							{ __(
								'Save it and press “Send test”: the call appears below within a few seconds.',
								'social-relay-for-postiz'
							) }
						</li>
					</ol>

					{ check && (
						<Notice
							status={ check.status }
							onRemove={ () => setCheck( null ) }
						>
							{ check.text }
						</Notice>
					) }

					<div className="socialrelaycvi-settings__buttons">
						<Button
							variant="secondary"
							onClick={ runCheck }
							isBusy={ checking }
							disabled={ checking }
						>
							{ __( 'Check URL', 'social-relay-for-postiz' ) }
						</Button>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => setConfirming( true ) }
						>
							{ __(
								'Create a new URL',
								'social-relay-for-postiz'
							) }
						</Button>
					</div>

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

			<Card>
				<CardHeader>
					<h3>{ __( 'Last calls', 'social-relay-for-postiz' ) }</h3>
				</CardHeader>
				<CardBody>
					{ webhook.history.length ? (
						<ul className="socialrelaycvi-webhook__history">
							{ webhook.history.map( ( call, index ) => (
								<HistoryRow
									key={ index }
									call={ call }
									channels={ channels }
								/>
							) ) }
						</ul>
					) : (
						<p className="description">
							{ __(
								'No calls yet. The first one shows up here when Postiz publishes, or when you press “Send test”.',
								'social-relay-for-postiz'
							) }
						</p>
					) }
				</CardBody>
			</Card>
		</>
	);
}
