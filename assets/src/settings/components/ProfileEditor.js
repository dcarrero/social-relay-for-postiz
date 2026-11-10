import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

import { ChannelLabel } from '../../common/ChannelAvatar';
import TemplateField from '../../common/TemplateField';

import { IMAGE_MODES, MODES } from '../config';
import Conditions from './Conditions';
import NetworkOptions from './NetworkOptions';

/**
 * Editor of one publishing profile.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.postType Post type name.
 * @param {Object}   props.profile  Profile.
 * @param {Object}   props.settings Settings from the REST route.
 * @param {Function} props.onChange Receives the new profile.
 * @return {Element} Editor.
 */
export default function ProfileEditor( {
	postType,
	profile,
	settings,
	onChange,
} ) {
	const info = settings.postTypes[ postType ];
	const update = ( changes ) => onChange( { ...profile, ...changes } );
	const channels = settings.channels.filter(
		( channel ) => ! channel.disabled
	);
	const selected = channels.filter( ( channel ) =>
		profile.channels.includes( channel.id )
	);

	// Ids saved in the profile that Postiz no longer has (removed or reconnected).
	const known = settings.channels.map( ( channel ) => channel.id );
	const gone = known.length
		? profile.channels.filter( ( id ) => ! known.includes( id ) )
		: [];
	const removeGone = () => {
		const options = { ...profile.channel_options };
		gone.forEach( ( id ) => delete options[ id ] );
		update( {
			channels: profile.channels.filter( ( id ) => known.includes( id ) ),
			channel_options: options,
		} );
	};

	const toggleChannel = ( id, checked ) =>
		update( {
			channels: checked
				? [ ...profile.channels, id ]
				: profile.channels.filter( ( item ) => item !== id ),
		} );

	return (
		<>
			<Card>
				<CardBody>
					<ToggleControl
						label={ sprintf(
							/* translators: %s: post type label, e.g. Posts. */
							__(
								'Share %s when they are published',
								'social-relay-for-postiz'
							),
							info.label.toLowerCase()
						) }
						checked={ profile.enabled }
						onChange={ ( enabled ) => update( { enabled } ) }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h3>{ __( 'Channels', 'social-relay-for-postiz' ) }</h3>
				</CardHeader>
				<CardBody>
					{ ! channels.length && (
						<p>
							{ __(
								'No channels in Postiz yet. Add them in Postiz and reload this page.',
								'social-relay-for-postiz'
							) }
						</p>
					) }
					<div className="socialrelaycvi-settings__grid">
						{ channels.map( ( channel ) => (
							<CheckboxControl
								key={ channel.id }
								label={ <ChannelLabel channel={ channel } /> }
								checked={ profile.channels.includes(
									channel.id
								) }
								onChange={ ( checked ) =>
									toggleChannel( channel.id, checked )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
					{ gone.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %d: number of channels. */
								_n(
									'%d channel of this profile no longer exists in Postiz and is skipped.',
									'%d channels of this profile no longer exist in Postiz and are skipped.',
									gone.length,
									'social-relay-for-postiz'
								),
								gone.length
							) }{ ' ' }
							<Button variant="link" onClick={ removeGone }>
								{ __(
									'Remove them',
									'social-relay-for-postiz'
								) }
							</Button>
						</Notice>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h3>{ __( 'Message', 'social-relay-for-postiz' ) }</h3>
				</CardHeader>
				<CardBody>
					<TemplateField
						label={ __(
							'Message template',
							'social-relay-for-postiz'
						) }
						value={ profile.template }
						rows={ 5 }
						help={ __(
							'Use “Insert tag” for the post data. Filters such as |limit:100, |words:30, |upper, |hashtag or |default:text go inside a tag. A line with --- starts a new thread part.',
							'social-relay-for-postiz'
						) }
						onChange={ ( template ) => update( { template } ) }
					/>
				</CardBody>
				{ selected.length > 0 && (
					<>
						<CardBody>
							<p className="description">
								{ __(
									'Optional. A network without its own template uses the common one.',
									'social-relay-for-postiz'
								) }
							</p>
						</CardBody>
						{ selected.map( ( channel ) => (
							<NetworkOptions
								key={ channel.id }
								channel={ channel }
								options={
									profile.channel_options[ channel.id ] || {
										template: '',
										settings: {},
									}
								}
								fields={ settings.fields[ channel.identifier ] }
								limit={ settings.limits[ channel.identifier ] }
								onChange={ ( options ) =>
									update( {
										channel_options: {
											...profile.channel_options,
											[ channel.id ]: options,
										},
									} )
								}
							/>
						) ) }
					</>
				) }
			</Card>

			<Card>
				<CardHeader>
					<h3>{ __( 'When', 'social-relay-for-postiz' ) }</h3>
				</CardHeader>
				<CardBody>
					<SelectControl
						label={ __( 'When', 'social-relay-for-postiz' ) }
						hideLabelFromVision
						value={ profile.mode }
						options={ MODES }
						onChange={ ( mode ) => update( { mode } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ profile.mode === 'schedule' && (
						<TextControl
							label={ __(
								'Delay in minutes',
								'social-relay-for-postiz'
							) }
							type="number"
							min={ 0 }
							max={ 43200 }
							value={ String( profile.delay_minutes ) }
							onChange={ ( value ) =>
								update( {
									delay_minutes: parseInt( value || '0', 10 ),
								} )
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
					<SelectControl
						label={ __( 'Images', 'social-relay-for-postiz' ) }
						value={ profile.images }
						options={ IMAGE_MODES }
						onChange={ ( images ) => update( { images } ) }
						help={ __(
							'Up to 10 images; each network gets as many as it accepts (X 4, LinkedIn 9, Instagram 10, Pinterest 1). The editor can choose others for each post.',
							'social-relay-for-postiz'
						) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={ __(
							'Allow sharing again on update (when requested from the editor)',
							'social-relay-for-postiz'
						) }
						checked={ profile.share_on_update }
						onChange={ ( value ) =>
							update( { share_on_update: value } )
						}
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h3>{ __( 'Conditions', 'social-relay-for-postiz' ) }</h3>
				</CardHeader>
				<CardBody>
					<Conditions
						profile={ profile }
						postType={ info }
						categories={ settings.categories }
						update={ update }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h3>
						{ __( 'Links and tags', 'social-relay-for-postiz' ) }
					</h3>
				</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __(
							'Add UTM parameters to {url} (utm_source = network, utm_medium = social)',
							'social-relay-for-postiz'
						) }
						checked={ profile.utm }
						onChange={ ( utm ) => update( { utm } ) }
						__nextHasNoMarginBottom
					/>
					{ profile.utm && (
						<TextControl
							label="utm_campaign"
							help={ __(
								'Accepts tags, e.g. {slug}.',
								'social-relay-for-postiz'
							) }
							value={ profile.utm_campaign }
							onChange={ ( value ) =>
								update( { utm_campaign: value } )
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
					<ToggleControl
						label={ __(
							'Shorten links with Postiz',
							'social-relay-for-postiz'
						) }
						checked={ profile.short_link }
						onChange={ ( value ) =>
							update( { short_link: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Tag the posts in Postiz with the post categories',
							'social-relay-for-postiz'
						) }
						checked={ profile.postiz_tags }
						onChange={ ( value ) =>
							update( { postiz_tags: value } )
						}
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>
		</>
	);
}
