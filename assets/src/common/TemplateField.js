import { TextareaControl } from '@wordpress/components';
import { useRef } from '@wordpress/element';

import TagMenu, { insertAtCursor } from './TagMenu';

/**
 * Template textarea with the "Insert tag" menu under it.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.value    Template.
 * @param {Function} props.onChange Change handler.
 * @return {Element} Field.
 */
export default function TemplateField( { value, onChange, ...props } ) {
	const ref = useRef();

	return (
		<div className="socialrelaycvi-template-field">
			<TextareaControl
				ref={ ref }
				value={ value }
				onChange={ onChange }
				className="socialrelaycvi-settings__code"
				__nextHasNoMarginBottom
				{ ...props }
			/>
			<div className="socialrelaycvi-template-field__tools">
				<TagMenu
					onInsert={ ( tag ) =>
						insertAtCursor( ref, value, tag, onChange )
					}
				/>
			</div>
		</div>
	);
}
