/* global ajaxurl */
document.addEventListener( 'DOMContentLoaded', function() {
	'use strict';

	var tabs = document.querySelectorAll( '.export-tab' ),
		panels = document.querySelectorAll( '.export-panel' ),
		first = tabs[0],
		last = tabs[tabs.length - 1],
		textarea = document.getElementById( 'post-ids' ),
		form = document.getElementById( 'export-form' );

	enableDisableIncludeExclude( textarea );

	tabs.forEach( function( tab ) {
		tab.addEventListener( 'click', function() {

			// De-select previous active tab
			var activeButton = document.querySelector( '.active' );
			activeButton.setAttribute( 'aria-selected', false );
			activeButton.setAttribute( 'tabindex', '-1' );
			activeButton.classList.remove( 'active' );

			// Identify newly-selected tab
			tab.setAttribute( 'aria-selected', true );
			tab.classList.add( 'active' );
			tab.setAttribute( 'tabindex', '0' );

			// Disable all panels
			panels.forEach( function( panel ) {
				var checkboxes = panel.querySelectorAll( 'input[type="checkbox"]:not([id="users-published"]' ),
					allbox = checkboxes[0];

				panel.classList.add( 'hidden' );
				panel.setAttribute( 'aria-hidden', true );
				panel.setAttribute( 'disabled', true );
				panel.setAttribute( 'inert', true );
				panel.querySelectorAll( 'input, select' ).forEach( function( input ) {
					input.disabled = true;
				} );

				// Enable the panel associated with the current tab
				if ( panel.id === tab.getAttribute( 'aria-controls' ) ) {
					panel.classList.remove( 'hidden' );
					panel.setAttribute( 'aria-hidden', false );
					panel.removeAttribute( 'disabled' );
					panel.removeAttribute( 'inert' );
					panel.querySelectorAll( 'input, select' ).forEach( function( input ) {
						input.disabled = false;
					} );

					// Enable the include/exclude option if an ID is specified
					textarea = panel.querySelector( 'textarea' );
					if ( textarea && textarea.id.split( '-' ).at( -1 ) === 'ids' ) {
						enableDisableIncludeExclude( textarea );
					}

					// Set or remove required attribute on checkboxes
					if ( allbox ) {
						checkboxes.forEach( function( input ) {
							input.addEventListener( 'change', function( e ) {
								var full, empty;
								if ( e.target.checked ) {
									full = [ ...checkboxes ].splice( 0, 1 ); // removes allbox from array
									full.every( function( box ) {
										return box.checked === true;
									} );
									if ( full ) {
										allbox.checked = true;
									}
									checkboxes.forEach( function( checkbox ) {
										checkbox.removeAttribute( 'required' );
									} );
								} else {
									allbox.checked = false;
									empty = [ ...checkboxes ].every( function( box ) {
										return box.checked === false;
									} );
									checkboxes.forEach( function( checkbox ) {
										if ( empty && panel.id !== 'panel-users' ) {
											checkbox.setAttribute( 'required', true );
										}
									} );
								}
							} );
						} );

						// Enable ALL checkbox to check or uncheck other checkboxes within same panel
						allbox.addEventListener( 'change', function( e ) {
							if ( e.target.value === '0' ) {
								if ( e.target.checked ) {
									checkboxes.forEach( function( input ) {
										input.checked = true;
									} );
								} else {
									checkboxes.forEach( function( input ) {
										input.checked = false;
										input.setAttribute( 'required', true );
									} );
								}
							}
						} );
					}
				}
			} );

			// Set the correct endpoint
			document.querySelector( 'input[name="type"]' ).value = tab.id.replace( 'tab-', '' );
		} );

		// Enable tab navigation via the keyboard
		tab.addEventListener( 'keydown', function( e ) {
			if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
				e.preventDefault();
				if ( e.target === last ) 		{	
					first.focus();
					first.click();
				} else {
					tab.nextElementSibling.focus();
					tab.nextElementSibling.click();
				}
			} else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				if ( e.target === first ) {
					last.focus();
					last.click();
				} else {
					tab.previousElementSibling.focus();
					tab.previousElementSibling.click();
				}
			} else if ( e.key === 'Home' ) {
				e.preventDefault();
				first.focus();
				first.click();
			} else if ( e.key === 'End' ) {
				e.preventDefault();
				last.focus();
				last.click();
			}
		} );
	} );

	// Set number of records per query
	document.querySelectorAll( '#panel-settings input' ).forEach( function( input ) {
		input.addEventListener( 'click', function() {
			var settings = document.getElementById( 'panel-settings' ),
				data = new URLSearchParams( {
					action: 'export_json_per_page',
					per_page: input.value
				} ),
				span = document.createElement( 'span' );

			form.querySelector( 'input[name="per_page"]' ).value = input.value;

			fetch( ajaxurl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			} )
			.then( function( response ) {
				if ( response.ok ) {
					return response.json(); // no errors
				}
				throw new Error( response.status );
			} )
			.then( function( result ) {
				span.style.color = 'green';
				span.style.fontSize = '120%';
				span.textContent = settings.dataset.message;
				settings.prepend( span );
				setTimeout( function() {
					span.remove();
				}, 3000 );
			} )
			.catch( function( error ) {
				span.style.color = 'red';
				span.style.fontSize = '120%';
				span.textContent = error;
				settings.prepend( span );
				setTimeout( function() {
					span.remove();
				}, 3000 );
			} );
		} );
	} );

	// Prevent form submission if the end date is earlier than the start date
	form.addEventListener( 'submit', function( e ) {
		e.preventDefault();
		panels.forEach( function( panel ) {
			if ( ! panel.hasAttribute( 'disabled' ) && ! panel.hasAttribute( 'inert' ) ) {
				if ( panel.querySelector( '[name="start_date"]' ).value > panel.querySelector( '[name="end_date"]' ).value ) {
					alert( form.dataset.message );

					// Clear both start and end dates, and set focus on the former
					panel.querySelector( '[name="start_date"]' ).value = 0;
					panel.querySelector( '[name="end_date"]' ).value = 0;
					panel.querySelector( '[name="start_date"]' ).focus();
				} else {
					form.submit();
				}
			}
		} );
	} );

	// Enable or disable the include/exclude option according to whether an ID has been provided
	function enableDisableIncludeExclude( textarea ) {
		textarea.addEventListener( 'input', function() {
			var fieldset = document.getElementById( textarea.id + '-fieldset' );
			if ( isNumber( textarea.value ) ) {
				fieldset.removeAttribute( 'disabled' );
			} else {
				fieldset.setAttribute( 'disabled', true );
			}
			fieldset.addEventListener( 'input', function( e ) {
				if ( e.target.value === 'exclude' ) {
					textarea.name = 'exclude';
				} else {
					textarea.name = 'include';
				}
			} );
		} );
	}

	// Check that each entry in comma-demlimited string is a number
	function isNumber( str ) {
		str = str.split( ',' ).map( e => e.trim() ).filter( e => e ).join( ', ' );
		var res = str.split( ',' ).every( function( val ) { 
			return parseInt( val ) == val;
		} );
		return res ? true : false;
	}

} );
