/**
 * Social Relay for Postiz: block editor panel.
 *
 * Plain JavaScript on top of the packages WordPress already loads in the editor,
 * so the plugin has no build step. Data comes from window.socialRelayCvi
 * (see src/Admin/EditorPanel.php) and from the socialrelaycvi/v1 REST routes.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editor || ! config ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem, PluginPrePublishPanel } = wp.editor;
	const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;
	const {
		PanelBody,
		ToggleControl,
		CheckboxControl,
		TextareaControl,
		Button,
		Notice,
		Spinner,
		ExternalLink,
	} = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const apiFetch = wp.apiFetch;
	const { __, _n, sprintf } = wp.i18n;

	// The text domain is written literally in every call so that `wp i18n make-pot` can extract the strings.
	const ICON = 'share';
	const META = {
		enabled: '_socialrelaycvi_enabled',
		message: '_socialrelaycvi_message',
		overrides: '_socialrelaycvi_overrides',
		channels: '_socialrelaycvi_channels',
		reshare: '_socialrelaycvi_reshare',
	};

	const channelsById = {};
	( config.channels || [] ).forEach( ( channel ) => {
		channelsById[ channel.id ] = channel;
	} );

	function modeLabel( mode ) {
		switch ( mode ) {
			case 'schedule':
				return sprintf(
					/* translators: %d: minutes of delay. */
					__( 'Scheduled %d minutes after publishing', 'social-relay-for-postiz' ),
					config.profile.delayMinutes
				);
			case 'slot':
				return __( 'Next free slot in Postiz', 'social-relay-for-postiz' );
			case 'draft':
				return __( 'Draft in Postiz', 'social-relay-for-postiz' );
			default:
				return __( 'Immediately', 'social-relay-for-postiz' );
		}
	}

	function statusLabel( status ) {
		const labels = {
			queued: __( 'Queued', 'social-relay-for-postiz' ),
			published: __( 'Published', 'social-relay-for-postiz' ),
			error: __( 'Error', 'social-relay-for-postiz' ),
			draft: __( 'Draft in Postiz', 'social-relay-for-postiz' ),
			cancelled: __( 'Cancelled', 'social-relay-for-postiz' ),
			pending: __( 'Pending', 'social-relay-for-postiz' ),
		};
		return labels[ status ] || status;
	}

	const STATUS_COLORS = {
		queued: '#3858e9',
		published: '#007a1f',
		error: '#cc1818',
		draft: '#646970',
		cancelled: '#646970',
	};

	/**
	 * Post meta of the post being edited, plus a setter.
	 */
	function usePostMeta() {
		const meta = useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );
		return [ meta, ( key, value ) => editPost( { meta: { [ key ]: value } } ) ];
	}

	/**
	 * Post id, status and a counter that increases after each completed save.
	 */
	function usePostState() {
		const state = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return {
				postId: editor.getCurrentPostId(),
				status: editor.getCurrentPostAttribute( 'status' ),
				saving: editor.isSavingPost() && ! editor.isAutosavingPost(),
			};
		}, [] );

		const [ saves, setSaves ] = useState( 0 );
		const wasSaving = useRef( false );

		useEffect( () => {
			if ( wasSaving.current && ! state.saving ) {
				setSaves( ( n ) => n + 1 );
			}
			wasSaving.current = state.saving;
		}, [ state.saving ] );

		return { ...state, saves };
	}

	/**
	 * Channels that will be used: the post's own list, or the profile's.
	 *
	 * @param {Object} meta Post meta.
	 */
	function effectiveChannels( meta ) {
		const own = meta[ META.channels ];
		return Array.isArray( own ) && own.length ? own : config.profile.channels;
	}

	function ChannelBadge( { channel } ) {
		return el(
			'span',
			{ style: { fontSize: '12px' } },
			channel ? channel.name : '',
			channel ? el( 'span', { style: { color: '#646970' } }, ' · ' + channel.identifier ) : null
		);
	}

	function ChannelsSection( { meta, setMeta } ) {
		const selected = effectiveChannels( meta );
		const custom = Array.isArray( meta[ META.channels ] ) && meta[ META.channels ].length > 0;

		const toggle = ( id, checked ) => {
			const next = checked ? [ ...selected, id ] : selected.filter( ( item ) => item !== id );
			setMeta( META.channels, next );
		};

		if ( ! config.channels.length ) {
			return el( 'p', null, __( 'No channels in Postiz yet.', 'social-relay-for-postiz' ) );
		}

		return el(
			Fragment,
			null,
			config.channels.map( ( channel ) =>
				el( CheckboxControl, {
					key: channel.id,
					label: el( ChannelBadge, { channel } ),
					checked: selected.includes( channel.id ),
					disabled: channel.disabled,
					onChange: ( checked ) => toggle( channel.id, checked ),
					__nextHasNoMarginBottom: true,
				} )
			),
			custom
				? el(
						Button,
						{
							variant: 'link',
							onClick: () => setMeta( META.channels, [] ),
						},
						__( 'Use the profile channels', 'social-relay-for-postiz' )
				  )
				: el( 'p', { className: 'description' }, __( 'Channels from the publishing profile.', 'social-relay-for-postiz' ) )
		);
	}

	function MessageSection( { meta, setMeta } ) {
		const overrides = meta[ META.overrides ] || {};
		const selected = effectiveChannels( meta ).filter( ( id ) => channelsById[ id ] );
		const [ open, setOpen ] = useState( null );

		const setOverride = ( id, text ) => {
			const next = { ...overrides };
			if ( text ) {
				next[ id ] = text;
			} else {
				delete next[ id ];
			}
			setMeta( META.overrides, next );
		};

		return el(
			Fragment,
			null,
			el( TextareaControl, {
				label: __( 'Text for all networks', 'social-relay-for-postiz' ),
				help: __( 'Empty = profile template. Tags like {title} and {url} work here too.', 'social-relay-for-postiz' ),
				value: meta[ META.message ] || '',
				rows: 4,
				onChange: ( value ) => setMeta( META.message, value ),
				__nextHasNoMarginBottom: true,
			} ),
			selected.length
				? el( 'p', { style: { margin: '12px 0 4px', fontWeight: 600 } }, __( 'Text per network', 'social-relay-for-postiz' ) )
				: null,
			selected.map( ( id ) => {
				const channel = channelsById[ id ];
				const isOpen = open === id;
				const hasText = !! overrides[ id ];
				return el(
					'div',
					{ key: id, style: { borderTop: '1px solid #f0f0f1', padding: '6px 0' } },
					el(
						Button,
						{
							variant: 'link',
							onClick: () => setOpen( isOpen ? null : id ),
							'aria-expanded': isOpen,
						},
						channel.name + ( hasText ? ' · ' + __( 'custom', 'social-relay-for-postiz' ) : '' )
					),
					isOpen
						? el( TextareaControl, {
								value: overrides[ id ] || '',
								placeholder: __( 'Empty = text for all networks', 'social-relay-for-postiz' ),
								rows: 4,
								onChange: ( value ) => setOverride( id, value ),
								__nextHasNoMarginBottom: true,
						  } )
						: null
				);
			} )
		);
	}

	function PreviewSection( { postId, saves } ) {
		const [ data, setData ] = useState( null );
		const [ error, setError ] = useState( null );
		const [ loading, setLoading ] = useState( false );

		const load = () => {
			if ( ! postId ) {
				return;
			}
			setLoading( true );
			apiFetch( { path: '/' + config.namespace + '/posts/' + postId + '/preview' } )
				.then( ( response ) => {
					setData( response );
					setError( null );
				} )
				.catch( ( err ) => setError( err.message || String( err ) ) )
				.finally( () => setLoading( false ) );
		};

		useEffect( load, [ postId, saves ] );

		if ( error ) {
			return el( Notice, { status: 'error', isDismissible: false }, error );
		}
		if ( ! data ) {
			return loading ? el( Spinner ) : null;
		}

		return el(
			Fragment,
			null,
			! data.passes_conditions
				? el(
						Notice,
						{ status: 'warning', isDismissible: false },
						__( 'This post does not match the profile conditions and will not be shared.', 'social-relay-for-postiz' )
				  )
				: null,
			data.items.map( ( item ) => {
				const text = item.parts.join( '\n\n— — —\n\n' );
				const longest = Math.max( ...item.parts.map( ( part ) => part.length ) );
				const over = item.limit && longest > item.limit;
				return el(
					'div',
					{ key: item.integration_id, style: { marginBottom: '12px' } },
					el(
						'div',
						{ style: { display: 'flex', justifyContent: 'space-between', fontSize: '12px', fontWeight: 600 } },
						el( 'span', null, item.name ),
						el(
							'span',
							{ style: { color: over ? '#cc1818' : '#007a1f' } },
							item.limit ? longest + ' / ' + item.limit : String( longest )
						)
					),
					el(
						'div',
						{
							style: {
								whiteSpace: 'pre-wrap',
								overflowWrap: 'anywhere',
								fontSize: '12px',
								border: '1px solid #dcdcde',
								borderRadius: '4px',
								padding: '8px',
								marginTop: '4px',
								background: '#fff',
							},
						},
						text
					),
					item.parts.length > 1
						? el(
								'p',
								{ className: 'description' },
								sprintf(
									/* translators: %d: number of parts in a thread. */
									_n( 'Thread of %d part.', 'Thread of %d parts.', item.parts.length, 'social-relay-for-postiz' ),
									item.parts.length
								)
						  )
						: null
				);
			} ),
			data.skipped.map( ( skip ) =>
				el(
					Notice,
					{ key: skip.integration_id, status: 'warning', isDismissible: false },
					skip.reason
				)
			),
			el(
				'p',
				{ className: 'description' },
				__( 'Based on the last saved version.', 'social-relay-for-postiz' ),
				' ',
				el( Button, { variant: 'link', onClick: load, disabled: loading }, __( 'Refresh', 'social-relay-for-postiz' ) )
			)
		);
	}

	function StatusSection( { postId, saves } ) {
		const [ rows, setRows ] = useState( null );

		const load = () => {
			if ( ! postId ) {
				return;
			}
			apiFetch( { path: '/' + config.namespace + '/posts/' + postId + '/shares' } )
				.then( setRows )
				.catch( () => setRows( [] ) );
		};

		useEffect( load, [ postId, saves ] );

		// Refresh every 30 s while something is still on its way.
		const pending = ( rows || [] ).some( ( row ) => row.status === 'queued' );
		useEffect( () => {
			if ( ! pending ) {
				return undefined;
			}
			const timer = setInterval( load, 30000 );
			return () => clearInterval( timer );
		}, [ pending, postId ] );

		if ( rows === null ) {
			return el( Spinner );
		}
		if ( ! rows.length ) {
			return el( 'p', { className: 'description' }, __( 'Nothing sent to Postiz yet.', 'social-relay-for-postiz' ) );
		}

		// Latest row per channel.
		const latest = [];
		const seen = {};
		rows.forEach( ( row ) => {
			if ( ! seen[ row.integration_id ] ) {
				seen[ row.integration_id ] = true;
				latest.push( row );
			}
		} );

		return el(
			Fragment,
			null,
			latest.map( ( row ) =>
				el(
					'div',
					{ key: row.id, style: { borderTop: '1px solid #f0f0f1', padding: '8px 0' } },
					el(
						'div',
						{ style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '8px' } },
						el( ChannelBadge, { channel: channelsById[ row.integration_id ] } ),
						el(
							'span',
							{
								style: {
									fontSize: '11px',
									fontWeight: 600,
									color: STATUS_COLORS[ row.status ] || '#1e1e1e',
								},
							},
							statusLabel( row.status )
						)
					),
					row.release_url
						? el( ExternalLink, { href: row.release_url }, __( 'View post', 'social-relay-for-postiz' ) )
						: null,
					row.error
						? el( 'p', { style: { color: '#cc1818', fontSize: '12px', margin: '4px 0 0' } }, row.error )
						: null
				)
			),
			el(
				'p',
				{ className: 'description' },
				el( Button, { variant: 'link', onClick: load }, __( 'Refresh', 'social-relay-for-postiz' ) ),
				config.canManage ? ' · ' : null,
				config.canManage ? el( ExternalLink, { href: config.logUrl }, __( 'Log', 'social-relay-for-postiz' ) ) : null
			)
		);
	}

	function NotReady() {
		if ( ! config.configured ) {
			return el(
				PanelBody,
				null,
				el( 'p', null, __( 'Postiz is not connected yet.', 'social-relay-for-postiz' ) ),
				config.canManage
					? el( ExternalLink, { href: config.settingsUrl }, __( 'Connect Postiz', 'social-relay-for-postiz' ) )
					: null
			);
		}
		return el(
			PanelBody,
			null,
			el( 'p', null, __( 'Sharing is off for this post type.', 'social-relay-for-postiz' ) ),
			config.canManage
				? el( ExternalLink, { href: config.settingsUrl }, __( 'Set up a publishing profile', 'social-relay-for-postiz' ) )
				: null
		);
	}

	function Sidebar() {
		const [ meta, setMeta ] = usePostMeta();
		const { postId, status, saves } = usePostState();
		const enabled = meta[ META.enabled ] !== false;
		const published = status === 'publish';

		if ( ! config.configured || ! config.profile.enabled ) {
			return el( NotReady );
		}

		return el(
			Fragment,
			null,
			el(
				PanelBody,
				null,
				el( ToggleControl, {
					label: published ? __( 'Shared when published', 'social-relay-for-postiz' ) : __( 'Share when publishing', 'social-relay-for-postiz' ),
					checked: enabled,
					onChange: ( value ) => setMeta( META.enabled, value ),
					help: modeLabel( config.profile.mode ),
					__nextHasNoMarginBottom: true,
				} ),
				published && config.profile.shareOnUpdate
					? el( ToggleControl, {
							label: __( 'Share again on the next update', 'social-relay-for-postiz' ),
							checked: !! meta[ META.reshare ],
							onChange: ( value ) => setMeta( META.reshare, value ),
							__nextHasNoMarginBottom: true,
					  } )
					: null
			),
			enabled
				? el(
						Fragment,
						null,
						published
							? el(
									PanelBody,
									{ title: __( 'Status', 'social-relay-for-postiz' ), initialOpen: true },
									el( StatusSection, { postId, saves } )
							  )
							: null,
						el(
							PanelBody,
							{ title: __( 'Channels', 'social-relay-for-postiz' ), initialOpen: ! published },
							el( ChannelsSection, { meta, setMeta } )
						),
						el(
							PanelBody,
							{ title: __( 'Message', 'social-relay-for-postiz' ), initialOpen: ! published },
							el( MessageSection, { meta, setMeta } )
						),
						el(
							PanelBody,
							{ title: __( 'Preview', 'social-relay-for-postiz' ), initialOpen: false },
							el( PreviewSection, { postId, saves } )
						)
				  )
				: null
		);
	}

	function PrePublish() {
		const [ meta ] = usePostMeta();

		if ( ! config.configured || ! config.profile.enabled ) {
			return null;
		}

		const enabled = meta[ META.enabled ] !== false;
		const names = effectiveChannels( meta )
			.map( ( id ) => channelsById[ id ] && channelsById[ id ].name )
			.filter( Boolean );

		return el(
			PluginPrePublishPanel,
			{ title: __( 'Social networks', 'social-relay-for-postiz' ), initialOpen: true, icon: ICON },
			! enabled || ! names.length
				? el( 'p', null, __( 'This post will not be shared.', 'social-relay-for-postiz' ) )
				: el(
						Fragment,
						null,
						el(
							'p',
							null,
							sprintf(
								/* translators: %d: number of channels. */
								_n( 'Will be sent to Postiz for %d channel:', 'Will be sent to Postiz for %d channels:', names.length, 'social-relay-for-postiz' ),
								names.length
							)
						),
						el( 'p', { style: { fontWeight: 600 } }, names.join( ', ' ) ),
						el( 'p', { className: 'description' }, modeLabel( config.profile.mode ) )
				  )
		);
	}

	registerPlugin( 'socialrelaycvi', {
		icon: ICON,
		render: () =>
			el(
				Fragment,
				null,
				el(
					PluginSidebarMoreMenuItem,
					{ target: 'socialrelaycvi-sidebar', icon: ICON },
					__( 'Social Relay', 'social-relay-for-postiz' )
				),
				el(
					PluginSidebar,
					{ name: 'socialrelaycvi-sidebar', title: __( 'Social networks', 'social-relay-for-postiz' ), icon: ICON },
					el( Sidebar )
				),
				el( PrePublish )
			),
	} );
} )( window.wp, window.socialRelayCvi );
