( function () {
	// Live source feedback stays in the browser; saving remains authoritative.
	function normalizeSourceValue( input ) {
		var value = input.value.replace( /^[\x00\t\n\r\v ]+|[\x00\t\n\r\v ]+$/g, '' );
		if ( ! value ) return '';
		if ( input.type === 'number' ) {
			var number = Number( value );
			if ( ! Number.isFinite( number ) ) return '';
			if ( input.min !== '' ) number = Math.max( number, Number( input.min ) );
			value = new Intl.NumberFormat( 'en-US', { useGrouping: false, maximumFractionDigits: 4 } ).format( number );
			return Number( value ) === 0 ? '0' : value;
		}
		if ( input.tagName === 'SELECT' || input.type === 'date' ) return value;
		// Match the plain-text cleanup used for saved catalogue fields.
		value = value.replace( /<[^>]*?((?=<)|>|$)/g, function ( part ) {
			return part.includes( '>' ) ? part : part.replace( /</g, '&lt;' );
		} ).replace( /<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, '' )
			.replace( /<!--[\s\S]*?-->|<[^>]*>/g, '' )
			.replace( /<\n/g, '&lt;\n' ).replace( /[\r\n\t ]+/g, ' ' ).trim();
		while ( /%[a-f0-9]{2}/i.test( value ) ) value = value.replace( /%[a-f0-9]{2}/gi, '' );
		return value.replace( / +/g, ' ' ).trim();
	}

	document.querySelectorAll( '[data-coll-item-form]' ).forEach( function ( form ) {
		form.querySelectorAll( '[data-coll-numista-key]' ).forEach( function ( indicator ) {
			var key = indicator.dataset.collNumistaKey;
			var input = form.elements.namedItem( indicator.dataset.collSourceInput );
			var own = Array.from( form.querySelectorAll( '[data-coll-numista-own]' ) ).find( function ( control ) { return control.value === key; } );
			var privateChoice = Array.from( form.querySelectorAll( '[data-coll-numista-private]' ) ).find( function ( control ) { return control.value === key; } );
			var clear = Array.from( form.querySelectorAll( '[data-coll-clear-source]' ) ).find( function ( control ) { return control.dataset.collClearSource === key; } );
			if ( ! input || ! own || ! privateChoice ) return;
			var revision = 0;
			var initialValue = normalizeSourceValue( input );
			var initiallyPrivate = indicator.dataset.state === 'private';
			function update( confirmed, state ) {
				indicator.dataset.state = state;
				indicator.closest( '.coll-numista-controls' ).hidden = state === 'cleared';
				indicator.setAttribute( 'aria-pressed', confirmed ? 'true' : 'false' );
				indicator.textContent = confirmed ? form.dataset.collSourceConfirmed : form.dataset.collSourcePrivate;
			}
			async function refresh() {
				var current = ++revision;
				var value = normalizeSourceValue( input );
				if ( ! value || ! privateChoice.disabled ) { update( false, value ? 'private' : 'cleared' ); return; }
				if ( own.checked || indicator.dataset.collSourceReleased === 'true' ) { update( true, 'confirmed' ); return; }
				var unchanged;
				if ( globalThis.crypto && globalThis.crypto.subtle ) {
					var digest = await globalThis.crypto.subtle.digest( 'SHA-256', new TextEncoder().encode( value ) );
					if ( current !== revision ) return;
					var hash = Array.from( new Uint8Array( digest ) ).map( function ( byte ) { return byte.toString( 16 ).padStart( 2, '0' ); } ).join( '' );
					unchanged = hash === indicator.dataset.collSourceHash;
				} else {
					// Older HTTP contexts can still compare against their initial private value.
					unchanged = initiallyPrivate && value === initialValue;
				}
				update( ! unchanged, unchanged ? 'private' : 'updated' );
			}
			function edited() {
				own.checked = false;
				privateChoice.disabled = true;
				refresh();
			}
			input.addEventListener( 'input', edited );
			input.addEventListener( 'change', edited );
			indicator.addEventListener( 'click', function () {
				own.checked = indicator.getAttribute( 'aria-pressed' ) !== 'true';
				privateChoice.disabled = own.checked;
				refresh();
			} );
			if ( clear ) clear.addEventListener( 'click', function () {
				input.value = '';
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				input.focus();
			} );
		} );
	} );


	// Keep typed item values when the separate catalogue form is submitted.
	document.querySelectorAll( '[data-coll-numista-lookup]' ).forEach( function ( lookup ) {
		lookup.addEventListener( 'formdata', function ( event ) {
			var itemForm = document.querySelector( '[data-coll-item-form]' );
			if ( ! itemForm ) return;
			new FormData( itemForm ).forEach( function ( value, name ) {
				if ( typeof value === 'string' && name !== 'coll_action' && name !== 'coll_nonce' && name !== '_wp_http_referer' ) {
					event.formData.append( name, value );
				}
			} );
		} );
	} );

	function parseJSON( value ) {
		if ( ! value ) {
			return {};
		}

		try {
			return JSON.parse( value );
		} catch ( error ) {
			return {};
		}
	}

	function updatePublicCount( collection, change, absolute ) {
		document.querySelectorAll( '[data-coll-public-count]' ).forEach( function ( count ) {
			if ( count.dataset.collPublicCount !== collection ) return;
			var value = absolute ? change : Number( count.dataset.collCount ) + change;
			count.dataset.collCount = String( value );
			count.textContent = value.toLocaleString();
		} );
	}


	function updateShareWarning( button, state ) {
		var warning = typeof state.warning === 'string' ? state.warning : ( button.dataset.collShareWarning || '' );
		button.dataset.collShareWarning = warning;
		var icon = button.querySelector( '[data-coll-share-warning]' );
		if ( icon ) icon.hidden = ! warning;
		button.title = warning || state.message || '';
		button.setAttribute( 'aria-label', button.dataset.collShareTitle + ': ' + state.action + ( warning ? '. ' + warning : '' ) );
	}

	var shareQueue = Promise.resolve();
	document.querySelectorAll( '[data-coll-share-item]' ).forEach( function ( button ) {
		button.hidden = false;
	} );
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-coll-share-item]' );
		if ( ! button || button.disabled ) return;
		var label = button.querySelector( '[data-coll-share-label]' );
		var previousLabel = label.textContent;
		var message = button.closest( '.item-card-body' ).querySelector( '.coll-share-message' );
		var selected = button.getAttribute( 'aria-pressed' ) !== 'true';
		button.disabled = true;
		label.textContent = button.dataset.collShareSaving;
		button.setAttribute( 'aria-busy', 'true' );
		message.textContent = '';
		message.classList.add( 'screen-reader-text' );
		shareQueue = shareQueue.then( async function () {
			var replied = false;
			var controller = new AbortController();
			var timeout = setTimeout( function () { controller.abort(); }, 20000 );
			try {
				var body = new FormData();
				body.set( 'action', 'coll_share_item' );
				body.set( 'item_id', button.dataset.collShareItem );
				body.set( 'collection_id', button.dataset.collShareCollection );
				body.set( 'nonce', button.dataset.collShareNonce );
				body.set( 'shared', selected ? '1' : '0' );
				var response = await fetch( button.dataset.collShareUrl, { method: 'POST', body: body, credentials: 'same-origin', signal: controller.signal } );
				var result = await response.json();
				replied = result && typeof result.success === 'boolean';
				if ( ! response.ok || ! result.success ) throw new Error( typeof result.data === 'string' ? result.data : button.dataset.collShareError );
				updatePublicCount( button.dataset.collShareCollection, Number( result.data.selected ) - Number( button.getAttribute( 'aria-pressed' ) === 'true' ), false );
				button.setAttribute( 'aria-pressed', result.data.selected ? 'true' : 'false' );
				updateShareWarning( button, result.data );
				label.textContent = result.data.label;
				message.textContent = result.data.message;
			} catch ( error ) {
				label.textContent = replied ? previousLabel : button.dataset.collShareUnknown;
				if ( ! replied ) button.setAttribute( 'aria-pressed', 'mixed' );
				message.textContent = ! replied ? button.dataset.collShareError : ( error.message || button.dataset.collShareError );
				message.classList.remove( 'screen-reader-text' );
			} finally {
				clearTimeout( timeout );
				button.disabled = ! replied;
				button.removeAttribute( 'aria-busy' );
			}
		} );
	} );

	document.querySelectorAll( '[data-coll-share-all]' ).forEach( function ( button ) {
		button.hidden = false;
	} );
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-coll-share-all]' );
		if ( ! button || button.disabled ) return;
		var root = button.closest( '.coll-share-bulk' );
		var controls = root.querySelectorAll( 'button' );
		var message = root.querySelector( '.coll-share-bulk-message' );
		controls.forEach( function ( control ) { control.disabled = true; } );
		root.setAttribute( 'aria-busy', 'true' );
		shareQueue = shareQueue.then( async function () {
			var replied = false;
			var controller = new AbortController();
			var timeout = setTimeout( function () { controller.abort(); }, 20000 );
			try {
				var body = new FormData();
				body.set( 'action', 'coll_share_collection' );
				body.set( 'collection_id', root.dataset.collShareCollection );
				body.set( 'nonce', root.dataset.collShareNonce );
				body.set( 'shared', button.dataset.collShareAll );
				var response = await fetch( root.dataset.collShareUrl, { method: 'POST', body: body, credentials: 'same-origin', signal: controller.signal } );
				var result = await response.json();
				replied = result && typeof result.success === 'boolean';
				if ( ! response.ok || ! result.success ) throw new Error( typeof result.data === 'string' ? result.data : root.dataset.collShareError );
				document.querySelectorAll( '[data-coll-share-item]' ).forEach( function ( card ) {
					if ( card.dataset.collShareCollection !== root.dataset.collShareCollection ) return;
					var state = result.data[ card.dataset.collShareItem ];
					if ( ! state ) return;
					card.setAttribute( 'aria-pressed', state.selected ? 'true' : 'false' );
					updateShareWarning( card, state );
					card.querySelector( '[data-coll-share-label]' ).textContent = state.label;
					card.disabled = ! state.eligible && ! state.selected;
				} );
				var total = Object.keys( result.data ).filter( function ( id ) { return result.data[id].selected && result.data[id].eligible; } ).length;
				updatePublicCount( root.dataset.collShareCollection, total, true );
				message.textContent = button.textContent;
			} catch ( error ) {
				message.textContent = replied ? error.message : root.dataset.collShareError;
				message.classList.remove( 'screen-reader-text' );
			} finally {
				clearTimeout( timeout );
				controls.forEach( function ( control ) { control.disabled = ! replied; } );
				root.removeAttribute( 'aria-busy' );
			}
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		document.querySelectorAll( '.coll-actions-menu[open]' ).forEach( function ( menu ) {
			if ( ! menu.contains( event.target ) ) menu.open = false;
		} );
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) return;
		document.querySelectorAll( '.coll-actions-menu[open]' ).forEach( function ( menu ) {
			menu.open = false;
			menu.querySelector( 'summary' ).focus();
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		var confirmButton = event.target.closest( '[data-coll-confirm]' );

		if ( confirmButton && ! window.confirm( confirmButton.getAttribute( 'data-coll-confirm' ) ) ) {
			event.preventDefault();
			return;
		}

		var addLotButton = event.target.closest( '[data-coll-add-lot]' );
		var root = addLotButton ? addLotButton.closest( '[data-coll-lots]' ) : null;
		var rows = root ? root.querySelector( '.lot-rows' ) : null;

		if ( ! addLotButton || ! rows ) {
			return;
		}

		var all = rows.querySelectorAll( '.lot-row' );
		var copy = all[ all.length - 1 ].cloneNode( true );
		var index = all.length;

		copy.querySelectorAll( 'input, select' ).forEach( function ( control ) {
			control.name = control.name.replace( /\[\d+\]/, '[' + index + ']' );
			control.value = '';
		} );

		rows.appendChild( copy );
		copy.querySelector( 'input, select' ).focus();
	} );

	document.querySelectorAll( '[data-coll-lots]' ).forEach( function ( root ) {
		var button = root.querySelector( '[data-coll-add-lot]' );

		if ( button ) {
			button.hidden = false;
		}
	} );

	document.querySelectorAll( '[data-coll-map-steps]' ).forEach( function ( panel ) {
		var steps = parseJSON( panel.getAttribute( 'data-coll-map-steps' ) );
		var links = parseJSON( panel.getAttribute( 'data-coll-map-links' ) );
		var map = panel.querySelector( '.origins-map' );

		if ( ! map ) {
			return;
		}

		map.querySelectorAll( 'path[data-code]' ).forEach( function ( path ) {
			var code = path.getAttribute( 'data-code' );
			var step = parseInt( steps[ code ] || '0', 10 );

			if ( step >= 1 && step <= 5 ) {
				path.classList.add( 'coll-map-step-' + step );
			}

			if ( links[ code ] ) {
				path.classList.add( 'coll-map-active' );
			}
		} );

		map.addEventListener( 'click', function ( event ) {
			var path = event.target.closest( 'path[data-code]' );
			var href = path && links[ path.getAttribute( 'data-code' ) ];

			if ( href ) {
				window.location.href = href;
			}
		} );
	} );
} )();
