import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './tag-menu.css';

/**
 * Template tags, grouped, with what each one gives.
 *
 * @return {Array<{label: string, tags: Array<[string, string]>}>} Groups of [tag, description].
 */
function groups() {
	return [
		{
			label: __( 'Post', 'social-relay-for-postiz' ),
			tags: [
				[ '{title}', __( 'Title', 'social-relay-for-postiz' ) ],
				[
					'{excerpt|limit:180}',
					__(
						'Excerpt, up to 180 characters',
						'social-relay-for-postiz'
					),
				],
				[
					'{content|words:40}',
					__(
						'First 40 words of the content',
						'social-relay-for-postiz'
					),
				],
				[
					'{url}',
					__( 'Link to the post', 'social-relay-for-postiz' ),
				],
				[
					'{shortlink}',
					__( 'Short link', 'social-relay-for-postiz' ),
				],
				[ '{author}', __( 'Author', 'social-relay-for-postiz' ) ],
				[ '{date}', __( 'Date', 'social-relay-for-postiz' ) ],
				[
					'{reading_time}',
					__( 'Minutes to read', 'social-relay-for-postiz' ),
				],
			],
		},
		{
			label: __( 'Categories and tags', 'social-relay-for-postiz' ),
			tags: [
				[
					'{first_category}',
					__(
						'Main category (Yoast or Rank Math) or the first one',
						'social-relay-for-postiz'
					),
				],
				[
					'{categories}',
					__( 'All categories', 'social-relay-for-postiz' ),
				],
				[
					'{hashtags:post_tag}',
					__( 'Tags as #hashtags', 'social-relay-for-postiz' ),
				],
				[
					'{categories|hashtag}',
					__( 'Categories as #hashtags', 'social-relay-for-postiz' ),
				],
			],
		},
		{
			label: __( 'Site and SEO', 'social-relay-for-postiz' ),
			tags: [
				[ '{site_name}', __( 'Site name', 'social-relay-for-postiz' ) ],
				[
					'{seo_title}',
					__( 'SEO title, or the title', 'social-relay-for-postiz' ),
				],
				[
					'{seo_description}',
					__(
						'Meta description, or the excerpt',
						'social-relay-for-postiz'
					),
				],
			],
		},
		{
			label: __( 'Threads', 'social-relay-for-postiz' ),
			tags: [
				[
					'\n---\n',
					__( 'New part of the thread', 'social-relay-for-postiz' ),
				],
			],
		},
	];
}

/**
 * Inserts text at the cursor of a textarea and puts the cursor after it.
 *
 * @param {Object}   ref      Ref to the textarea.
 * @param {string}   value    Current text.
 * @param {string}   text     Text to insert.
 * @param {Function} onChange Change handler.
 */
export function insertAtCursor( ref, value, text, onChange ) {
	const field = ref.current;
	const start = field ? field.selectionStart : value.length;
	const end = field ? field.selectionEnd : value.length;
	onChange( value.slice( 0, start ) + text + value.slice( end ) );
	window.requestAnimationFrame( () => {
		if ( field ) {
			field.focus();
			field.selectionStart = field.selectionEnd = start + text.length;
		}
	} );
}

/**
 * "Insert tag" menu: each entry shows the tag and what it gives.
 *
 * @param {Object}   props          Props.
 * @param {Function} props.onInsert Receives the tag text.
 * @return {Element} Menu.
 */
export default function TagMenu( { onInsert } ) {
	return (
		<DropdownMenu
			icon={ null }
			text={ '{ } ' + __( 'Insert tag', 'social-relay-for-postiz' ) }
			label={ __( 'Insert tag', 'social-relay-for-postiz' ) }
			toggleProps={ { variant: 'secondary', size: 'compact' } }
			popoverProps={ { className: 'socialrelaycvi-tag-menu' } }
		>
			{ ( { onClose } ) =>
				groups().map( ( group ) => (
					<MenuGroup key={ group.label } label={ group.label }>
						{ group.tags.map( ( [ tag, description ] ) => (
							<MenuItem
								key={ tag }
								info={ description }
								onClick={ () => {
									onInsert( tag );
									onClose();
								} }
							>
								<code>{ tag.trim() }</code>
							</MenuItem>
						) ) }
					</MenuGroup>
				) )
			}
		</DropdownMenu>
	);
}
