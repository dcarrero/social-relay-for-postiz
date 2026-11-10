/**
 * Social Relay for Postiz: log screen.
 *
 * Built with the components WordPress already loads (no extra libraries), so the
 * bundle stays small. Data comes from socialrelaycvi/v1/admin/log.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
	__experimentalConfirmDialog as ConfirmDialog, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { dateI18n, getSettings } from '@wordpress/date';
import {
	createRoot,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import './style.css';

const config = window.socialRelayCviAdmin || {};
const channels = config.channels || {};
const PER_PAGE = 25;

const LEVELS = [
	{ value: '', label: __( 'All levels', 'social-relay-for-postiz' ) },
	{ value: 'info', label: __( 'Info', 'social-relay-for-postiz' ) },
	{ value: 'error', label: __( 'Error', 'social-relay-for-postiz' ) },
];

function levelLabel( level ) {
	return LEVELS.find( ( item ) => item.value === level )?.label ?? level;
}

function Row( { item } ) {
	return (
		<tr>
			<td className="column-date">
				{ dateI18n(
					getSettings().formats.datetimeAbbreviated,
					item.created_at
				) }
			</td>
			<td>
				<span
					className={ `socialrelaycvi-log__level is-${ item.level }` }
				>
					{ levelLabel( item.level ) }
				</span>
			</td>
			<td>{ item.action }</td>
			<td>
				{ item.edit_link && (
					<a href={ item.edit_link }>
						{ item.post_title ||
							__( '(no title)', 'social-relay-for-postiz' ) }
					</a>
				) }
			</td>
			<td>{ channels[ item.integration_id ] || item.integration_id }</td>
			<td className="socialrelaycvi-log__message">{ item.message }</td>
		</tr>
	);
}

function LogApp() {
	const [ page, setPage ] = useState( 1 );
	const [ level, setLevel ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ data, setData ] = useState( { items: [], total: 0, pages: 0 } );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ confirming, setConfirming ] = useState( false );
	const [ stats, setStats ] = useState( null );

	const applySearch = useDebounce( ( value ) => {
		setQuery( value );
		setPage( 1 );
	}, 400 );

	const load = useCallback( () => {
		setLoading( true );
		apiFetch( {
			path: addQueryArgs( `/${ config.namespace }/admin/log`, {
				page,
				per_page: PER_PAGE,
				search: query,
				level,
			} ),
		} )
			.then( ( response ) => {
				setData( response );
				setError( '' );
			} )
			.catch( ( err ) => setError( err.message || String( err ) ) )
			.finally( () => setLoading( false ) );
	}, [ page, query, level ] );

	useEffect( load, [ load ] );

	const loadStats = useCallback( () => {
		apiFetch( { path: `/${ config.namespace }/admin/stats` } )
			.then( setStats )
			.catch( () => setStats( null ) );
	}, [] );

	useEffect( loadStats, [ loadStats ] );

	const refresh = () => {
		load();
		loadStats();
	};

	const clear = () => {
		setConfirming( false );
		apiFetch( {
			path: `/${ config.namespace }/admin/log`,
			method: 'DELETE',
		} ).then( () => ( page === 1 ? load() : setPage( 1 ) ) );
	};

	const columns = [
		__( 'Date', 'social-relay-for-postiz' ),
		__( 'Level', 'social-relay-for-postiz' ),
		__( 'Action', 'social-relay-for-postiz' ),
		__( 'Post', 'social-relay-for-postiz' ),
		__( 'Channel', 'social-relay-for-postiz' ),
		__( 'Message', 'social-relay-for-postiz' ),
	];

	return (
		<div className="socialrelaycvi-log">
			<div className="socialrelaycvi-log__header">
				<h1>{ __( 'Log', 'social-relay-for-postiz' ) }</h1>
				<div className="socialrelaycvi-log__actions">
					<Button variant="secondary" onClick={ refresh }>
						{ __( 'Refresh', 'social-relay-for-postiz' ) }
					</Button>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => setConfirming( true ) }
						disabled={ ! data.total }
					>
						{ __( 'Clear log', 'social-relay-for-postiz' ) }
					</Button>
				</div>
			</div>

			{ stats && (
				<div className="socialrelaycvi-log__stats">
					{ [
						[
							stats.published,
							__(
								'Published (30 days)',
								'social-relay-for-postiz'
							),
							'is-success',
						],
						[
							stats.queued,
							__( 'Queued', 'social-relay-for-postiz' ),
							'is-info',
						],
						[
							stats.errors,
							__( 'Errors (30 days)', 'social-relay-for-postiz' ),
							'is-error',
						],
						[
							`${ stats.lastHour } / ${ stats.limit || 90 }`,
							__(
								'Requests to Postiz in the last hour',
								'social-relay-for-postiz'
							),
							'',
						],
					].map( ( [ value, label, tone ] ) => (
						<div
							key={ label }
							className={ `socialrelaycvi-log__stat ${ tone }` }
						>
							<strong>{ value }</strong>
							<span>{ label }</span>
						</div>
					) ) }
				</div>
			) }

			<div className="socialrelaycvi-log__filters">
				<SearchControl
					label={ __( 'Search messages', 'social-relay-for-postiz' ) }
					value={ search }
					onChange={ ( value ) => {
						setSearch( value );
						applySearch( value );
					} }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( 'Level', 'social-relay-for-postiz' ) }
					hideLabelFromVision
					value={ level }
					options={ LEVELS }
					onChange={ ( value ) => {
						setLevel( value );
						setPage( 1 );
					} }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{ loading && <Spinner /> }
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<table className="widefat striped socialrelaycvi-log__table">
				<thead>
					<tr>
						{ columns.map( ( column ) => (
							<th key={ column } scope="col">
								{ column }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ ! loading && ! data.items.length && (
						<tr>
							<td colSpan={ columns.length }>
								{ __(
									'Nothing logged yet.',
									'social-relay-for-postiz'
								) }
							</td>
						</tr>
					) }
					{ data.items.map( ( item ) => (
						<Row key={ item.id } item={ item } />
					) ) }
				</tbody>
			</table>

			{ data.pages > 1 && (
				<div className="socialrelaycvi-log__pagination">
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'social-relay-for-postiz' ) }
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: total pages, 3: total entries. */
							__(
								'Page %1$d of %2$d (%3$d entries)',
								'social-relay-for-postiz'
							),
							page,
							data.pages,
							data.total
						) }
					</span>
					<Button
						variant="secondary"
						disabled={ page >= data.pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'social-relay-for-postiz' ) }
					</Button>
				</div>
			) }

			<ConfirmDialog
				isOpen={ confirming }
				onConfirm={ clear }
				onCancel={ () => setConfirming( false ) }
				confirmButtonText={ __(
					'Clear log',
					'social-relay-for-postiz'
				) }
			>
				{ __(
					'Delete every log entry? This cannot be undone.',
					'social-relay-for-postiz'
				) }
			</ConfirmDialog>
		</div>
	);
}

const root = document.getElementById( 'socialrelaycvi-log' );
if ( root ) {
	createRoot( root ).render( <LogApp /> );
}
