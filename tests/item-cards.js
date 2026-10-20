/* Run with node tests/item-cards.js. Exercises the shared photo control. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const image = { src: '/front.jpg' };
const attributes = { 'aria-pressed': 'false' };
let click;
const button = {
	disabled: true,
	dataset: { collFront: '/front.jpg', collBack: '/back.jpg', collShowFront: 'Show front', collShowBack: 'Show back', collTitle: 'Banknote' },
	getAttribute: name => attributes[name],
	setAttribute: ( name, value ) => { attributes[name] = value; },
	closest: selector => selector === '[data-coll-flip]' ? button : { querySelector: () => image }
};
const document = {
	querySelectorAll: () => [ button ],
	addEventListener: ( type, handler ) => { click = handler; }
};
vm.runInNewContext( fs.readFileSync( require.resolve( '../assets/item-cards.js' ), 'utf8' ), { document } );
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
console.log( 'Passed 9 card photo checks.' );
