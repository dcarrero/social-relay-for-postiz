import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Flex,
	Modal,
	Spinner,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { ChannelLabel } from '../../common/ChannelAvatar';
import {
	channelsById,
	config,
	statusLabel,
	STATUS_COLORS,
	timingLabel,
} from '../config';
import { effectiveChannels } from '../hooks';

// After an action, poll quickly for a minute: the queue sends in the background.
const FAST_POLL = 5000;
const FAST_POLL_FOR = 60000;

/**
 * "Draft in Postiz" → "draft in Postiz": only the first letter, names keep their case.
 *
 * @param {string} text Text.
 * @return {string} Text.
 */
function lowerFirst( text ) {
	return text.charAt( 0 ).toLowerCase() + text.slice( 1 );
}

/**
 * Latest row per channel; rows arrive newest first.
 *
 * @param {Object[]} rows Share rows.
 * @return {Object[]} One row per channel.
 */
function latestPerChannel( rows ) {
	const seen = new Set();
	return rows.filter( ( row ) => {
		if ( seen.has( row.integration_id ) ) {
			return false;
		}
		seen.add( row.integration_id );
		return true;
	} );
}

/**
 * WordPress modal asking to confirm an action that reaches Postiz.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.title     Title.
 * @param {string}   props.children  Body.
 * @param {string}   props.confirm   Confirm button text.
 * @param {string}   props.dismiss   Close button text.
 * @param {boolean}  props.danger    Red confirm button.
 * @param {Function} props.onConfirm Confirm handler.
 * @param {Function} props.onClose   Close handler.
 * @return {Element} Modal.
 */
function Confirm( {
	title,
	children,
	confirm,
	dismiss,
	danger,
	onConfirm,
	onClose,
} ) {
	return (
		<Modal title={ title } onRequestClose={ onClose } size="small">
			<p>{ children }</p>
			<Flex justify="flex-end" gap={ 2 }>
				<Button variant="tertiary" onClick={ onClose }>
					{ dismiss || __( 'Cancel', 'social-relay-for-postiz' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ danger }
					onClick={ onConfirm }
				>
					{ confirm }
				</Button>
			</Flex>
		</Modal>
	);
}

/**
 * Status of each network after publishing, plus "share again", "retry" and "cancel pending".
 *
 * @param {Object} props        Props.
 * @param {number} props.postId Post id.
 * @param {number} props.saves  Completed saves, to reload after each one.
 * @param {Object} props.meta   Post meta (channels and timing for the confirmation).
 * @return {Element} Section.
 */
export default function StatusSection( { postId, saves, meta } ) {
	const [ rows, setRows ] = useState( null );
	const [ asking, setAsking ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ watchUntil, setWatchUntil ] = useState( 0 );
	const dirty = useSelect(
		( select ) => select( editorStore ).isEditedPostDirty(),
		[]
	);
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const load = useCallback( () => {
		if ( ! postId ) {
			return;
		}
		apiFetch( { path: `/${ config.namespace }/posts/${ postId }/shares` } )
			.then( setRows )
			.catch( () => setRows( [] ) );
	}, [ postId ] );

	useEffect( load, [ load, saves ] );

	// Every 30 s while something is on its way; every 5 s for a minute after an action.
	const pending = ( rows || [] ).some( ( row ) => row.status === 'queued' );
	useEffect( () => {
		const fast = watchUntil > Date.now();
		if ( ! pending && ! fast ) {
			return undefined;
		}
		const timer = setInterval(
			() => {
				load();
				if ( fast && Date.now() > watchUntil ) {
					setWatchUntil( 0 );
				}
			},
			fast ? FAST_POLL : 30000
		);
		return () => clearInterval( timer );
	}, [ pending, watchUntil, load ] );

	const run = ( path, data, done ) => {
		setAsking( null );
		setBusy( true );
		apiFetch( {
			path: `/${ config.namespace }/posts/${ postId }/${ path }`,
			method: 'POST',
			data,
		} )
			.then( ( response ) => {
				done( response );
				setWatchUntil( Date.now() + FAST_POLL_FOR );
				load();
			} )
			.catch( ( err ) =>
				createErrorNotice( err.message || String( err ), {
					type: 'snackbar',
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const shareAgain = ( channels = [] ) =>
		run( 'share', { channels }, () =>
			createSuccessNotice(
				__(
					'Queued for Postiz. The status updates here in a few seconds.',
					'social-relay-for-postiz'
				),
				{ type: 'snackbar' }
			)
		);

	const cancelPending = () =>
		run( 'cancel', {}, ( response ) =>
			createSuccessNotice(
				response.cancelled
					? sprintf(
							/* translators: %d: number of groups. */
							_n(
								'Cancelled %d pending group in Postiz.',
								'Cancelled %d pending groups in Postiz.',
								response.cancelled,
								'social-relay-for-postiz'
							),
							response.cancelled
					  )
					: __(
							'Nothing pending in Postiz for this post.',
							'social-relay-for-postiz'
					  ),
				{ type: 'snackbar' }
			)
		);

	if ( rows === null ) {
		return <Spinner />;
	}

	const latest = latestPerChannel( rows );
	const counts = {};
	latest.forEach( ( row ) => {
		counts[ row.status ] = ( counts[ row.status ] || 0 ) + 1;
	} );
	const summary = Object.entries( counts )
		.map(
			( [ status, count ] ) =>
				`${ count } ${ lowerFirst( statusLabel( status ) ) }`
		)
		.join( ' · ' );
	const cancellable = latest.some(
		( row ) => row.status === 'queued' || row.status === 'draft'
	);
	const channelCount = effectiveChannels( meta ).filter(
		( id ) => channelsById[ id ] && ! channelsById[ id ].disabled
	).length;

	return (
		<>
			{ ! latest.length && (
				<p className="description">
					{ __(
						'Nothing sent to Postiz yet.',
						'social-relay-for-postiz'
					) }
				</p>
			) }

			{ latest.length > 0 && (
				<>
					<p className="socialrelaycvi-status__summary">
						{ summary }
					</p>
					<div
						className="socialrelaycvi-status__bar"
						aria-hidden="true"
					>
						{ Object.entries( counts ).map(
							( [ status, count ] ) => (
								<span
									key={ status }
									style={ {
										flex: count,
										background:
											STATUS_COLORS[ status ] ||
											'#949494',
									} }
								/>
							)
						) }
					</div>
				</>
			) }

			{ latest.map( ( row ) => {
				const channel = channelsById[ row.integration_id ] || {
					name: row.integration_id,
					identifier: '',
				};
				const color = STATUS_COLORS[ row.status ] || '#1e1e1e';
				return (
					<div key={ row.id } className="socialrelaycvi-status__row">
						<div className="socialrelaycvi-status__head">
							<ChannelLabel channel={ channel } />
							<span
								className="socialrelaycvi-status__pill"
								style={ {
									color,
									background: color + '14',
									border: `1px solid ${ color }33`,
								} }
							>
								{ statusLabel( row.status ) }
							</span>
						</div>
						{ row.error && (
							<p className="socialrelaycvi-status__detail is-error">
								{ row.error }
							</p>
						) }
						{ ( row.release_url ||
							( row.status === 'error' &&
								channelsById[ row.integration_id ] ) ) && (
							<p className="socialrelaycvi-status__detail">
								{ row.release_url && (
									<ExternalLink href={ row.release_url }>
										{ __(
											'View post',
											'social-relay-for-postiz'
										) }
									</ExternalLink>
								) }
								{ row.status === 'error' &&
									channelsById[ row.integration_id ] && (
										<Button
											variant="link"
											disabled={ busy || dirty }
											onClick={ () =>
												shareAgain( [
													row.integration_id,
												] )
											}
										>
											{ __(
												'Retry',
												'social-relay-for-postiz'
											) }
										</Button>
									) }
							</p>
						) }
					</div>
				);
			} ) }

			<div className="socialrelaycvi-status__actions">
				<Button
					variant="secondary"
					className="socialrelaycvi-status__again"
					disabled={ busy || dirty || ! channelCount }
					isBusy={ busy }
					onClick={ () => setAsking( 'share' ) }
				>
					{ __( 'Share again now…', 'social-relay-for-postiz' ) }
				</Button>
				{ dirty && (
					<p className="description">
						{ __(
							'Save your changes first: Postiz gets the saved version.',
							'social-relay-for-postiz'
						) }
					</p>
				) }
			</div>

			<p className="description socialrelaycvi-status__footer">
				<Button variant="link" onClick={ load }>
					{ __( 'Refresh', 'social-relay-for-postiz' ) }
				</Button>
				{ config.canManage && (
					<>
						{ ' · ' }
						<ExternalLink href={ config.logUrl }>
							{ __( 'Log', 'social-relay-for-postiz' ) }
						</ExternalLink>
					</>
				) }
				{ cancellable && (
					<>
						{ ' · ' }
						<Button
							variant="link"
							isDestructive
							disabled={ busy }
							onClick={ () => setAsking( 'cancel' ) }
						>
							{ __(
								'Cancel pending',
								'social-relay-for-postiz'
							) }
						</Button>
					</>
				) }
			</p>

			{ asking === 'share' && (
				<Confirm
					title={ __(
						'Share again now?',
						'social-relay-for-postiz'
					) }
					confirm={ __( 'Share again', 'social-relay-for-postiz' ) }
					onConfirm={ () => shareAgain() }
					onClose={ () => setAsking( null ) }
				>
					{ sprintf(
						/* translators: 1: number of channels, 2: timing, e.g. "Next free slot in Postiz". */
						_n(
							'The saved post is sent to Postiz again for %1$d channel. Timing: %2$s.',
							'The saved post is sent to Postiz again for %1$d channels. Timing: %2$s.',
							channelCount,
							'social-relay-for-postiz'
						),
						channelCount,
						timingLabel( meta )
					) }
				</Confirm>
			) }

			{ asking === 'cancel' && (
				<Confirm
					title={ __(
						'Cancel what is pending?',
						'social-relay-for-postiz'
					) }
					confirm={ __(
						'Cancel pending',
						'social-relay-for-postiz'
					) }
					dismiss={ __( 'Keep them', 'social-relay-for-postiz' ) }
					danger
					onConfirm={ cancelPending }
					onClose={ () => setAsking( null ) }
				>
					{ __(
						'Scheduled posts and drafts of this post are deleted in Postiz. What is already published stays.',
						'social-relay-for-postiz'
					) }
				</Confirm>
			) }
		</>
	);
}
