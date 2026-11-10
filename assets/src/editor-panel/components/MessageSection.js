import { TabPanel, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { network } from '../../common/networks';
import { channelLabel, channelsById, config, META } from '../config';
import { effectiveChannels } from '../hooks';
import './message-section.css';

const ALL = '__all';

/**
 * Keeps tab titles short.
 *
 * @param {string} name Channel name.
 * @return {string} Name, cut at 14 characters.
 */
function shorten( name ) {
	return name.length > 14 ? name.slice( 0, 13 ) + '…' : name;
}

/**
 * Text for all networks plus an optional text per network, one tab each.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.meta    Post meta.
 * @param {Function} props.setMeta Meta setter.
 * @return {Element} Section.
 */
export default function MessageSection( { meta, setMeta } ) {
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

	// The network is only added when two selected channels share a name.
	const names = selected.map( ( id ) => channelsById[ id ].name );
	const tabTitle = ( id ) => {
		const { name, identifier } = channelsById[ id ];
		const shared = names.filter( ( other ) => other === name ).length > 1;
		return (
			shorten( name ) +
			( shared ? ' · ' + network( identifier ).label : '' ) +
			( overrides[ id ] ? ' •' : '' )
		);
	};

	const tabs = [
		{ name: ALL, title: __( 'All', 'social-relay-for-postiz' ) },
		...selected.map( ( id ) => ( {
			name: id,
			title: tabTitle( id ),
		} ) ),
	];

	return (
		<TabPanel className="socialrelaycvi-message" tabs={ tabs }>
			{ ( tab ) => {
				if ( tab.name === ALL ) {
					return (
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
							rows={ 5 }
							onChange={ ( value ) =>
								setMeta( META.message, value )
							}
							__nextHasNoMarginBottom
						/>
					);
				}

				const channel = channelsById[ tab.name ];
				const limit = config.limits[ channel.identifier ];

				return (
					<TextareaControl
						label={ channelLabel( channel ) }
						help={
							limit
								? sprintf(
										/* translators: %d: character limit of the network. */
										__(
											'Empty = text for all networks. Limit: %d characters; check the count in Preview.',
											'social-relay-for-postiz'
										),
										limit
								  )
								: __(
										'Empty = text for all networks.',
										'social-relay-for-postiz'
								  )
						}
						value={ overrides[ tab.name ] || '' }
						rows={ 5 }
						onChange={ ( value ) => setOverride( tab.name, value ) }
						__nextHasNoMarginBottom
					/>
				);
			} }
		</TabPanel>
	);
}
