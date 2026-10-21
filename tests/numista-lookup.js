/* Run with node tests/numista-lookup.js. Checks preservation across catalogue submits. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
let onFormData;
const itemForm = {};
const lookup = { addEventListener: ( name, callback ) => {
	assert.equal( name, 'formdata' );
	onFormData = callback;
} };
const values = [
	[ 'coll_name', 'My typed title' ],
	[ 'coll_field_year', '1984' ],
	[ 'coll_notes', 'Personal notes' ],
	[ 'coll_numista_source', 'signed-original-source' ],
	[ 'coll_lot[0][quantity]', '2' ],
	[ 'coll_action', 'create_item' ],
	[ 'coll_nonce', 'save-nonce' ],
	[ '_wp_http_referer', '/previous-form' ],
	[ 'coll_photo_front', { name: 'photo.jpg' } ]
];
const document = {
	querySelectorAll: selector => selector === '[data-coll-numista-lookup]' ? [ lookup ] : [],
	querySelector: selector => selector === '[data-coll-item-form]' ? itemForm : null,
	addEventListener: () => {}
};
class FormData {
	constructor( form ) { assert.equal( form, itemForm ); }
	forEach( callback ) { values.forEach( ( [ name, value ] ) => callback( value, name ) ); }
}
vm.runInNewContext( fs.readFileSync( require.resolve( '../assets/collectibles.js' ), 'utf8' ), { document, FormData, Promise } );
const submitted = new Map( [ [ 'coll_nonce', 'lookup-nonce' ], [ 'coll_action', 'numista_lookup' ], [ 'coll_numista_issue', '42' ] ] );
onFormData( { formData: { append: ( name, value ) => {
	assert.ok( ! submitted.has( name ), 'Item form must not replace lookup controls.' );
	submitted.set( name, value );
} } } );
assert.equal( submitted.get( 'coll_name' ), 'My typed title' );
assert.equal( submitted.get( 'coll_field_year' ), '1984' );
assert.equal( submitted.get( 'coll_notes' ), 'Personal notes' );
assert.equal( submitted.get( 'coll_numista_source' ), 'signed-original-source' );
assert.equal( submitted.get( 'coll_lot[0][quantity]' ), '2' );
assert.equal( submitted.get( 'coll_nonce' ), 'lookup-nonce' );
assert.equal( submitted.get( 'coll_action' ), 'numista_lookup' );
assert.equal( submitted.get( 'coll_numista_issue' ), '42' );
assert.ok( ! submitted.has( 'coll_photo_front' ) );
assert.ok( ! submitted.has( '_wp_http_referer' ) );
console.log( 'Numista lookup input preservation checks passed.' );
