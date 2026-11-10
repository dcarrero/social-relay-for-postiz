import { ExternalLink, PanelBody, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { channelsById, config, META, timingLabel } from '../config';
import { effectiveChannels, usePostMeta, usePostState } from '../hooks';
import ChannelsSection from './ChannelsSection';
import ImagesSection from './ImagesSection';
import MessageSection from './MessageSection';
import PreviewSection from './PreviewSection';
import StatusSection from './StatusSection';
import TimingSection from './TimingSection';

/**
 * Shown when Postiz is not connected or the post type has no enabled profile.
 *
 * @return {Element} Panel.
 */
function NotReady() {
	const connected = config.configured;

	return (
		<PanelBody>
			<p>
				{ connected
					? __(
							'Sharing is off for this post type.',
							'social-relay-for-postiz'
					  )
					: __(
							'Postiz is not connected yet.',
							'social-relay-for-postiz'
					  ) }
			</p>
			{ config.canManage && (
				<ExternalLink href={ config.settingsUrl }>
					{ connected
						? __(
								'Set up a publishing profile',
								'social-relay-for-postiz'
						  )
						: __( 'Connect Postiz', 'social-relay-for-postiz' ) }
				</ExternalLink>
			) }
		</PanelBody>
	);
}

/**
 * Content of the "Social networks" sidebar.
 *
 * @return {Element} Sidebar content.
 */
export default function Sidebar() {
	const [ meta, setMeta ] = usePostMeta();
	const { postId, status, saves } = usePostState();

	if ( ! config.configured || ! config.profile.enabled ) {
		return <NotReady />;
	}

	const enabled = meta[ META.enabled ] !== false;
	const published = status === 'publish';
	const count = effectiveChannels( meta ).filter(
		( id ) => channelsById[ id ] && ! channelsById[ id ].disabled
	).length;
	const summary = sprintf(
		/* translators: 1: profile name (post type), 2: channels used, 3: channels available, 4: timing. */
		__(
			'Profile “%1$s” · %2$d of %3$d channels · %4$s',
			'social-relay-for-postiz'
		),
		config.profile.label,
		count,
		config.channels.filter( ( channel ) => ! channel.disabled ).length,
		timingLabel( meta )
	);

	return (
		<>
			<PanelBody className="socialrelaycvi-sidebar__share">
				<ToggleControl
					label={ __( 'Share this post', 'social-relay-for-postiz' ) }
					checked={ enabled }
					onChange={ ( value ) => setMeta( META.enabled, value ) }
					help={ summary }
					__nextHasNoMarginBottom
				/>
				{ published && config.profile.shareOnUpdate && (
					<ToggleControl
						label={ __(
							'Share again on the next update',
							'social-relay-for-postiz'
						) }
						checked={ !! meta[ META.reshare ] }
						onChange={ ( value ) => setMeta( META.reshare, value ) }
						__nextHasNoMarginBottom
					/>
				) }
			</PanelBody>

			{ /* The history stays visible even when sharing is turned off afterwards. */ }
			{ published && (
				<PanelBody title={ __( 'Status', 'social-relay-for-postiz' ) }>
					<StatusSection
						postId={ postId }
						saves={ saves }
						meta={ meta }
					/>
				</PanelBody>
			) }

			{ enabled && (
				<>
					<PanelBody
						title={ __( 'Channels', 'social-relay-for-postiz' ) }
						initialOpen={ ! published }
					>
						<ChannelsSection meta={ meta } setMeta={ setMeta } />
					</PanelBody>
					<PanelBody
						title={ __( 'When', 'social-relay-for-postiz' ) }
						initialOpen={ ! published }
					>
						<TimingSection meta={ meta } setMeta={ setMeta } />
					</PanelBody>
					<PanelBody
						title={ __( 'Message', 'social-relay-for-postiz' ) }
						initialOpen={ ! published }
					>
						<MessageSection meta={ meta } setMeta={ setMeta } />
					</PanelBody>
					<PanelBody
						title={ __( 'Images', 'social-relay-for-postiz' ) }
						initialOpen={ false }
					>
						<ImagesSection meta={ meta } setMeta={ setMeta } />
					</PanelBody>
					<PanelBody
						title={ __( 'Preview', 'social-relay-for-postiz' ) }
						initialOpen={ false }
					>
						<PreviewSection postId={ postId } saves={ saves } />
					</PanelBody>
				</>
			) }
		</>
	);
}
