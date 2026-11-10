import apiFetch from '@wordpress/api-fetch';
import {
	BaseControl,
	CheckboxControl,
	FormTokenField,
	SearchControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
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

// Above this many categories the list gets a search box.
const SEARCH_FROM = 8;

/**
 * Categories of the site as checkboxes, in a scrolling box with a search when there are many.
 *
 * @param {Object}   props            Props.
 * @param {string}   props.id         Unique id for the label.
 * @param {Object[]} props.categories All categories ({ id, name }).
 * @param {number[]} props.value      Checked category ids.
 * @param {Function} props.onToggle   Receives ( id, checked ).
 * @return {Element} Field.
 */
function CategoriesField( { id, categories, value, onToggle } ) {
	const [ search, setSearch ] = useState( '' );
	const term = search.trim().toLowerCase();
	const shown = term
		? categories.filter( ( category ) =>
				category.name.toLowerCase().includes( term )
		  )
		: categories;

	return (
		<BaseControl
			id={ id }
			label={ __( 'Categories', 'social-relay-for-postiz' ) }
			help={
				value.length
					? sprintf(
							/* translators: %d: number of categories. */
							_n(
								'%d category selected.',
								'%d categories selected.',
								value.length,
								'social-relay-for-postiz'
							),
							value.length
					  )
					: undefined
			}
			__nextHasNoMarginBottom
		>
			{ categories.length > SEARCH_FROM && (
				<SearchControl
					value={ search }
					onChange={ setSearch }
					placeholder={ __(
						'Search categories',
						'social-relay-for-postiz'
					) }
					size="compact"
					__nextHasNoMarginBottom
				/>
			) }
			<div
				className="socialrelaycvi-settings__terms"
				role="group"
				aria-label={ __( 'Categories', 'social-relay-for-postiz' ) }
			>
				{ shown.map( ( category ) => (
					<CheckboxControl
						key={ category.id }
						label={ category.name }
						checked={ value.includes( category.id ) }
						onChange={ ( checked ) =>
							onToggle( category.id, checked )
						}
						__nextHasNoMarginBottom
					/>
				) ) }
				{ ! shown.length && (
					<p className="description">
						{ __(
							'No categories match.',
							'social-relay-for-postiz'
						) }
					</p>
				) }
			</div>
		</BaseControl>
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
		{
			kind: 'include',
			title: __( 'Only', 'social-relay-for-postiz' ),
			hint: __(
				'Share only posts with at least one of these. Empty: any post.',
				'social-relay-for-postiz'
			),
		},
		{
			kind: 'exclude',
			title: __( 'Never', 'social-relay-for-postiz' ),
			hint: __(
				'Never share posts with any of these, even if they match “Only”.',
				'social-relay-for-postiz'
			),
		},
	];

	return (
		<>
			<p className="description">
				{ __(
					'Choose which posts are shared by their categories and tags (those of this site). Leave everything empty to share all posts.',
					'social-relay-for-postiz'
				) }
			</p>
			<div className="socialrelaycvi-settings__columns">
				{ groups.map( ( { kind, title, hint } ) => (
					<div key={ kind }>
						<h4>{ title }</h4>
						<p className="description socialrelaycvi-settings__hint">
							{ hint }
						</p>
						{ postType.categories && (
							<CategoriesField
								id={ `socialrelaycvi-${ kind }-categories` }
								categories={ categories }
								value={
									profile[ `${ kind }_categories` ] || []
								}
								onToggle={ ( id, checked ) =>
									toggleCategory( kind, id, checked )
								}
							/>
						) }
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
