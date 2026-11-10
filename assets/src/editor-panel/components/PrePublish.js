import { useSelect } from '@wordpress/data';
import { PluginPrePublishPanel, store as editorStore } from '@wordpress/editor';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	channelLabel,
	channelsById,
	config,
	ICON,
	META,
	passesConditions,
	timingLabel,
} from '../config';
import { effectiveChannels, usePostMeta } from '../hooks';

/**
 * Summary in the pre-publish checks: channels and timing.
 *
 * @return {Element|null} Panel.
 */
export default function PrePublish() {
	const [ meta ] = usePostMeta();
	const terms = useSelect( ( select ) => {
		const editor = select( editorStore );
		return [
			...( editor.getEditedPostAttribute( 'categories' ) || [] ),
			...( editor.getEditedPostAttribute( 'tags' ) || [] ),
		];
	}, [] );

	if ( ! config.configured || ! config.profile.enabled ) {
		return null;
	}

	const enabled = meta[ META.enabled ] !== false;
	const names = effectiveChannels( meta )
		.map( ( id ) => channelLabel( channelsById[ id ] ) )
		.filter( Boolean );

	return (
		<PluginPrePublishPanel
			title={ __( 'Social networks', 'social-relay-for-postiz' ) }
			initialOpen
			icon={ ICON }
		>
			{ enabled && names.length > 0 && ! passesConditions( terms ) && (
				<p>
					{ __(
						'This post will not be shared: its categories or tags do not match the profile conditions.',
						'social-relay-for-postiz'
					) }
				</p>
			) }
			{ ( ! enabled || ! names.length ) && (
				<p>
					{ __(
						'This post will not be shared.',
						'social-relay-for-postiz'
					) }
				</p>
			) }
			{ enabled && names.length > 0 && passesConditions( terms ) && (
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
					<p className="description">{ timingLabel( meta ) }</p>
				</>
			) }
		</PluginPrePublishPanel>
	);
}
