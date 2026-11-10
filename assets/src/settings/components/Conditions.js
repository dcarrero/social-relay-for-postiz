import apiFetch from '@wordpress/api-fetch';
import { CheckboxControl, FormTokenField } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Tag names typed by the user, with suggestions from existing tags.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string[]} props.value    Tag names.
 * @param {Function} props.onChange Receives the new list.
 * @return {Element} Field.
 */
function TagsField( { label, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const [ suggestions, setSuggestions ] = useState( [] );

	useEffect( () => {
		if ( search.length < 2 ) {
			return;
		}
		apiFetch( {
			path: addQueryArgs( '/wp/v2/tags', {
				search,
				per_page: 20,
				_fields: 'name',
			} ),
		} ).then( ( tags ) =>
			setSuggestions( tags.map( ( tag ) => tag.name ) )
		);
	}, [ search ] );

	return (
		<FormTokenField
			label={ label }
			value={ value }
			suggestions={ suggestions }
			onInputChange={ setSearch }
			onChange={ ( tokens ) =>
				onChange(
					tokens.map( ( token ) =>
						typeof token === 'string' ? token : token.value
					)
				)
			}
			__experimentalExpandOnFocus
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

/**
 * "Only" and "Never" rules on categories and tags.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.profile    Profile.
 * @param {Object}   props.postType   { categories, tags } support.
 * @param {Object[]} props.categories All categories.
 * @param {Function} props.update     Updates profile fields.
 * @return {Element|null} Section.
 */
export default function Conditions( {
	profile,
	postType,
	categories,
	update,
} ) {
	if ( ! postType.categories && ! postType.tags ) {
		return null;
	}

	const toggleCategory = ( kind, id, checked ) => {
		const key = `${ kind }_categories`;
		const current = profile[ key ] || [];
		update( {
			[ key ]: checked
				? [ ...current, id ]
				: current.filter( ( item ) => item !== id ),
		} );
	};

	const groups = [
		{ kind: 'include', title: __( 'Only', 'social-relay-for-postiz' ) },
		{ kind: 'exclude', title: __( 'Never', 'social-relay-for-postiz' ) },
	];

	return (
		<>
			<p className="description">
				{ __(
					'Share only posts in at least one of the “Only” terms, and never posts in a “Never” term. Leave everything empty to share all posts.',
					'social-relay-for-postiz'
				) }
			</p>
			<div className="socialrelaycvi-settings__columns">
				{ groups.map( ( { kind, title } ) => (
					<div key={ kind }>
						<h4>{ title }</h4>
						{ postType.categories &&
							categories.map( ( category ) => (
								<CheckboxControl
									key={ category.id }
									label={ category.name }
									checked={ (
										profile[ `${ kind }_categories` ] || []
									).includes( category.id ) }
									onChange={ ( checked ) =>
										toggleCategory(
											kind,
											category.id,
											checked
										)
									}
									__nextHasNoMarginBottom
								/>
							) ) }
						{ postType.tags && (
							<TagsField
								label={ __(
									'Tags',
									'social-relay-for-postiz'
								) }
								value={ profile[ `${ kind }_tags` ] || [] }
								onChange={ ( tags ) =>
									update( { [ `${ kind }_tags` ]: tags } )
								}
							/>
						) }
					</div>
				) ) }
			</div>
		</>
	);
}
