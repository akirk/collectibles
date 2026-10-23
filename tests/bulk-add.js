/* Run with node tests/bulk-add.js. No network or API calls. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
function node() {
	return { children: [], handlers: {}, dataset: {}, style: {}, value: '',
		appendChild( child ) { this.children.push( child ); },
		addEventListener( event, handler ) { this.handlers[event] = handler; },
		setAttribute() {}, focus() {}, remove() {} };
}
const lookup = node(), controls = node(), toggle = node(), input = node(), results = node(), ordinary = node();
controls.dataset = { loading: 'Queued', save: 'Add', saving: 'Saving', saved: 'Saved', error: 'Failed' };
controls.querySelector = () => toggle;
lookup.querySelector = selector => selector.includes( 'controls' ) ? controls : input;
lookup.action = '/new';
const window = { fetch: true, addEventListener() {} };
let requests = [], active = 0, maximum = 0;
let returnedForm = null;
let lastBody;
const fields = { name: { value: 'Original set' }, id: { value: '789-1' }, quantity: { value: '1' } };
const editable = node();
editable.querySelector = selector => selector.includes( 'rebrickable_id' ) ? fields.id : fields.name;
editable.querySelectorAll = () => [];
editable.reportValidity = () => true;
editable.requestSubmit = () => editable.handlers.submit( { preventDefault() {} } );
const document = {
	querySelector: selector => selector.includes( 'lookup' ) ? lookup : selector.includes( 'results' ) ? results : ordinary,
	createElement: () => node()
};
const source = fs.readFileSync( require.resolve( '../assets/collectibles.js' ), 'utf8' ).split( '\n( function () {' ).pop();
vm.runInNewContext( '( function () {' + source, {
	document, window, FormData: class {
		constructor( form ) {
			this.ref = input.value;
			if ( form === editable ) { this.name = fields.name.value; this.quantity = fields.quantity.value; }
		}
		set( key, value ) { this[key] = value; }
	},
	fetch: ( url, options ) => {
		lastBody = options.body;
		active++; maximum = Math.max( maximum, active );
		return new Promise( resolve => requests.push( () => { active--; resolve( { ok: true, text: async () => options.body.ref, json: async () => ( { success: true, data: { url: '/saved' } } ) } ); } ) );
	},
	DOMParser: class { parseFromString() { return { querySelector: selector => selector === '[data-coll-item-form]' ? returnedForm : null }; } }
} );
const tick = () => new Promise( resolve => setImmediate( resolve ) );
( async () => {
	toggle.checked = true;
	toggle.handlers.change();
	assert.equal( ordinary.hidden, true );
	for ( const ref of [ '123', '456' ] ) {
		input.value = ref;
		lookup.handlers.submit( { preventDefault() {} } );
		assert.equal( input.value, '' );
	}
	await tick();
	assert.equal( requests.length, 1, 'only first lookup starts' );
	requests[0]();
	await tick();
	assert.equal( requests.length, 2, 'failure does not block next lookup' );
	requests[1]();
	await tick();
	assert.equal( maximum, 1, 'requests never overlap' );
	assert.equal( results.children.length, 2 );
	assert.equal( results.children[0].children[1].textContent, 'Failed' );
	returnedForm = editable;
	input.value = '789';
	lookup.handlers.submit( { preventDefault() {} } );
	await tick();
	requests[2]();
	await tick();
	const card = results.children[2];
	const details = card.children.find( child => child.children.includes( editable ) );
	assert.ok( details, 'the normal form is mounted for refinement' );
	fields.name.value = 'My refined set';
	fields.quantity.value = '3';
	card.children.at( -1 ).handlers.click();
	await tick();
	assert.equal( lastBody.name, 'My refined set' );
	assert.equal( lastBody.quantity, '3' );
	assert.equal( lastBody.coll_bulk_save, '1' );
	requests[3]();
	await tick();
	assert.equal( card.children.at( -1 ).href, '/saved' );
	assert.equal( maximum, 1 );
	console.log( 'Bulk add queue and refinement checks passed.' );
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
