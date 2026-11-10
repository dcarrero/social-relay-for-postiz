import {
	Button,
	CheckboxControl,
	ExternalLink,
	Notice,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

import { ChannelLabel } from '../../common/ChannelAvatar';
import { channelsById, config, META, NO_CHANNELS } from '../config';
import { effectiveChannels } from '../hooks';

/**
 * Channels for this post. An empty list in the meta means "use the profile's".
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.meta    Post meta.
 * @param {Function} props.setMeta Meta setter.
 * @return {Element} Section.
 */
export default function ChannelsSection( { meta, setMeta } ) {
	const selected = effectiveChannels( meta );
	const custom =
		Array.isArray( meta[ META.channels ] ) &&
		meta[ META.channels ].length > 0;
	// Ids that were picked once but are gone from Postiz (removed or reconnected).
	const gone = selected.filter(
		( id ) => id !== NO_CHANNELS && ! channelsById[ id ]
	);

	if ( ! config.channels.length ) {
		return (
			<p>
				{ __(
					'No channels in Postiz yet.',
					'social-relay-for-postiz'
				) }
			</p>
		);
	}

	const usable = config.channels.filter( ( channel ) => ! channel.disabled );
	const live = selected.filter( ( id ) => channelsById[ id ] );
	const setChannels = ( ids ) => setMeta( META.channels, ids );
	const toggle = ( id, checked ) => {
		const next = checked
			? [ ...live, id ]
			: live.filter( ( item ) => item !== id );
		setChannels( next.length ? next : [ NO_CHANNELS ] );
	};

	return (
		<div className="socialrelaycvi-channels">
			<div className="socialrelaycvi-panel__bar">
				<span className="description">
					{ custom
						? __(
								'Chosen for this post',
								'social-relay-for-postiz'
						  )
						: __(
								'From the publishing profile',
								'social-relay-for-postiz'
						  ) }
				</span>
				<span>
					<Button
						variant="link"
						onClick={ () =>
							setChannels(
								usable.map( ( channel ) => channel.id )
							)
						}
					>
						{ __( 'All', 'social-relay-for-postiz' ) }
					</Button>
					{ ' · ' }
					<Button
						variant="link"
						onClick={ () => setChannels( [ NO_CHANNELS ] ) }
					>
						{ __( 'None', 'social-relay-for-postiz' ) }
					</Button>
				</span>
			</div>

			{ config.channels.map( ( channel ) => (
				<CheckboxControl
					key={ channel.id }
					className="socialrelaycvi-channels__item"
					label={
						<ChannelLabel
							channel={ channel }
							note={
								channel.disabled
									? __(
											'Disconnected in Postiz',
											'social-relay-for-postiz'
									  )
									: ''
							}
						/>
					}
					checked={ live.includes( channel.id ) }
					disabled={ channel.disabled }
					onChange={ ( checked ) => toggle( channel.id, checked ) }
					__nextHasNoMarginBottom
				/>
			) ) }

			{ gone.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of channels. */
						_n(
							'%d selected channel no longer exists in Postiz and will be skipped.',
							'%d selected channels no longer exist in Postiz and will be skipped.',
							gone.length,
							'social-relay-for-postiz'
						),
						gone.length
					) }{ ' ' }
					{ custom ? (
						<Button
							variant="link"
							onClick={ () =>
								setChannels(
									live.length ? live : [ NO_CHANNELS ]
								)
							}
						>
							{ __( 'Remove them', 'social-relay-for-postiz' ) }
						</Button>
					) : (
						config.canManage && (
							<ExternalLink href={ config.settingsUrl }>
								{ __(
									'Review the profile',
									'social-relay-for-postiz'
								) }
							</ExternalLink>
						)
					) }
				</Notice>
			) }

			{ custom && (
				<Button variant="link" onClick={ () => setChannels( [] ) }>
					{ __(
						'Use the profile channels',
						'social-relay-for-postiz'
					) }
				</Button>
			) }
		</div>
	);
}
