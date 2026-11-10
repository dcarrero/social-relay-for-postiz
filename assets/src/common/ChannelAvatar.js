import { useState } from '@wordpress/element';

import { network } from './networks';
import './channel.css';

/**
 * Channel picture from Postiz, or the first letter on the network colour when there is none.
 *
 * @param {Object} props         Props.
 * @param {Object} props.channel Channel: name, identifier, picture.
 * @param {number} props.size    Size in pixels.
 * @return {Element} Avatar.
 */
export default function ChannelAvatar( { channel, size = 28 } ) {
	const [ broken, setBroken ] = useState( false );
	const net = network( channel.identifier );
	const style = { width: size, height: size, flexBasis: size };

	if ( channel.picture && ! broken ) {
		return (
			<img
				className="socialrelaycvi-avatar"
				src={ channel.picture }
				alt=""
				style={ style }
				onError={ () => setBroken( true ) }
			/>
		);
	}

	return (
		<span
			className="socialrelaycvi-avatar"
			style={ { ...style, background: net.color } }
			aria-hidden="true"
		>
			{ ( channel.name || '?' ).charAt( 0 ).toUpperCase() }
		</span>
	);
}

/**
 * Avatar, bold name and network (or a note) on two lines.
 *
 * @param {Object} props         Props.
 * @param {Object} props.channel Channel.
 * @param {string} props.note    Replaces the network line (e.g. "Disconnected in Postiz").
 * @return {Element} Label.
 */
export function ChannelLabel( { channel, note } ) {
	return (
		<span className="socialrelaycvi-channel">
			<ChannelAvatar channel={ channel } />
			<span className="socialrelaycvi-channel__text">
				<strong>{ channel.name }</strong>
				<span className={ note ? 'is-note' : '' }>
					{ note || network( channel.identifier ).label }
				</span>
			</span>
		</span>
	);
}
