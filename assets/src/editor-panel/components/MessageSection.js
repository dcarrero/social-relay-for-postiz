import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	TabPanel,
	TextareaControl,
} from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { network } from '../../common/networks';
import { channelLabel, channelsById, config, META } from '../config';
import { effectiveChannels } from '../hooks';
import './message-section.css';

const ALL = '__all';

const TAGS = [
	'{title}',
	'{excerpt}',
	'{url}',
	'{shortlink}',
	'{author}',
	'{site_name}',
	'{categories}',
	'{hashtags:post_tag}',
	'{excerpt|limit:180}',
];

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
 * Textarea plus "Insert tag" and "Reset". The placeholder shows what is used when it stays empty.
 *
 * @param {Object}   props             Props.
 * @param {string}   props.label       Label.
 * @param {string}   props.help        Help text.
 * @param {string}   props.value       Text.
 * @param {string}   props.placeholder Text used when empty.
 * @param {Function} props.onChange    Change handler.
 * @return {Element} Editor.
 */
function MessageEditor( { label, help, value, placeholder, onChange } ) {
	const ref = useRef();

	const insert = ( tag ) => {
		const field = ref.current;
		const start = field ? field.selectionStart : value.length;
		const end = field ? field.selectionEnd : value.length;
		onChange( value.slice( 0, start ) + tag + value.slice( end ) );
		window.requestAnimationFrame( () => {
			if ( field ) {
				field.focus();
				field.selectionStart = field.selectionEnd = start + tag.length;
			}
		} );
	};

	return (
		<>
			<TextareaControl
				ref={ ref }
				label={ label }
				help={ help }
				value={ value }
				placeholder={ placeholder }
				rows={ 6 }
				onChange={ onChange }
				__nextHasNoMarginBottom
			/>
			<div className="socialrelaycvi-message__tools">
				<DropdownMenu
					icon={ null }
					text={
						'{ } ' + __( 'Insert tag', 'social-relay-for-postiz' )
					}
					label={ __( 'Insert tag', 'social-relay-for-postiz' ) }
					toggleProps={ { variant: 'secondary', size: 'compact' } }
				>
					{ ( { onClose } ) => (
						<MenuGroup>
							{ TAGS.map( ( tag ) => (
								<MenuItem
									key={ tag }
									onClick={ () => {
										insert( tag );
										onClose();
									} }
								>
									<code>{ tag }</code>
								</MenuItem>
							) ) }
						</MenuGroup>
					) }
				</DropdownMenu>
				{ value && (
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => onChange( '' ) }
					>
						{ __( 'Reset', 'social-relay-for-postiz' ) }
					</Button>
				) }
			</div>
		</>
	);
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
	const message = meta[ META.message ] || '';
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
		{
			name: ALL,
			title:
				__( 'All', 'social-relay-for-postiz' ) +
				( message ? ' •' : '' ),
		},
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
						<MessageEditor
							label={ __(
								'Text for all networks',
								'social-relay-for-postiz'
							) }
							help={ __(
								'Empty = profile template, shown in grey. • marks a custom text.',
								'social-relay-for-postiz'
							) }
							value={ message }
							placeholder={ config.profile.template }
							onChange={ ( value ) =>
								setMeta( META.message, value )
							}
						/>
					);
				}

				const channel = channelsById[ tab.name ];
				const limit = config.limits[ channel.identifier ];

				return (
					<MessageEditor
						key={ tab.name }
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
						placeholder={
							message ||
							config.profile.templates[ tab.name ] ||
							config.profile.template
						}
						onChange={ ( value ) => setOverride( tab.name, value ) }
					/>
				);
			} }
		</TabPanel>
	);
}
