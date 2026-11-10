import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { channelsById, config, statusLabel, STATUS_COLORS } from '../config';
import ChannelBadge from './ChannelBadge';

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
 * Status of each network after publishing.
 *
 * @param {Object} props        Props.
 * @param {number} props.postId Post id.
 * @param {number} props.saves  Completed saves, to reload after each one.
 * @return {Element} Section.
 */
export default function StatusSection( { postId, saves } ) {
	const [ rows, setRows ] = useState( null );

	const load = useCallback( () => {
		if ( ! postId ) {
			return;
		}
		apiFetch( { path: `/${ config.namespace }/posts/${ postId }/shares` } )
			.then( setRows )
			.catch( () => setRows( [] ) );
	}, [ postId ] );

	useEffect( load, [ load, saves ] );

	// Refresh every 30 s while something is still on its way.
	const pending = ( rows || [] ).some( ( row ) => row.status === 'queued' );
	useEffect( () => {
		if ( ! pending ) {
			return undefined;
		}
		const timer = setInterval( load, 30000 );
		return () => clearInterval( timer );
	}, [ pending, load ] );

	if ( rows === null ) {
		return <Spinner />;
	}

	if ( ! rows.length ) {
		return (
			<p className="description">
				{ __(
					'Nothing sent to Postiz yet.',
					'social-relay-for-postiz'
				) }
			</p>
		);
	}

	return (
		<>
			{ latestPerChannel( rows ).map( ( row ) => (
				<div
					key={ row.id }
					style={ {
						borderTop: '1px solid #f0f0f1',
						padding: '8px 0',
					} }
				>
					<div
						style={ {
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							gap: '8px',
						} }
					>
						<ChannelBadge
							channel={ channelsById[ row.integration_id ] }
						/>
						<span
							style={ {
								fontSize: '11px',
								fontWeight: 600,
								color: STATUS_COLORS[ row.status ] || '#1e1e1e',
							} }
						>
							{ statusLabel( row.status ) }
						</span>
					</div>
					{ row.release_url && (
						<ExternalLink href={ row.release_url }>
							{ __( 'View post', 'social-relay-for-postiz' ) }
						</ExternalLink>
					) }
					{ row.error && (
						<p
							style={ {
								color: '#cc1818',
								fontSize: '12px',
								margin: '4px 0 0',
							} }
						>
							{ row.error }
						</p>
					) }
				</div>
			) ) }
			<p className="description">
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
			</p>
		</>
	);
}
