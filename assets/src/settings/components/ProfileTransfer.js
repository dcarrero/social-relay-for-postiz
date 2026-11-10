import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FormFileUpload,
	Modal,
	Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { config } from '../config';

/**
 * Builds the file name: social-relay-profiles-example.com-2026-11-10.json.
 *
 * @return {string} File name.
 */
function fileName() {
	const host = window.location.hostname.replace( /[^a-z0-9.-]/gi, '' );
	const day = new Date().toISOString().slice( 0, 10 );
	return `social-relay-profiles-${ host }-${ day }.json`;
}

/**
 * Hands a JSON value to the browser as a download.
 *
 * @param {Object} data Value.
 */
function download( data ) {
	const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], {
		type: 'application/json',
	} );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = fileName();
	document.body.appendChild( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

/**
 * What the import did, as notice lines.
 *
 * @param {Object} report    Report from the REST route.
 * @param {Object} postTypes Post types of this site.
 * @return {string[]} Lines.
 */
function reportLines( report, postTypes ) {
	const label = ( type ) => postTypes[ type ]?.label || type;
	const lines = [
		sprintf(
			/* translators: %s: list of post type names. */
			__( 'Imported: %s.', 'social-relay-for-postiz' ),
			report.imported.map( label ).join( ', ' )
		),
	];

	if ( report.skipped_types.length ) {
		lines.push(
			sprintf(
				/* translators: %s: list of post type names. */
				__(
					'Not on this site, skipped: %s.',
					'social-relay-for-postiz'
				),
				report.skipped_types.join( ', ' )
			)
		);
	}
	if ( report.missing_terms.length ) {
		lines.push(
			sprintf(
				/* translators: %s: list of category or tag names. */
				__(
					'Categories or tags this site does not have, left out of the conditions: %s.',
					'social-relay-for-postiz'
				),
				report.missing_terms.join( ', ' )
			)
		);
	}
	if ( report.unknown_channels ) {
		lines.push(
			sprintf(
				/* translators: %d: number of channels. */
				_n(
					'%d channel is not in your Postiz instance. Review the profiles to remove it.',
					'%d channels are not in your Postiz instance. Review the profiles to remove them.',
					report.unknown_channels,
					'social-relay-for-postiz'
				),
				report.unknown_channels
			)
		);
	}

	return lines;
}

/**
 * Export and import of the publishing profiles.
 *
 * @param {Object}   props             Props.
 * @param {Object}   props.settings    Settings from the REST route.
 * @param {Function} props.setSettings Replaces the settings after importing.
 * @return {Element} Card.
 */
export default function ProfileTransfer( { settings, setSettings } ) {
	const [ busy, setBusy ] = useState( false );
	const [ pending, setPending ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const exportProfiles = () => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path: `/${ config.namespace }/admin/profiles/export` } )
			.then( download )
			.catch( ( err ) =>
				setNotice( { status: 'error', lines: [ err.message ] } )
			)
			.finally( () => setBusy( false ) );
	};

	const readFile = ( event ) => {
		const file = event.target.files?.[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}

		setNotice( null );
		file.text()
			.then( ( text ) => {
				const data = JSON.parse( text );
				const types = Object.keys( data?.profiles || {} );
				if ( ! types.length ) {
					throw new Error();
				}
				setPending( { data, types, name: file.name } );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					lines: [
						__(
							'This file is not an export of Social Relay profiles.',
							'social-relay-for-postiz'
						),
					],
				} )
			);
	};

	const importProfiles = () => {
		setBusy( true );
		apiFetch( {
			path: `/${ config.namespace }/admin/profiles/import`,
			method: 'POST',
			data: { data: pending.data },
		} )
			.then( ( result ) => {
				setSettings( result.settings );
				setNotice( {
					status:
						result.report.unknown_channels ||
						result.report.missing_terms.length
							? 'warning'
							: 'success',
					lines: reportLines(
						result.report,
						result.settings.postTypes
					),
				} );
			} )
			.catch( ( err ) =>
				setNotice( { status: 'error', lines: [ err.message ] } )
			)
			.finally( () => {
				setBusy( false );
				setPending( null );
			} );
	};

	const label = ( type ) => settings.postTypes[ type ]?.label || type;

	return (
		<Card>
			<CardHeader>
				<h3>
					{ __( 'Publishing profiles', 'social-relay-for-postiz' ) }
				</h3>
			</CardHeader>
			<CardBody>
				<p className="description">
					{ __(
						'Copy your profiles to another site: channels, messages, timing and conditions. The connection to Postiz is not included.',
						'social-relay-for-postiz'
					) }
				</p>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.lines.map( ( line ) => (
							<p key={ line }>{ line }</p>
						) ) }
					</Notice>
				) }
				<div className="socialrelaycvi-settings__buttons">
					<Button
						variant="secondary"
						onClick={ exportProfiles }
						isBusy={ busy && ! pending }
						disabled={ busy }
					>
						{ __( 'Export profiles', 'social-relay-for-postiz' ) }
					</Button>
					<FormFileUpload
						accept="application/json,.json"
						onChange={ readFile }
						render={ ( { openFileDialog } ) => (
							<Button
								variant="secondary"
								onClick={ openFileDialog }
								disabled={ busy }
							>
								{ __(
									'Import profiles…',
									'social-relay-for-postiz'
								) }
							</Button>
						) }
					/>
				</div>
			</CardBody>
			{ pending && (
				<Modal
					title={ __(
						'Import profiles?',
						'social-relay-for-postiz'
					) }
					onRequestClose={ () => ! busy && setPending( null ) }
					size="small"
				>
					<p>
						{ sprintf(
							/* translators: 1: file name, 2: list of post type names. */
							__(
								'“%1$s” replaces the profiles of: %2$s.',
								'social-relay-for-postiz'
							),
							pending.name,
							pending.types.map( label ).join( ', ' )
						) }
					</p>
					<p>
						{ __(
							'Other post types keep their profile. Posts already shared are not touched.',
							'social-relay-for-postiz'
						) }
					</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button
							variant="tertiary"
							onClick={ () => setPending( null ) }
							disabled={ busy }
						>
							{ __( 'Cancel', 'social-relay-for-postiz' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ importProfiles }
							isBusy={ busy }
							disabled={ busy }
						>
							{ __( 'Import', 'social-relay-for-postiz' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</Card>
	);
}
