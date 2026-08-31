( function () {
	'use strict';

	document.documentElement.classList.add( 'ligatura-manuscripti-illuminati-admin' );

	const bindCharacterAuthor = function () {
		const characterAuthor = document.querySelector( 'select[name="ligatura_diary_character"]' );
		const editorDispatch = window.wp && wp.data ? wp.data.dispatch( 'core/editor' ) : null;

		if ( ! characterAuthor || ! editorDispatch || typeof editorDispatch.editPost !== 'function' ) {
			return;
		}

		const syncCharacterAuthor = function () {
			const characterId = Number.parseInt( characterAuthor.value, 10 ) || 0;
			editorDispatch.editPost( {
				meta: { ligatura_diary_character: characterId },
			} );
		};

		characterAuthor.addEventListener( 'change', syncCharacterAuthor );
		syncCharacterAuthor();
	};

	if ( window.wp && typeof wp.domReady === 'function' ) {
		wp.domReady( bindCharacterAuthor );
	} else {
		document.addEventListener( 'DOMContentLoaded', bindCharacterAuthor );
	}
}() );
