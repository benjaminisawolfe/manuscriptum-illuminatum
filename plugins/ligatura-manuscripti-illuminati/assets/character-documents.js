( function () {
	'use strict';

	const { __ } = wp.i18n;
	const domain = 'ligatura-manuscripti-illuminati';
	const initialize = function ( control ) {
		const field = control.querySelector( '[data-document-ids]' );
		const list = control.querySelector( '[data-document-list]' );
		const status = control.querySelector( '[data-document-status]' );
		const files = control.querySelector( '[data-document-upload]' );
		let busy = false;
		let frame;
		const report = ( message, error = false ) => {
			status.textContent = message;
			status.classList.toggle( 'notice-error', error );
		};
		const synchronize = () => {
			field.value = JSON.stringify( Array.from( list.querySelectorAll( '[data-document-id]' ), row => Number( row.dataset.documentId ) ) );
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};
		const pending = () => report( __( 'Document list changed. Save the Character to keep these changes.', domain ) );
		const append = document => {
			if ( list.querySelector( `[data-document-id="${Number( document.id )}"]` ) ) return;
			const row = window.document.createElement( 'li' );
			row.dataset.documentId = Number( document.id );
			const link = window.document.createElement( 'a' );
			link.href = document.url;
			link.textContent = document.title || document.filename;
			const type = window.document.createElement( 'span' );
			type.textContent = ` (${document.type}) `;
			const remove = window.document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'button-link-delete';
			remove.dataset.unlinkDocument = '';
			remove.textContent = __( 'Unlink', domain );
			remove.setAttribute( 'aria-label', `${__( 'Unlink document:', domain )} ${document.title || document.filename}` );
			row.append( link, type, remove );
			list.append( row );
			synchronize();
		};
		const request = async ( suffix, options = {} ) => {
			let response;
			try {
				response = await fetch( control.dataset.restUrl + suffix, {
					...options, credentials: 'same-origin', headers: { 'X-WP-Nonce': control.dataset.restNonce },
				} );
			} catch {
				throw new Error( __( 'The document request failed. Check your connection and try again.', domain ) );
			}
			const result = await response.json().catch( () => null );
			if ( ! response.ok || ! result ) {
				throw new Error( result?.message || ( response.status === 413
					? __( 'This file exceeds the server upload limit.', domain )
					: __( 'The document request failed. Reload the editor to check your access and try again.', domain ) ) );
			}
			return result;
		};
		const setBusy = value => {
			busy = value;
			control.setAttribute( 'aria-busy', String( value ) );
			control.querySelectorAll( 'button, input[type="file"]' ).forEach( element => { element.disabled = value; } );
			const editor = wp.data?.dispatch( 'core/editor' );
			if ( editor?.lockPostSaving ) {
				if ( value ) editor.lockPostSaving( 'ligatura-character-documents' );
				else editor.unlockPostSaving( 'ligatura-character-documents' );
			}
		};
		control.closest( 'form' )?.addEventListener( 'submit', event => {
			if ( busy ) {
				event.preventDefault();
				report( __( 'Wait for the document request to finish before saving.', domain ), true );
			}
		} );
		control.addEventListener( 'click', async event => {
			const button = event.target.closest( 'button' );
			if ( ! button || busy ) return;
			if ( button.hasAttribute( 'data-unlink-document' ) ) {
				button.closest( '[data-document-id]' ).remove();
				synchronize();
				pending();
				control.querySelector( '[data-attach-documents]' ).focus();
			}
			if ( button.hasAttribute( 'data-upload-documents' ) ) {
				if ( ! files.files.length ) {
					report( __( 'Choose one or more documents to upload.', domain ), true );
					files.focus();
					return;
				}
				setBusy( true );
				report( __( 'Uploading documents…', domain ) );
				const errors = [];
				for ( const file of files.files ) {
					try {
						if ( file.size > Number( control.dataset.maxBytes ) ) throw new Error( __( 'This file exceeds the server upload limit.', domain ) );
						const data = new FormData();
						data.append( 'document', file );
						append( await request( '/upload', { method: 'POST', body: data } ) );
					} catch ( error ) {
						errors.push( `${file.name}: ${error.message}` );
					}
				}
				files.value = '';
				setBusy( false );
				if ( errors.length ) report( errors.join( '\n' ) + '\n' + __( 'Save the Character to keep any documents that uploaded successfully.', domain ), true );
				else pending();
			}
			if ( button.hasAttribute( 'data-attach-documents' ) ) {
				if ( ! frame ) {
					// A selection-only native frame; uploads use the validated endpoint above.
					const DocumentFrame = wp.media.view.MediaFrame.Select.extend( {
						browseRouter: router => router.set( { browse: { text: __( 'Media Library', domain ), priority: 40 } } ),
					} );
					frame = new DocumentFrame( {
						title: __( 'Attach Document', domain ), button: { text: __( 'Attach Document', domain ) },
						multiple: true, uploader: false,
						library: { type: [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.oasis.opendocument.text', 'application/rtf', 'text/rtf', 'text/plain' ] },
					} );
					frame.on( 'select', async () => {
						setBusy( true );
						try {
							const ids = frame.state().get( 'selection' ).map( attachment => attachment.id );
							const result = await request( '?ids=' + encodeURIComponent( ids.join( ',' ) ) );
							result.documents.forEach( append );
							pending();
						} catch ( error ) {
							report( error.message, true );
						} finally { setBusy( false ); }
					} );
				}
				frame.open();
			}
		} );
	};
	wp.domReady( () => document.querySelectorAll( '.ligatura-character-documents' ).forEach( initialize ) );
}() );
