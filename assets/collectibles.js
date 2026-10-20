( function () {
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
				button.setAttribute( 'aria-label', button.dataset.collShareTitle + ': ' + result.data.action );
				button.title = result.data.message;
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
					card.setAttribute( 'aria-label', card.dataset.collShareTitle + ': ' + state.action );
					card.title = state.message;
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
