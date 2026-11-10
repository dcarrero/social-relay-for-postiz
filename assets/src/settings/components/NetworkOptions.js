import {
	CheckboxControl,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Template and network settings for one channel of a profile.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.channel  Channel.
 * @param {Object}   props.options  { template, settings } for this channel.
 * @param {Object[]} props.fields   Network setting fields (from ProviderRules).
 * @param {?number}  props.limit    Character limit of the network.
 * @param {Function} props.onChange Receives the new options.
 * @return {Element} Panel.
 */
export default function NetworkOptions( {
	channel,
	options,
	fields,
	limit,
	onChange,
} ) {
	const settings = options.settings || {};
	const customized =
		!! options.template?.trim() || Object.keys( settings ).length > 0;

	const setSetting = ( key, value ) =>
		onChange( { ...options, settings: { ...settings, [ key ]: value } } );

	const title =
		`${ channel.name } · ${ channel.identifier }` +
		( customized
			? ` · ${ __( 'customized', 'social-relay-for-postiz' ) }`
			: '' );

	return (
		<PanelBody title={ title } initialOpen={ false }>
			<TextareaControl
				label={
					limit
						? sprintf(
								/* translators: %d: character limit of the network. */
								__(
									'Template for this network (limit: %d characters)',
									'social-relay-for-postiz'
								),
								limit
						  )
						: __(
								'Template for this network',
								'social-relay-for-postiz'
						  )
				}
				placeholder={ __(
					'Empty = common template',
					'social-relay-for-postiz'
				) }
				value={ options.template || '' }
				rows={ 3 }
				onChange={ ( template ) =>
					onChange( { ...options, template } )
				}
				__nextHasNoMarginBottom
			/>

			{ Object.entries( fields || {} ).map( ( [ key, field ] ) => {
				if ( field.type === 'bool' ) {
					return (
						<CheckboxControl
							key={ key }
							label={ field.label }
							checked={ settings[ key ] === true }
							onChange={ ( value ) => setSetting( key, value ) }
							__nextHasNoMarginBottom
						/>
					);
				}

				if ( field.type === 'select' ) {
					return (
						<SelectControl
							key={ key }
							label={ field.label }
							value={ settings[ key ] ?? '' }
							options={ [
								{
									value: '',
									label: __(
										'Default',
										'social-relay-for-postiz'
									),
								},
								...Object.entries( field.options || {} ).map(
									( [ value, label ] ) => ( { value, label } )
								),
							] }
							onChange={ ( value ) => setSetting( key, value ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					);
				}

				return (
					<TextControl
						key={ key }
						label={ field.label + ( field.required ? ' *' : '' ) }
						value={ settings[ key ] ?? '' }
						onChange={ ( value ) => setSetting( key, value ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				);
			} ) }
		</PanelBody>
	);
}
