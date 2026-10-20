( function () {
	document.querySelectorAll( '[data-coll-flip]' ).forEach( function ( button ) {
		button.disabled = false;
		var image = button.querySelector( 'img' );
		if ( ! image ) return;
		function positionCurl() {
			if ( ! image.naturalWidth || ! image.naturalHeight ) return;
			var frame = button.getBoundingClientRect();
			var box = image.getBoundingClientRect();
			if ( ! box.width || ! box.height ) return;
			var scale = Math.min( box.width / image.naturalWidth, box.height / image.naturalHeight );
			var width = image.naturalWidth * scale;
			var height = image.naturalHeight * scale;
			button.style.setProperty( '--coll-image-inset-x', ( ( box.width - width ) / 2 ) + 'px' );
			button.style.setProperty( '--coll-image-inset-y', ( ( box.height - height ) / 2 ) + 'px' );
			button.style.setProperty( '--coll-curl-right', ( frame.right - box.left - ( box.width + width ) / 2 ) + 'px' );
			button.style.setProperty( '--coll-curl-bottom', ( frame.bottom - box.top - ( box.height + height ) / 2 ) + 'px' );
			button.setAttribute( 'data-coll-curl-ready', '' );
		}
		image.addEventListener( 'load', positionCurl );
		if ( typeof ResizeObserver !== 'undefined' ) {
			var observer = new ResizeObserver( positionCurl );
			observer.observe( button );
			observer.observe( image );
		} else {
			window.addEventListener( 'resize', positionCurl );
		}
		positionCurl();
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
