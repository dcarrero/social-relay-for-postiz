import { Button, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { config, META } from '../config';
import { effectiveChannels } from '../hooks';
import ChannelBadge from './ChannelBadge';

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

	const toggle = ( id, checked ) => {
		const next = checked
			? [ ...selected, id ]
			: selected.filter( ( item ) => item !== id );
		setMeta( META.channels, next );
	};

	return (
		<>
			{ config.channels.map( ( channel ) => (
				<CheckboxControl
					key={ channel.id }
					label={ <ChannelBadge channel={ channel } /> }
					checked={ selected.includes( channel.id ) }
					disabled={ channel.disabled }
					onChange={ ( checked ) => toggle( channel.id, checked ) }
					__nextHasNoMarginBottom
				/>
			) ) }
			{ custom ? (
				<Button
					variant="link"
					onClick={ () => setMeta( META.channels, [] ) }
				>
					{ __(
						'Use the profile channels',
						'social-relay-for-postiz'
					) }
				</Button>
			) : (
				<p className="description">
					{ __(
						'Channels from the publishing profile.',
						'social-relay-for-postiz'
					) }
				</p>
			) }
		</>
	);
}
