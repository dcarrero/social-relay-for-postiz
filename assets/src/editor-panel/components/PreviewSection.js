import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { config } from '../config';

const boxStyle = {
	whiteSpace: 'pre-wrap',
	overflowWrap: 'anywhere',
	fontSize: '12px',
	border: '1px solid #dcdcde',
	borderRadius: '4px',
	padding: '8px',
	marginTop: '4px',
	background: '#fff',
};

/**
 * One network in the preview.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Preview item from the REST route.
 * @return {Element} Item.
 */
function PreviewItem( { item } ) {
	const longest = Math.max( ...item.parts.map( ( part ) => part.length ) );
	const over = item.limit && longest > item.limit;

	return (
		<div style={ { marginBottom: '12px' } }>
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					fontSize: '12px',
					fontWeight: 600,
				} }
			>
				<span>{ `${ item.name } · ${ item.identifier }` }</span>
				<span style={ { color: over ? '#cc1818' : '#007a1f' } }>
					{ item.limit
						? `${ longest } / ${ item.limit }`
						: String( longest ) }
				</span>
			</div>
			<div style={ boxStyle }>{ item.parts.join( '\n\n— — —\n\n' ) }</div>
			{ item.parts.length > 1 && (
				<p className="description">
					{ sprintf(
						/* translators: %d: number of parts in a thread. */
						_n(
							'Thread of %d part.',
							'Thread of %d parts.',
							item.parts.length,
							'social-relay-for-postiz'
						),
						item.parts.length
					) }
				</p>
			) }
		</div>
	);
}

/**
 * Messages that would be sent, from the last saved version. Nothing is sent.
 *
 * @param {Object} props        Props.
 * @param {number} props.postId Post id.
 * @param {number} props.saves  Completed saves, to reload after each one.
 * @return {Element|null} Section.
 */
export default function PreviewSection( { postId, saves } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	const load = useCallback( () => {
		if ( ! postId ) {
			return;
		}
		setLoading( true );
		apiFetch( { path: `/${ config.namespace }/posts/${ postId }/preview` } )
			.then( ( response ) => {
				setData( response );
				setError( null );
			} )
			.catch( ( err ) => setError( err.message || String( err ) ) )
			.finally( () => setLoading( false ) );
	}, [ postId ] );

	useEffect( load, [ load, saves ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! data ) {
		return loading ? <Spinner /> : null;
	}

	return (
		<>
			{ ! data.passes_conditions && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This post does not match the profile conditions and will not be shared.',
						'social-relay-for-postiz'
					) }
				</Notice>
			) }
			{ data.items.map( ( item ) => (
				<PreviewItem key={ item.integration_id } item={ item } />
			) ) }
			{ data.skipped.map( ( skip ) => (
				<Notice
					key={ skip.integration_id }
					status="warning"
					isDismissible={ false }
				>
					{ skip.reason }
				</Notice>
			) ) }
			<p className="description">
				{ __(
					'Based on the last saved version.',
					'social-relay-for-postiz'
				) }{ ' ' }
				<Button variant="link" onClick={ load } disabled={ loading }>
					{ __( 'Refresh', 'social-relay-for-postiz' ) }
				</Button>
			</p>
		</>
	);
}
