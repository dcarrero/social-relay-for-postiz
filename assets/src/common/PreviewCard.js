import { __, _n, sprintf } from '@wordpress/i18n';

import ChannelAvatar from './ChannelAvatar';
import { network } from './networks';
import './preview-card.css';

const URL_PATTERN = /https?:\/\/[^\s]+/;

/**
 * Host of the first link in a text, without "www.".
 *
 * @param {string} text Text.
 * @return {string} Host, or '' when there is no link.
 */
function firstHost( text ) {
	const match = text.match( URL_PATTERN );
	if ( ! match ) {
		return '';
	}
	try {
		return new URL( match[ 0 ] ).host.replace( /^www\./, '' );
	} catch ( e ) {
		return '';
	}
}

/**
 * How a message will look on its network: channel, text (one bubble per thread
 * part), link card and character count against the network limit.
 *
 * @param {Object} props           Props.
 * @param {Object} props.item      Preview item: name, identifier, parts, limit.
 * @param {string} props.linkTitle Title shown in the link card (the post title).
 * @return {Element} Card.
 */
export default function PreviewCard( { item, linkTitle } ) {
	const net = network( item.identifier );
	const longest = Math.max( ...item.parts.map( ( part ) => part.length ) );
	const over = item.limit && longest > item.limit;
	const host = firstHost( item.parts.join( '\n' ) );

	return (
		<div className="socialrelaycvi-card">
			<div className="socialrelaycvi-card__head">
				<ChannelAvatar channel={ item } size={ 32 } />
				<span className="socialrelaycvi-card__who">
					<strong>{ item.name }</strong>
					<span>{ net.label }</span>
				</span>
				<span
					className={ `socialrelaycvi-card__count${
						over ? ' is-over' : ''
					}` }
				>
					{ item.limit ? `${ longest } / ${ item.limit }` : longest }
				</span>
			</div>

			{ item.parts.map( ( part, index ) => (
				<div
					key={ index }
					className={ `socialrelaycvi-card__text${
						index > 0 ? ' is-reply' : ''
					}` }
				>
					{ part }
				</div>
			) ) }

			{ host && (
				<div className="socialrelaycvi-card__link">
					<span>{ host }</span>
					{ linkTitle && <strong>{ linkTitle }</strong> }
				</div>
			) }

			{ item.parts.length > 1 && (
				<p className="socialrelaycvi-card__note">
					{ sprintf(
						/* translators: %d: number of parts in a thread. */
						_n(
							'Thread of %d part.',
							'Thread of %d parts.',
							item.parts.length,
							'social-relay-for-postiz'
						),
						item.parts.length
					) }
				</p>
			) }

			{ over && (
				<p className="socialrelaycvi-card__note is-over">
					{ __(
						'Longer than the network allows: it will be shortened.',
						'social-relay-for-postiz'
					) }
				</p>
			) }
		</div>
	);
}
