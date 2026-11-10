/**
 * Channel name followed by its network.
 *
 * @param {Object} props         Props.
 * @param {Object} props.channel Channel from the config.
 * @return {Element|null} Badge.
 */
export default function ChannelBadge( { channel } ) {
	if ( ! channel ) {
		return null;
	}

	return (
		<span style={ { fontSize: '12px' } }>
			{ channel.name }
			<span style={ { color: '#646970' } }>
				{ ' · ' + channel.identifier }
			</span>
		</span>
	);
}
