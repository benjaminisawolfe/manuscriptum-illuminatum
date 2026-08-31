( function () {
	'use strict';

	document.documentElement.classList.add( 'manuscriptum-illuminatum-js' );

	function closeSiblingMenus( item ) {
		const parent = item.parentElement;

		if ( ! parent ) {
			return;
		}

		parent.querySelectorAll( ':scope > .menu-item-has-children.is-open' ).forEach( function ( sibling ) {
			if ( sibling === item ) {
				return;
			}

			sibling.classList.remove( 'is-open' );
			const toggle = sibling.querySelector( ':scope > .site-nav__submenu-toggle' );

			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	function setMenuState( item, expanded ) {
		item.classList.toggle( 'is-open', expanded );

		const toggle = item.querySelector( ':scope > .site-nav__submenu-toggle' );

		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		}
	}

	function setupSubmenus() {
		document.querySelectorAll( '.site-nav__menu' ).forEach( function ( menu, menuIndex ) {
			menu.querySelectorAll( '.menu-item-has-children' ).forEach( function ( item, itemIndex ) {
				const link = item.querySelector( ':scope > a' );
				const submenu = item.querySelector( ':scope > .sub-menu' );

				if ( ! link || ! submenu || item.querySelector( ':scope > .site-nav__submenu-toggle' ) ) {
					return;
				}

				if ( ! submenu.id ) {
					submenu.id = 'site-nav-submenu-' + menuIndex + '-' + itemIndex;
				}

				link.setAttribute( 'aria-haspopup', 'true' );

				const toggle = document.createElement( 'button' );
				toggle.className = 'site-nav__submenu-toggle';
				toggle.type = 'button';
				toggle.setAttribute( 'aria-controls', submenu.id );
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.setAttribute( 'aria-label', 'Toggle submenu for ' + link.textContent.trim() );

				item.insertBefore( toggle, submenu );

				toggle.addEventListener( 'click', function () {
					const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
					closeSiblingMenus( item );
					setMenuState( item, ! expanded );
				} );

				item.addEventListener( 'keydown', function ( event ) {
					if ( event.key === 'Escape' ) {
						setMenuState( item, false );
						link.focus();
					}
				} );
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.site-nav__menu' ) ) {
				return;
			}

			document.querySelectorAll( '.site-nav__menu .menu-item-has-children.is-open' ).forEach( function ( item ) {
				setMenuState( item, false );
			} );
		} );
	}

	function setupPagedDirectory( directory, config ) {
		const form = directory.querySelector( config.form );
		const results = directory.querySelector( config.results );
		const loadMore = directory.querySelector( config.loadMore );
		const empty = directory.querySelector( config.empty );
		const error = directory.querySelector( config.error );
		const status = directory.querySelector( config.status );
		const advancedToggle = directory.querySelector( config.advancedToggle );
		const advanced = directory.querySelector( config.advanced );
		const clear = directory.querySelector( config.clear );
		const queryInput = form ? form.querySelector( config.queryInput ) : null;
		let busy = false;
		let activeRequest = null;
		let requestSequence = 0;
		let resetTimer = null;
		let hadSimpleSearch = Boolean( queryInput && queryInput.value.trim() );

		if ( ! form || ! results || ! loadMore || ! empty || ! error || ! status ) {
			return;
		}

		function setAdvancedState( expanded ) {
			if ( advancedToggle && advanced ) {
				advancedToggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				advanced.hidden = ! expanded;
			}
		}

		function filterData() {
			return new FormData( form );
		}

		function updateAddress( data ) {
			const address = new URL( form.action, window.location.href );

			data.forEach( function ( value, key ) {
				const field = form.elements.namedItem( key );
				const normalized = String( value ).trim();

				if ( normalized && ( ! field || ! field.hasAttribute( 'data-directory-context' ) ) ) {
					address.searchParams.set( key, normalized );
				}
			} );

			window.history.replaceState( {}, '', address );
		}

		function setBusy( isBusy, loadingMore ) {
			busy = isBusy;
			results.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
			loadMore.disabled = isBusy;
			form.querySelectorAll( 'input, select, button' ).forEach( function ( control ) {
				control.disabled = isBusy;
			} );

			if ( clear ) {
				clear.setAttribute( 'aria-disabled', isBusy ? 'true' : 'false' );
				if ( isBusy ) {
					clear.setAttribute( 'tabindex', '-1' );
				} else {
					clear.removeAttribute( 'tabindex' );
				}
			}

			if ( isBusy ) {
				status.textContent = loadingMore ? config.loadingMoreStatus : config.searchingStatus;
				loadMore.textContent = loadingMore ? 'Loading…' : config.loadMoreLabel;
			} else {
				loadMore.textContent = config.loadMoreLabel;
			}
		}

		async function requestResults( page, append ) {
			if ( busy ) {
				return;
			}

			if ( activeRequest ) {
				activeRequest.abort();
			}

			const controller = new AbortController();
			const requestId = requestSequence + 1;
			const data = filterData();
			const endpoint = new URL( directory.dataset.endpoint, window.location.href );
			activeRequest = controller;
			requestSequence = requestId;

			data.forEach( function ( value, key ) {
				const normalized = String( value ).trim();
				if ( normalized ) {
					endpoint.searchParams.set( key, normalized );
				}
			} );
			endpoint.searchParams.set( 'page', String( page ) );

			error.hidden = true;
			error.textContent = '';
			setBusy( true, append );

			try {
				const response = await fetch( endpoint.toString(), {
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': directory.dataset.nonce },
					signal: controller.signal,
				} );

				if ( ! response.ok ) {
					throw new Error( config.requestError );
				}

				const payload = await response.json();

				if ( requestId !== requestSequence ) {
					return;
				}

				if ( append ) {
					results.insertAdjacentHTML( 'beforeend', payload.html );
				} else {
					results.innerHTML = payload.html;
					updateAddress( data );
				}

				directory.dataset.page = String( payload.page );
				directory.dataset.hasMore = payload.hasMore ? 'true' : 'false';
				loadMore.hidden = ! payload.hasMore;
				empty.hidden = Boolean( results.querySelector( config.rowSelector ) );
				status.textContent = append
					? payload.count + ' more ' + config.plural + ' loaded.'
					: payload.total + ( payload.total === 1 ? ' ' + config.singular + ' found.' : ' ' + config.plural + ' found.' );
			} catch ( requestError ) {
				if ( requestError.name === 'AbortError' || requestId !== requestSequence ) {
					return;
				}

				error.textContent = config.errorMessage;
				error.hidden = false;
				status.textContent = config.failureStatus;
			} finally {
				if ( requestId === requestSequence ) {
					activeRequest = null;
					setBusy( false, false );
				}
			}
		}

		if ( advancedToggle && advanced ) {
			advancedToggle.addEventListener( 'click', function () {
				setAdvancedState( advancedToggle.getAttribute( 'aria-expanded' ) !== 'true' );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( busy ) {
				return;
			}
			window.clearTimeout( resetTimer );
			hadSimpleSearch = Boolean( queryInput && queryInput.value.trim() );
			requestResults( 1, false );
		} );

		if ( queryInput ) {
			queryInput.addEventListener( 'input', function () {
				const hasSimpleSearch = Boolean( queryInput.value.trim() );
				window.clearTimeout( resetTimer );

				if ( hasSimpleSearch ) {
					hadSimpleSearch = true;
					return;
				}

				if ( ! hadSimpleSearch ) {
					return;
				}

				hadSimpleSearch = false;
				resetTimer = window.setTimeout( function () {
					requestResults( 1, false );
				}, 150 );
			} );
		}

		loadMore.addEventListener( 'click', function () {
			requestResults( Number.parseInt( directory.dataset.page || '1', 10 ) + 1, true );
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				if ( busy ) {
					return;
				}
				window.clearTimeout( resetTimer );
				form.reset();
				hadSimpleSearch = false;
				requestResults( 1, false );
			} );
		}
	}

	function setupJournalDirectory() {
		document.querySelectorAll( '.manuscriptum-illuminatum-journal-directory:not(.manuscriptum-illuminatum-covenant-directory)' ).forEach( function ( directory ) {
			setupPagedDirectory( directory, {
				form: '[data-journal-search-form]', results: '[data-journal-results]', loadMore: '[data-journal-load-more]',
				empty: '[data-journal-empty]', error: '[data-journal-error]', status: '[data-journal-status]',
				advancedToggle: '[data-journal-advanced-toggle]', advanced: '[data-journal-advanced]', clear: '[data-journal-clear]',
				queryInput: 'input[name="journal_q"]', rowSelector: '.manuscriptum-illuminatum-journal-row', loadMoreLabel: 'More Commentarii',
				singular: 'Journal', plural: 'Journals', searchingStatus: 'Searching Journals.', loadingMoreStatus: 'Loading more Commentarii.',
				requestError: 'Journal request failed.', errorMessage: 'The Journal results could not be loaded. Please try again.', failureStatus: 'Journal loading failed.',
			} );
		} );
	}

	function setupCovenantDirectory() {
		document.querySelectorAll( '.manuscriptum-illuminatum-covenant-directory' ).forEach( function ( directory ) {
			setupPagedDirectory( directory, {
				form: '[data-covenant-search-form]', results: '[data-covenant-results]', loadMore: '[data-covenant-load-more]',
				empty: '[data-covenant-empty]', error: '[data-covenant-error]', status: '[data-covenant-status]',
				advancedToggle: '[data-covenant-advanced-toggle]', advanced: '[data-covenant-advanced]', clear: '[data-covenant-clear]',
				queryInput: 'input[name="covenant_q"]', rowSelector: '.manuscriptum-illuminatum-covenant-record-row', loadMoreLabel: 'More Covenant Records',
				singular: 'Covenant Record', plural: 'Covenant Records', searchingStatus: 'Searching Covenant Records.', loadingMoreStatus: 'Loading more Covenant Records.',
				requestError: 'Covenant Records request failed.', errorMessage: 'The Covenant Record results could not be loaded. Please try again.', failureStatus: 'Covenant Records loading failed.',
			} );
		} );
	}

	function setupSpeculumDirectory() {
		document.querySelectorAll( '.manuscriptum-illuminatum-wiki-directory' ).forEach( function ( directory ) {
			const form = directory.querySelector( '[data-speculum-search-form]' );
			const results = directory.querySelector( '[data-speculum-results]' );
			const empty = directory.querySelector( '[data-speculum-empty]' );
			const error = directory.querySelector( '[data-speculum-error]' );
			const status = directory.querySelector( '[data-speculum-status]' );
			const advancedToggle = directory.querySelector( '[data-speculum-advanced-toggle]' );
			const advanced = directory.querySelector( '[data-speculum-advanced]' );
			const clear = directory.querySelector( '[data-speculum-clear]' );
			const queryInput = form ? form.querySelector( 'input[name="speculum_q"]' ) : null;
			let busy = false;
			let activeRequest = null;
			let requestSequence = 0;
			let resetTimer = null;
			let hadSimpleSearch = Boolean( queryInput && queryInput.value.trim() );

			if ( ! form || ! results || ! empty || ! error || ! status ) {
				return;
			}

			function setAdvancedState( expanded ) {
				if ( ! advancedToggle || ! advanced ) {
					return;
				}

				advancedToggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				advanced.hidden = ! expanded;
			}

			function filterData() {
				return new FormData( form );
			}

			function updateAddress( data ) {
				const address = new URL( form.action, window.location.href );

				data.forEach( function ( value, key ) {
					const normalized = String( value ).trim();

					if ( normalized ) {
						address.searchParams.set( key, normalized );
					}
				} );

				window.history.replaceState( {}, '', address );
			}

			function setBusy( isBusy ) {
				busy = isBusy;
				results.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
				form.querySelectorAll( 'input, select, button' ).forEach( function ( control ) {
					control.disabled = isBusy;
				} );

				if ( clear ) {
					clear.setAttribute( 'aria-disabled', isBusy ? 'true' : 'false' );

					if ( isBusy ) {
						clear.setAttribute( 'tabindex', '-1' );
					} else {
						clear.removeAttribute( 'tabindex' );
					}
				}

				if ( isBusy ) {
					status.textContent = 'Searching Speculum.';
				}
			}

			async function requestResults() {
				if ( activeRequest ) {
					activeRequest.abort();
				}

				const controller = new AbortController();
				const requestId = requestSequence + 1;
				const data = filterData();
				const endpoint = new URL( directory.dataset.endpoint, window.location.href );
				activeRequest = controller;
				requestSequence = requestId;

				data.forEach( function ( value, key ) {
					const normalized = String( value ).trim();

					if ( normalized ) {
						endpoint.searchParams.set( key, normalized );
					}
				} );

				error.hidden = true;
				error.textContent = '';
				setBusy( true );

				try {
					const response = await fetch( endpoint.toString(), {
						credentials: 'same-origin',
						headers: {
							'X-WP-Nonce': directory.dataset.nonce,
						},
						signal: controller.signal,
					} );

					if ( ! response.ok ) {
						throw new Error( 'Speculum request failed.' );
					}

					const payload = await response.json();

					if ( requestId !== requestSequence ) {
						return;
					}

					results.innerHTML = payload.html;
					directory.dataset.grouped = payload.grouped ? 'true' : 'false';
					empty.hidden = Boolean( payload.html );
					updateAddress( data );
					status.textContent = payload.grouped
						? 'The grouped Speculum directory has been restored.'
						: payload.total + ( payload.total === 1 ? ' Speculum entry found.' : ' Speculum entries found.' );
				} catch ( requestError ) {
					if ( requestError.name === 'AbortError' || requestId !== requestSequence ) {
						return;
					}

					error.textContent = 'The Speculum results could not be loaded. Please try again.';
					error.hidden = false;
					status.textContent = 'Speculum loading failed.';
				} finally {
					if ( requestId === requestSequence ) {
						activeRequest = null;
						setBusy( false );
					}
				}
			}

			if ( advancedToggle && advanced ) {
				advancedToggle.addEventListener( 'click', function () {
					setAdvancedState( advancedToggle.getAttribute( 'aria-expanded' ) !== 'true' );
				} );
			}

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				if ( busy ) {
					return;
				}

				window.clearTimeout( resetTimer );
				hadSimpleSearch = Boolean( queryInput && queryInput.value.trim() );
				requestResults();
			} );

			if ( queryInput ) {
				queryInput.addEventListener( 'input', function () {
					const hasSimpleSearch = Boolean( queryInput.value.trim() );

					window.clearTimeout( resetTimer );

					if ( hasSimpleSearch ) {
						hadSimpleSearch = true;
						return;
					}

					if ( ! hadSimpleSearch ) {
						return;
					}

					hadSimpleSearch = false;
					resetTimer = window.setTimeout( requestResults, 150 );
				} );
			}

			if ( clear ) {
				clear.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					if ( busy ) {
						return;
					}

					window.clearTimeout( resetTimer );
					form.reset();
					hadSimpleSearch = false;
					requestResults();
				} );
			}
		} );
	}

	function setupPersonaeDirectory() {
		document.querySelectorAll( '.manuscriptum-illuminatum-personae-directory' ).forEach( function ( directory ) {
			const form = directory.querySelector( '[data-personae-search-form]' );
			const results = directory.querySelector( '[data-personae-results]' );
			const empty = directory.querySelector( '[data-personae-empty]' );
			const error = directory.querySelector( '[data-personae-error]' );
			const status = directory.querySelector( '[data-personae-status]' );
			const advancedToggle = directory.querySelector( '[data-personae-advanced-toggle]' );
			const advanced = directory.querySelector( '[data-personae-advanced]' );
			const clear = directory.querySelector( '[data-personae-clear]' );
			const queryInput = form ? form.querySelector( 'input[name="personae_q"]' ) : null;
			let busy = false;
			let activeRequest = null;
			let requestSequence = 0;
			let resetTimer = null;
			let hadSimpleSearch = Boolean( queryInput && queryInput.value.trim() );

			if ( ! form || ! results || ! empty || ! error || ! status ) {
				return;
			}

			function setAdvancedState( expanded ) {
				if ( ! advancedToggle || ! advanced ) {
					return;
				}

				advancedToggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				advanced.hidden = ! expanded;
			}

			function filterData() {
				return new FormData( form );
			}

			function updateAddress( data ) {
				const address = new URL( form.action, window.location.href );

				data.forEach( function ( value, key ) {
					const normalized = String( value ).trim();

					if ( normalized ) {
						address.searchParams.set( key, normalized );
					}
				} );

				window.history.replaceState( {}, '', address );
			}

			function setBusy( isBusy ) {
				busy = isBusy;
				results.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
				form.querySelectorAll( 'input, select, button' ).forEach( function ( control ) {
					control.disabled = isBusy;
				} );

				if ( clear ) {
					clear.setAttribute( 'aria-disabled', isBusy ? 'true' : 'false' );

					if ( isBusy ) {
						clear.setAttribute( 'tabindex', '-1' );
					} else {
						clear.removeAttribute( 'tabindex' );
					}
				}

				if ( isBusy ) {
					status.textContent = 'Searching Personae.';
				}
			}

			async function requestResults() {
				if ( activeRequest ) {
					activeRequest.abort();
				}

				const controller = new AbortController();
				const requestId = requestSequence + 1;
				const data = filterData();
				const endpoint = new URL( directory.dataset.endpoint, window.location.href );
				activeRequest = controller;
				requestSequence = requestId;

				data.forEach( function ( value, key ) {
					const normalized = String( value ).trim();

					if ( normalized ) {
						endpoint.searchParams.set( key, normalized );
					}
				} );

				error.hidden = true;
				error.textContent = '';
				setBusy( true );

				try {
					const response = await fetch( endpoint.toString(), {
						credentials: 'same-origin',
						headers: {
							'X-WP-Nonce': directory.dataset.nonce,
						},
						signal: controller.signal,
					} );

					if ( ! response.ok ) {
						throw new Error( 'Personae request failed.' );
					}

					const payload = await response.json();

					if ( requestId !== requestSequence ) {
						return;
					}

					results.innerHTML = payload.html;
					directory.dataset.grouped = payload.grouped ? 'true' : 'false';
					empty.hidden = Boolean( payload.html );
					updateAddress( data );
					status.textContent = payload.grouped
						? 'The grouped Personae directory has been restored.'
						: payload.total + ( payload.total === 1 ? ' Persona found.' : ' Personae found.' );
				} catch ( requestError ) {
					if ( requestError.name === 'AbortError' || requestId !== requestSequence ) {
						return;
					}

					error.textContent = 'The Personae results could not be loaded. Please try again.';
					error.hidden = false;
					status.textContent = 'Personae loading failed.';
				} finally {
					if ( requestId === requestSequence ) {
						activeRequest = null;
						setBusy( false );
					}
				}
			}

			if ( advancedToggle && advanced ) {
				advancedToggle.addEventListener( 'click', function () {
					setAdvancedState( advancedToggle.getAttribute( 'aria-expanded' ) !== 'true' );
				} );
			}

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				if ( busy ) {
					return;
				}

				window.clearTimeout( resetTimer );
				hadSimpleSearch = Boolean( queryInput && queryInput.value.trim() );
				requestResults();
			} );

			if ( queryInput ) {
				queryInput.addEventListener( 'input', function () {
					const hasSimpleSearch = Boolean( queryInput.value.trim() );

					window.clearTimeout( resetTimer );

					if ( hasSimpleSearch ) {
						hadSimpleSearch = true;
						return;
					}

					if ( ! hadSimpleSearch ) {
						return;
					}

					hadSimpleSearch = false;
					resetTimer = window.setTimeout( requestResults, 150 );
				} );
			}

			if ( clear ) {
				clear.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					if ( busy ) {
						return;
					}

					window.clearTimeout( resetTimer );
					form.reset();
					hadSimpleSearch = false;
					requestResults();
				} );
			}
		} );
	}

	function setupTheme() {
		setupSubmenus();
		setupJournalDirectory();
		setupCovenantDirectory();
		setupSpeculumDirectory();
		setupPersonaeDirectory();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', setupTheme );
	} else {
		setupTheme();
	}
}() );
