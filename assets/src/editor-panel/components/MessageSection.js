import { Button, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { channelsById, META } from '../config';
import { effectiveChannels } from '../hooks';

/**
 * Text for all networks plus an optional text per network.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.meta    Post meta.
 * @param {Function} props.setMeta Meta setter.
 * @return {Element} Section.
 */
export default function MessageSection( { meta, setMeta } ) {
	const [ open, setOpen ] = useState( null );
	const overrides = meta[ META.overrides ] || {};
	const selected = effectiveChannels( meta ).filter(
		( id ) => channelsById[ id ]
	);

	const setOverride = ( id, text ) => {
		const next = { ...overrides };
		if ( text ) {
			next[ id ] = text;
		} else {
			delete next[ id ];
		}
		setMeta( META.overrides, next );
	};

	return (
		<>
			<TextareaControl
				label={ __(
					'Text for all networks',
					'social-relay-for-postiz'
				) }
				help={ __(
					'Empty = profile template. Tags like {title} and {url} work here too.',
					'social-relay-for-postiz'
				) }
				value={ meta[ META.message ] || '' }
				rows={ 4 }
				onChange={ ( value ) => setMeta( META.message, value ) }
				__nextHasNoMarginBottom
			/>

			{ selected.length > 0 && (
				<p style={ { margin: '12px 0 4px', fontWeight: 600 } }>
					{ __( 'Text per network', 'social-relay-for-postiz' ) }
				</p>
			) }

			{ selected.map( ( id ) => {
				const isOpen = open === id;
				const suffix = overrides[ id ]
					? ' · ' + __( 'custom', 'social-relay-for-postiz' )
					: '';

				return (
					<div
						key={ id }
						style={ {
							borderTop: '1px solid #f0f0f1',
							padding: '6px 0',
						} }
					>
						<Button
							variant="link"
							aria-expanded={ isOpen }
							onClick={ () => setOpen( isOpen ? null : id ) }
						>
							{ channelsById[ id ].name + suffix }
						</Button>
						{ isOpen && (
							<TextareaControl
								value={ overrides[ id ] || '' }
								placeholder={ __(
									'Empty = text for all networks',
									'social-relay-for-postiz'
								) }
								rows={ 4 }
								onChange={ ( value ) =>
									setOverride( id, value )
								}
								__nextHasNoMarginBottom
							/>
						) }
					</div>
				);
			} ) }
		</>
	);
}
