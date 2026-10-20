/* Run with node tests/item-cards.js. Exercises the shared photo control. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
let loaded;
let resized;
let frameWidth = 300;
const image = {
	src: '/front.jpg', naturalWidth: 600, naturalHeight: 300,
	addEventListener: ( type, handler ) => { loaded = handler; },
	getBoundingClientRect: () => ( { left: 10, top: 10, width: frameWidth - 20, height: 160 } )
};
const styles = {};
const attributes = { 'aria-pressed': 'false' };
let click;
const button = {
	disabled: true,
	querySelector: () => image,
	style: { setProperty: ( name, value ) => { styles[name] = value; } },
	getBoundingClientRect: () => ( { right: frameWidth, bottom: 180 } ),
	dataset: { collFront: '/front.jpg', collBack: '/back.jpg', collShowFront: 'Show front', collShowBack: 'Show back', collTitle: 'Banknote' },
	getAttribute: name => attributes[name],
	setAttribute: ( name, value ) => { attributes[name] = value; },
	closest: selector => selector === '[data-coll-flip]' ? button : { querySelector: () => image }
};
const document = {
	querySelectorAll: () => [ button ],
	addEventListener: ( type, handler ) => { click = handler; }
};
vm.runInNewContext( fs.readFileSync( require.resolve( '../assets/item-cards.js' ), 'utf8' ), { document, ResizeObserver: class { constructor( callback ) { resized = callback; } observe() {} } } );
assert.equal( button.disabled, false, 'The image becomes interactive with JavaScript.' );
click( { target: button } );
assert.equal( image.src, '/back.jpg' );
assert.equal( attributes['aria-pressed'], 'true' );
assert.equal( attributes['aria-label'], 'Banknote: Show front' );
assert.equal( button.title, 'Show front' );
click( { target: button } );
assert.equal( image.src, '/front.jpg' );
assert.equal( attributes['aria-pressed'], 'false' );
assert.equal( attributes['aria-label'], 'Banknote: Show back' );
click( { target: { closest: () => null } } );
assert.equal( image.src, '/front.jpg', 'Other clicks leave the photo unchanged.' );
assert.equal( styles['--coll-curl-right'], '10px', 'Wide photos anchor the curl at the image edge.' );
assert.equal( styles['--coll-curl-bottom'], '20px' );
image.naturalWidth = 300;
loaded();
assert.equal( styles['--coll-curl-right'], '70px', 'Narrow photos keep the curl on the photo, not the frame.' );
assert.equal( styles['--coll-curl-bottom'], '10px' );
frameWidth = 220;
resized();
assert.equal( styles['--coll-curl-right'], '30px', 'Resizing repositions the curl.' );
image.naturalWidth = 600;
loaded();
assert.equal( styles['--coll-curl-bottom'], '40px', 'Loading the other side recalculates its proportions.' );
assert.equal( styles['--coll-image-inset-x'], '0px', 'Clipping follows the fitted horizontal image bounds.' );
assert.equal( styles['--coll-image-inset-y'], '30px', 'Clipping follows the fitted vertical image bounds.' );
console.log( 'Passed 17 card photo checks.' );
