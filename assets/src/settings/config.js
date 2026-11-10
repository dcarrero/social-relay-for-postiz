/**
 * Data printed by src/Admin/SettingsPage.php.
 */
import { __ } from '@wordpress/i18n';

export const config = window.socialRelayCviAdmin || {};

export const MODES = [
	{
		value: 'now',
		label: __( 'Publish immediately', 'social-relay-for-postiz' ),
	},
	{
		value: 'schedule',
		label: __( 'Schedule with a delay', 'social-relay-for-postiz' ),
	},
	{
		value: 'slot',
		label: __( 'Next free slot in Postiz', 'social-relay-for-postiz' ),
	},
	{
		value: 'draft',
		label: __( 'Create as draft in Postiz', 'social-relay-for-postiz' ),
	},
];

export const IMAGE_MODES = [
	{
		value: 'featured',
		label: __( 'Featured image', 'social-relay-for-postiz' ),
	},
	{
		value: 'content',
		label: __(
			'Featured image and images in the post',
			'social-relay-for-postiz'
		),
	},
	{
		value: 'none',
		label: __( 'No images', 'social-relay-for-postiz' ),
	},
];
