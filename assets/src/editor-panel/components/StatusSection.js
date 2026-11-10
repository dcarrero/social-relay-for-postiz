import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { channelsById, config, statusLabel, STATUS_COLORS } from '../config';
import { ChannelLabel } from '../../common/ChannelAvatar';

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

	const latest = latestPerChannel( rows );
	const counts = {};
	latest.forEach( ( row ) => {
		counts[ row.status ] = ( counts[ row.status ] || 0 ) + 1;
	} );
	const summary = Object.entries( counts )
		.map(
			( [ status, count ] ) =>
				`${ count } ${ statusLabel( status ).toLowerCase() }`
		)
		.join( ' · ' );

	return (
		<>
			<p className="socialrelaycvi-status__summary">{ summary }</p>
			<div className="socialrelaycvi-status__bar" aria-hidden="true">
				{ Object.entries( counts ).map( ( [ status, count ] ) => (
					<span
						key={ status }
						style={ {
							flex: count,
							background: STATUS_COLORS[ status ] || '#949494',
						} }
					/>
				) ) }
			</div>
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
						{ row.release_url && (
							<p className="socialrelaycvi-status__detail">
								<ExternalLink href={ row.release_url }>
									{ __(
										'View post',
										'social-relay-for-postiz'
									) }
								</ExternalLink>
							</p>
						) }
					</div>
				);
			} ) }
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
			</p>
		</>
	);
}
