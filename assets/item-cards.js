( function () {
	document.querySelectorAll( '[data-coll-flip]' ).forEach( function ( button ) {
		button.disabled = false;
	} );
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-coll-flip]' );
		if ( ! button || button.disabled ) return;
		var image = button.closest( '.item-card-image' ).querySelector( 'img' );
		if ( ! image ) return;
		var back = button.getAttribute( 'aria-pressed' ) !== 'true';
		image.src = back ? button.dataset.collBack : button.dataset.collFront;
		button.setAttribute( 'aria-pressed', back ? 'true' : 'false' );
		var action = back ? button.dataset.collShowFront : button.dataset.collShowBack;
		button.title = action;
		button.setAttribute( 'aria-label', button.dataset.collTitle + ': ' + action );
	} );
} )();
