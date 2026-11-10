import {
	Button,
	DateTimePicker,
	Dropdown,
	// Stable in practice and used across core; still exported with the prefix in WordPress 7.1.
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { getSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

import { config, META, modeLabel, postTiming, siteDateLabel } from '../config';

const HELP = {
	now: () =>
		__(
			'Postiz publishes right away when the post is published.',
			'social-relay-for-postiz'
		),
	slot: () =>
		__(
			'Postiz picks the next free time in the posting schedule of each channel.',
			'social-relay-for-postiz'
		),
	draft: () =>
		__(
			'Created as a draft in Postiz, to review and publish there.',
			'social-relay-for-postiz'
		),
};

/**
 * When this post goes out: the profile timing or its own (now, a date, next free slot, draft).
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.meta    Post meta.
 * @param {Function} props.setMeta Meta setter.
 * @return {Element} Section.
 */
export default function TimingSection( { meta, setMeta } ) {
	const { mode, publishAt, own } = postTiming( meta );
	const { formats } = getSettings();

	// The profile choice is stored as '' so later profile changes still apply.
	const setMode = ( value ) =>
		setMeta( META.mode, value === config.profile.mode ? '' : value );

	let scheduleHelp = __(
		'Without a date: right after publishing.',
		'social-relay-for-postiz'
	);
	if ( publishAt ) {
		scheduleHelp = __(
			'Postiz publishes on this date. A past date means right away.',
			'social-relay-for-postiz'
		);
	} else if ( config.profile.delayMinutes ) {
		scheduleHelp = sprintf(
			/* translators: %d: minutes. */
			__(
				'Without a date: %d minutes after publishing, as in the profile.',
				'social-relay-for-postiz'
			),
			config.profile.delayMinutes
		);
	}

	return (
		<>
			<ToggleGroupControl
				label={ __( 'When', 'social-relay-for-postiz' ) }
				hideLabelFromVision
				value={ mode }
				onChange={ setMode }
				isBlock
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			>
				<ToggleGroupControlOption
					value="now"
					label={ __( 'Now', 'social-relay-for-postiz' ) }
				/>
				<ToggleGroupControlOption
					value="schedule"
					label={ __( 'Date', 'social-relay-for-postiz' ) }
				/>
				<ToggleGroupControlOption
					value="slot"
					label={ __( 'Next free', 'social-relay-for-postiz' ) }
				/>
				<ToggleGroupControlOption
					value="draft"
					label={ __( 'Draft', 'social-relay-for-postiz' ) }
				/>
			</ToggleGroupControl>

			{ mode === 'schedule' && (
				<div className="socialrelaycvi-timing__date">
					<Dropdown
						popoverProps={ { placement: 'left-start' } }
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								variant="secondary"
								size="compact"
								onClick={ onToggle }
								aria-expanded={ isOpen }
							>
								{ publishAt
									? siteDateLabel( publishAt )
									: __(
											'Pick a date',
											'social-relay-for-postiz'
									  ) }
							</Button>
						) }
						renderContent={ () => (
							<div className="socialrelaycvi-timing__picker">
								<DateTimePicker
									currentDate={ publishAt || undefined }
									onChange={ ( value ) =>
										setMeta( META.publishAt, value || '' )
									}
									// PHP time formats with a or A are 12-hour (g:i a).
									is12Hour={ /a/i.test( formats.time ) }
								/>
							</div>
						) }
					/>
					{ publishAt && (
						<Button
							variant="tertiary"
							size="compact"
							onClick={ () => setMeta( META.publishAt, '' ) }
						>
							{ __( 'Clear', 'social-relay-for-postiz' ) }
						</Button>
					) }
				</div>
			) }

			<p className="description socialrelaycvi-timing__help">
				{ mode === 'schedule' ? scheduleHelp : HELP[ mode ]() }{ ' ' }
				{ own ? (
					<Button
						variant="link"
						onClick={ () => {
							setMeta( META.mode, '' );
							setMeta( META.publishAt, '' );
						} }
					>
						{ sprintf(
							/* translators: %s: timing of the profile. */
							__(
								'Back to the profile (%s)',
								'social-relay-for-postiz'
							),
							modeLabel( config.profile.mode )
						) }
					</Button>
				) : (
					__( '(Profile setting.)', 'social-relay-for-postiz' )
				) }
			</p>
		</>
	);
}
