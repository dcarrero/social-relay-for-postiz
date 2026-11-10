import {
	MediaUpload,
	MediaUploadCheck,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	// Stable in practice and used across core; still exported with the prefix in WordPress 7.1.
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';
import { __, sprintf } from '@wordpress/i18n';

import { network } from '../../common/networks';
import { channelsById, config, META, NO_IMAGES } from '../config';
import { effectiveChannels } from '../hooks';

const MAX = 10;

/**
 * Label of a profile image setting.
 *
 * @param {string} mode featured, content or none.
 * @return {string} Label.
 */
export function imagesLabel( mode ) {
	switch ( mode ) {
		case 'content':
			return __(
				'Featured image and images in the post',
				'social-relay-for-postiz'
			);
		case 'none':
			return __( 'No images', 'social-relay-for-postiz' );
		default:
			return __( 'Featured image', 'social-relay-for-postiz' );
	}
}

/**
 * Attachment ids of image, gallery, media & text and cover blocks, in order.
 *
 * @param {Object[]} blocks Blocks.
 * @return {number[]} Ids.
 */
function blockImageIds( blocks ) {
	const ids = [];
	for ( const block of blocks ) {
		const { name, attributes = {} } = block;
		if ( name === 'core/image' && attributes.id ) {
			ids.push( attributes.id );
		} else if ( name === 'core/media-text' && attributes.mediaId ) {
			if ( attributes.mediaType !== 'video' ) {
				ids.push( attributes.mediaId );
			}
		} else if (
			name === 'core/cover' &&
			attributes.id &&
			attributes.backgroundType !== 'video'
		) {
			ids.push( attributes.id );
		} else if ( name === 'core/gallery' && attributes.ids?.length ) {
			ids.push( ...attributes.ids );
		}
		if ( block.innerBlocks?.length ) {
			ids.push( ...blockImageIds( block.innerBlocks ) );
		}
	}
	return ids;
}

/**
 * Images of this post: its own choice, none, or what the profile setting picks.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.meta    Post meta.
 * @param {Function} props.setMeta Meta setter.
 * @return {Element} Section.
 */
export default function ImagesSection( { meta, setMeta } ) {
	const own = Array.isArray( meta[ META.images ] ) ? meta[ META.images ] : [];
	// "Choose" with nothing picked yet lives only here until images are selected.
	const [ picking, setPicking ] = useState( false );
	let mode = picking ? 'choose' : 'profile';
	if ( own.length === 1 && own[ 0 ] === NO_IMAGES ) {
		mode = 'none';
	} else if ( own.length ) {
		mode = 'choose';
	}

	const profileMode = config.profile.images;
	const { featured, blocks } = useSelect(
		( select ) => ( {
			featured:
				select( editorStore ).getEditedPostAttribute(
					'featured_media'
				) || 0,
			// Only read the blocks when the profile uses them.
			blocks:
				profileMode === 'content'
					? select( blockEditorStore ).getBlocks()
					: null,
		} ),
		[ profileMode ]
	);
	const contentIds = useMemo(
		() => ( blocks ? blockImageIds( blocks ) : [] ),
		[ blocks ]
	);

	let ids = [];
	if ( mode === 'choose' ) {
		ids = own;
	} else if ( mode === 'profile' && profileMode !== 'none' ) {
		ids = [ ...new Set( [ featured, ...contentIds ].filter( Boolean ) ) ];
	}
	ids = ids.slice( 0, MAX );

	const idsKey = ids.join( ',' );
	const media = useSelect(
		( select ) =>
			idsKey
				.split( ',' )
				.filter( Boolean )
				.map( ( id ) => select( coreStore ).getMedia( Number( id ) ) ),
		[ idsKey ]
	);

	// Networks among the chosen channels that take fewer images than there are.
	const fewer = [];
	effectiveChannels( meta ).forEach( ( id ) => {
		const channel = channelsById[ id ];
		const max = channel ? config.maxImages?.[ channel.identifier ] : 0;
		const label = channel ? network( channel.identifier ).label : '';
		if (
			max &&
			ids.length > max &&
			! fewer.some( ( item ) => item.label === label )
		) {
			fewer.push( { label, max } );
		}
	} );

	const choose = ( selection ) => {
		setPicking( false );
		setMeta(
			META.images,
			( Array.isArray( selection ) ? selection : [ selection ] )
				.map( ( item ) => item.id )
				.slice( 0, MAX )
		);
	};

	const setMode = ( value ) => {
		setPicking( value === 'choose' );
		if ( value === 'profile' ) {
			setMeta( META.images, [] );
		} else if ( value === 'none' ) {
			setMeta( META.images, [ NO_IMAGES ] );
		} else if ( ids.length ) {
			// Start from what the profile would send, to edit it.
			setMeta( META.images, ids );
		}
	};

	let help = sprintf(
		/* translators: %s: image setting of the profile. */
		__( 'Profile setting: %s.', 'social-relay-for-postiz' ),
		imagesLabel( profileMode )
	);
	if ( mode === 'none' ) {
		help = __(
			'Shared without images. Networks that need one (Instagram, Pinterest…) are skipped.',
			'social-relay-for-postiz'
		);
	} else if ( mode === 'choose' ) {
		help = ids.length
			? __(
					'Only these images, in this order.',
					'social-relay-for-postiz'
			  )
			: __(
					'Pick the images for this post in the media library.',
					'social-relay-for-postiz'
			  );
	} else if ( ! ids.length && profileMode !== 'none' ) {
		help = __(
			'This post has no images for the profile setting yet.',
			'social-relay-for-postiz'
		);
	}

	const picker = ( label, variant ) => (
		<MediaUploadCheck>
			<MediaUpload
				onSelect={ choose }
				allowedTypes={ [ 'image' ] }
				multiple
				gallery
				value={ mode === 'choose' ? own : ids }
				render={ ( { open } ) => (
					<Button variant={ variant } size="compact" onClick={ open }>
						{ label }
					</Button>
				) }
			/>
		</MediaUploadCheck>
	);

	return (
		<>
			<ToggleGroupControl
				label={ __( 'Images', 'social-relay-for-postiz' ) }
				hideLabelFromVision
				value={ mode }
				onChange={ setMode }
				isBlock
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			>
				<ToggleGroupControlOption
					value="profile"
					label={ __( 'Profile', 'social-relay-for-postiz' ) }
				/>
				<ToggleGroupControlOption
					value="choose"
					label={ __( 'Choose', 'social-relay-for-postiz' ) }
				/>
				<ToggleGroupControlOption
					value="none"
					label={ __( 'None', 'social-relay-for-postiz' ) }
				/>
			</ToggleGroupControl>

			{ ids.length > 0 && (
				<ul className="socialrelaycvi-images">
					{ ids.map( ( id, index ) => {
						const item = media[ index ];
						const src =
							item?.media_details?.sizes?.thumbnail?.source_url ||
							item?.source_url;
						return (
							<li
								key={ id }
								className="socialrelaycvi-images__item"
							>
								{ src ? (
									<img
										src={ src }
										alt={ item?.alt_text || '' }
									/>
								) : (
									<span className="socialrelaycvi-images__empty" />
								) }
								{ mode === 'choose' && (
									<Button
										className="socialrelaycvi-images__remove"
										icon="no-alt"
										size="small"
										label={ __(
											'Remove image',
											'social-relay-for-postiz'
										) }
										onClick={ () => {
											const next = own.filter(
												( value ) => value !== id
											);
											setMeta( META.images, next );
										} }
									/>
								) }
							</li>
						);
					} ) }
				</ul>
			) }

			{ mode === 'choose' && (
				<div className="socialrelaycvi-images__actions">
					{ picker(
						ids.length
							? __( 'Edit selection', 'social-relay-for-postiz' )
							: __( 'Select images', 'social-relay-for-postiz' ),
						'secondary'
					) }
				</div>
			) }

			<p className="description socialrelaycvi-timing__help">{ help }</p>

			{ fewer.length > 0 && (
				<p className="description socialrelaycvi-timing__help">
					{ fewer
						.map( ( { label, max } ) =>
							sprintf(
								/* translators: 1: network name, 2: number of images. */
								__(
									'%1$s gets the first %2$d.',
									'social-relay-for-postiz'
								),
								label,
								max
							)
						)
						.join( ' ' ) }
				</p>
			) }
		</>
	);
}
