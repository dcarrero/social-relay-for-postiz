import { PluginPrePublishPanel } from '@wordpress/editor';
import { __, _n, sprintf } from '@wordpress/i18n';

import { channelsById, config, ICON, META, modeLabel } from '../config';
import { effectiveChannels, usePostMeta } from '../hooks';

/**
 * Summary in the pre-publish checks: channels and timing.
 *
 * @return {Element|null} Panel.
 */
export default function PrePublish() {
	const [ meta ] = usePostMeta();

	if ( ! config.configured || ! config.profile.enabled ) {
		return null;
	}

	const enabled = meta[ META.enabled ] !== false;
	const names = effectiveChannels( meta )
		.map( ( id ) => channelsById[ id ]?.name )
		.filter( Boolean );

	return (
		<PluginPrePublishPanel
			title={ __( 'Social networks', 'social-relay-for-postiz' ) }
			initialOpen
			icon={ ICON }
		>
			{ ! enabled || ! names.length ? (
				<p>
					{ __(
						'This post will not be shared.',
						'social-relay-for-postiz'
					) }
				</p>
			) : (
				<>
					<p>
						{ sprintf(
							/* translators: %d: number of channels. */
							_n(
								'Will be sent to Postiz for %d channel:',
								'Will be sent to Postiz for %d channels:',
								names.length,
								'social-relay-for-postiz'
							),
							names.length
						) }
					</p>
					<p style={ { fontWeight: 600 } }>{ names.join( ', ' ) }</p>
					<p className="description">
						{ modeLabel( config.profile.mode ) }
					</p>
				</>
			) }
		</PluginPrePublishPanel>
	);
}
