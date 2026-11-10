import { __ } from '@wordpress/i18n';

/**
 * A label with a dot that screen readers announce with words.
 *
 * @param {Object}  props        Props.
 * @param {string}  props.label  Visible label.
 * @param {boolean} props.marked Whether to show the dot.
 * @param {string}  props.note   What the dot means, read by screen readers.
 * @return {Element} Label.
 */
export default function Marked( { label, marked, note } ) {
	return (
		<>
			{ label }
			{ marked && (
				<>
					<span aria-hidden="true"> •</span>
					<span className="screen-reader-text">
						{ ` (${
							note ||
							__( 'customized', 'social-relay-for-postiz' )
						})` }
					</span>
				</>
			) }
		</>
	);
}
