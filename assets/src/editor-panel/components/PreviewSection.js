import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

import PreviewCard from '../../common/PreviewCard';
import SkippedNotices from '../../common/SkippedNotices';
import { config } from '../config';

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
	const title = useSelect(
		( select ) => select( editorStore ).getEditedPostAttribute( 'title' ),
		[]
	);

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
				<PreviewCard
					key={ item.integration_id }
					item={ item }
					linkTitle={ title }
				/>
			) ) }
			<SkippedNotices
				skipped={ data.skipped }
				names={ Object.fromEntries(
					config.channels.map( ( channel ) => [
						channel.id,
						channel.name,
					] )
				) }
			/>
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
