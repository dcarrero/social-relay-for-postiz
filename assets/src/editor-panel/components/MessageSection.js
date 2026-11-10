import apiFetch from '@wordpress/api-fetch';
import { Button, TabPanel, TextareaControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { network } from '../../common/networks';
import TagMenu, { insertAtCursor } from '../../common/TagMenu';
import { channelLabel, channelsById, config, META } from '../config';
import { effectiveChannels } from '../hooks';
import './message-section.css';

const ALL = '__all';

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
 * @param {string}   props.channel     Channel id the text is for, '' for all networks.
 * @return {Element} Editor.
 */
function MessageEditor( {
	label,
	help,
	value,
	placeholder,
	onChange,
	channel,
} ) {
	const ref = useRef();
	const [ thinking, setThinking ] = useState( false );
	const [ previous, setPrevious ] = useState( null );
	const postId = useSelect(
		( select ) => select( editorStore ).getCurrentPostId(),
		[]
	);
	const { createErrorNotice } = useDispatch( noticesStore );

	// Only fills the field: a person reviews the text before anything is sent.
	const suggest = () => {
		setThinking( true );
		apiFetch( {
			path: `/${ config.namespace }/posts/${ postId }/suggest`,
			method: 'POST',
			data: { channel },
		} )
			.then( ( response ) => {
				setPrevious( value );
				onChange( response.text );
			} )
			.catch( ( err ) =>
				createErrorNotice( err.message || String( err ), {
					type: 'snackbar',
				} )
			)
			.finally( () => setThinking( false ) );
	};

	const insert = ( tag ) => insertAtCursor( ref, value, tag, onChange );

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
				{ config.ai && (
					<Button
						variant="secondary"
						size="compact"
						icon="superhero-alt"
						onClick={ suggest }
						isBusy={ thinking }
						disabled={ thinking || ! postId }
					>
						{ __( 'Suggest with AI', 'social-relay-for-postiz' ) }
					</Button>
				) }
				<TagMenu onInsert={ insert } />
				{ previous !== null && (
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => {
							onChange( previous );
							setPrevious( null );
						} }
					>
						{ __( 'Undo', 'social-relay-for-postiz' ) }
					</Button>
				) }
				{ value && previous === null && (
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => onChange( '' ) }
					>
						{ __( 'Reset', 'social-relay-for-postiz' ) }
					</Button>
				) }
			</div>
			{ config.ai && (
				<p className="description socialrelaycvi-message__ai">
					{ __(
						'AI uses the saved post and your provider in Settings → Connectors. Review the text before publishing.',
						'social-relay-for-postiz'
					) }
				</p>
			) }
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
							channel=""
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
						channel={ tab.name }
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
