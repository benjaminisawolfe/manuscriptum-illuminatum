( function () {
	'use strict';

	document.documentElement.classList.add( 'ligatura-manuscripti-illuminati-admin' );

	const metaEditorSelector = 'textarea.manuscriptum-illuminatum-meta-editor';
	const boundEditors = new WeakSet();
	const movingMetaBoxes = new WeakSet();
	const isLiveEditor = function ( editor ) {
		return editor.iframeElement && editor.iframeElement.contentDocument && editor.getBody() === editor.iframeElement.contentDocument.body;
	};

	const bindMetaEditor = function ( event, editor ) {
		const textarea = document.getElementById( editor.id );
		if ( ! textarea || ! textarea.matches( metaEditorSelector ) || boundEditors.has( editor ) ) {
			return;
		}
		boundEditors.add( editor );
		editor.on( 'input change undo redo', function () {
			if ( ! editor.isHidden() && isLiveEditor( editor ) ) {
				editor.save();
				// Keep Gutenberg's separate meta-box form current and dirty.
				textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	};

	const removeMetaEditor = function ( textarea ) {
		const editor = window.tinymce && tinymce.get( textarea.id );
		if ( ! editor ) {
			return;
		}
		if ( editor.initialized && ! editor.isHidden() && isLiveEditor( editor ) ) {
			editor.save();
		}
		// A moved iframe may already be blank. Preserve the synchronized field
		// while removing that obsolete instance, never serialize its blank body.
		const content = textarea.value;
		editor.remove();
		textarea.value = content;
	};

	const initializeMetaEditors = function () {
		const editorApi = window.wp && ( wp.oldEditor || wp.editor );
		if ( ! editorApi || ! editorApi.initialize || ! window.tinyMCEPreInit ) {
			return;
		}
		document.querySelectorAll( metaEditorSelector ).forEach( function ( textarea ) {
			// #metaboxes is Gutenberg's temporary, hidden source container.
			if ( textarea.closest( '#metaboxes, .ui-sortable-helper' ) || ! textarea.isConnected || movingMetaBoxes.has( textarea.closest( '.postbox' ) ) ) {
				return;
			}
			let editor = window.tinymce && tinymce.get( textarea.id );
			if ( editor && editor.initialized && ! isLiveEditor( editor ) ) {
				removeMetaEditor( textarea );
				editor = null;
			}
			const wrap = textarea.closest( '.wp-editor-wrap' );
			const settings = tinyMCEPreInit.mceInit[ textarea.id ];
			if ( editor || ! settings || ! wrap || wrap.classList.contains( 'html-active' ) || ! wrap.getClientRects().length ) {
				return;
			}
			// wp_editor already supplies the wrapper, tabs and Quicktags. Initialize
			// only TinyMCE here, with its existing WordPress-filtered settings.
			editorApi.initialize( textarea.id, { tinymce: settings } );
		} );
	};

	const bindMetaEditors = function () {
		if ( ! document.querySelector( metaEditorSelector ) ) {
			return;
		}
		jQuery( document ).on( 'tinymce-editor-init', bindMetaEditor );
		jQuery( document ).on( 'sortstart', '.meta-box-sortables', function ( event, ui ) {
			movingMetaBoxes.add( ui.item[ 0 ] );
			ui.item.find( metaEditorSelector ).each( function () { removeMetaEditor( this ); } );
		} );
		jQuery( document ).on( 'sortstop', '.meta-box-sortables', function ( event, ui ) {
			movingMetaBoxes.delete( ui.item[ 0 ] );
			initializeMetaEditors();
		} );
		// WordPress's keyboard ordering buttons move the same iframe subtree.
		document.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '.handle-order-higher, .handle-order-lower' );
			if ( button && button.getAttribute( 'aria-disabled' ) !== 'true' ) {
				const box = button.closest( '.postbox' );
				movingMetaBoxes.add( box );
				box.querySelectorAll( metaEditorSelector ).forEach( removeMetaEditor );
			}
		}, true );
		// Mutation observers can run between capture and bubble listeners. Resume
		// only after WordPress's document listener has actually moved the box.
		window.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '.handle-order-higher, .handle-order-lower' );
			if ( button ) {
				movingMetaBoxes.delete( button.closest( '.postbox' ) );
				initializeMetaEditors();
			}
		} );
		const observer = new MutationObserver( initializeMetaEditors );
		observer.observe( document.getElementById( 'wpbody-content' ), {
			childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style' ],
		} );
		initializeMetaEditors();
	};

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
		wp.domReady( bindMetaEditors );
	} else {
		document.addEventListener( 'DOMContentLoaded', bindCharacterAuthor );
		document.addEventListener( 'DOMContentLoaded', bindMetaEditors );
	}
}() );
