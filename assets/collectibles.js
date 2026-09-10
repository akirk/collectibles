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
