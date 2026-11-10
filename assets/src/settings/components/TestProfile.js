import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	ComboboxControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import PreviewCard from '../../common/PreviewCard';
import { config } from '../config';

/**
 * Pick a real post and see the messages the saved profile would send. Nothing is sent.
 *
 * @param {Object}  props          Props.
 * @param {Object}  props.postType Post type info (label, rest_base).
 * @param {boolean} props.dirty    Whether there are unsaved changes.
 * @return {Element} Panel.
 */
export default function TestProfile( { postType, dirty } ) {
	const [ search, setSearch ] = useState( '' );
	const [ options, setOptions ] = useState( [] );
	const [ postId, setPostId ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		if ( ! postType.rest_base ) {
			return;
		}
		// Wait until typing pauses, and drop answers that arrive after a newer search.
		let stale = false;
		const timer = setTimeout( () => {
			apiFetch( {
				path: addQueryArgs( `/wp/v2/${ postType.rest_base }`, {
					search,
					per_page: 10,
					status: 'publish,draft,future',
					_fields: 'id,title',
				} ),
			} )
				.then( ( posts ) => {
					if ( stale ) {
						return;
					}
					setOptions(
						posts.map( ( post ) => ( {
							value: post.id,
							label:
								decodeEntities( post.title?.rendered || '' ) ||
								__( '(no title)', 'social-relay-for-postiz' ),
						} ) )
					);
				} )
				.catch( () => ! stale && setOptions( [] ) );
		}, 300 );
		return () => {
			stale = true;
			clearTimeout( timer );
		};
	}, [ search, postType.rest_base ] );

	useEffect( () => {
		if ( ! postId ) {
			setPreview( null );
			return;
		}
		setLoading( true );
		setError( '' );
		apiFetch( { path: `/${ config.namespace }/posts/${ postId }/preview` } )
			.then( setPreview )
			.catch( ( err ) => setError( err.message || String( err ) ) )
			.finally( () => setLoading( false ) );
	}, [ postId ] );

	const title = options.find( ( option ) => option.value === postId )?.label;

	return (
		<Card className="socialrelaycvi-settings__test">
			<CardHeader>
				<h3>
					{ __( 'Test this profile', 'social-relay-for-postiz' ) }
				</h3>
			</CardHeader>
			<CardBody>
				<p className="description">
					{ __(
						'Pick a post to see the messages the saved profile would send. Nothing is sent.',
						'social-relay-for-postiz'
					) }
				</p>
				{ dirty && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Save the profile to test your latest changes.',
							'social-relay-for-postiz'
						) }
					</Notice>
				) }
				<ComboboxControl
					label={ postType.label }
					value={ postId }
					options={ options }
					onChange={ setPostId }
					onFilterValueChange={ setSearch }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<div style={ { height: 12 } } />
				{ loading && <Spinner /> }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ preview && ! loading && (
					<>
						{ ! preview.passes_conditions && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'This post does not match the profile conditions and will not be shared.',
									'social-relay-for-postiz'
								) }
							</Notice>
						) }
						{ preview.items.map( ( item ) => (
							<PreviewCard
								key={ item.integration_id }
								item={ item }
								linkTitle={ title }
							/>
						) ) }
						{ preview.skipped.map( ( skip ) => (
							<Notice
								key={ skip.integration_id }
								status="warning"
								isDismissible={ false }
							>
								{ skip.reason }
							</Notice>
						) ) }
						{ ! preview.items.length &&
							! preview.skipped.length && (
								<p>
									{ __(
										'No channels selected.',
										'social-relay-for-postiz'
									) }
								</p>
							) }
					</>
				) }
			</CardBody>
		</Card>
	);
}
