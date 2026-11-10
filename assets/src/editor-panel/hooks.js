/**
 * Hooks shared by the panel components.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useRef, useState } from '@wordpress/element';

import { config, META } from './config';

/**
 * Post meta of the post being edited, plus a setter for one key.
 *
 * @return {Array} [ meta, setMeta( key, value ) ].
 */
export function usePostMeta() {
	const meta = useSelect(
		( select ) =>
			select( editorStore ).getEditedPostAttribute( 'meta' ) || {},
		[]
	);
	const { editPost } = useDispatch( editorStore );

	return [ meta, ( key, value ) => editPost( { meta: { [ key ]: value } } ) ];
}

/**
 * Post id, saved status and a counter that increases after each completed save.
 *
 * @return {{postId: number, status: string, saving: boolean, saves: number}} State.
 */
export function usePostState() {
	const state = useSelect( ( select ) => {
		const editor = select( editorStore );
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
			setSaves( ( count ) => count + 1 );
		}
		wasSaving.current = state.saving;
	}, [ state.saving ] );

	return { ...state, saves };
}

/**
 * Channels that will be used: the post's own list, or the profile's.
 *
 * @param {Object} meta Post meta.
 * @return {string[]} Channel ids.
 */
export function effectiveChannels( meta ) {
	const own = meta[ META.channels ];
	return Array.isArray( own ) && own.length ? own : config.profile.channels;
}
