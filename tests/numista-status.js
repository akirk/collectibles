/* Run with node tests/numista-status.js. No DOM package, network or WordPress needed. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const { createHash, webcrypto } = require( 'node:crypto' );
function node( properties = {} ) {
	const handlers = {};
	const attributes = {};
	return Object.assign( {
		dataset: {}, value: '', type: 'text', min: '', tagName: 'INPUT', textContent: '',
		addEventListener: ( name, callback ) => { ( handlers[ name ] ||= [] ).push( callback ); },
		dispatchEvent( event ) { ( handlers[ event.type ] || [] ).forEach( callback => callback( event ) ); },
		click() { this.dispatchEvent( { type: 'click' } ); },
		setAttribute: ( name, value ) => { attributes[ name ] = value; },
		getAttribute: name => attributes[ name ],
		removeAttribute: name => { delete attributes[ name ]; },
		focus() { this.focused = true; }
	}, properties );
}
function fixture( key, name, value, type = 'text' ) {
	const controls = { hidden: false };
	const input = node( { name, value, type } );
	const indicator = node( {
		dataset: { collNumistaKey: key, collSourceInput: name, state: 'private', collSourceHash: createHash( 'sha256' ).update( value ).digest( 'hex' ), collSourceReleased: 'false' },
		closest: () => controls,
		textContent: 'Private until changed'
	} );
	indicator.setAttribute( 'aria-pressed', 'false' );
	const own = node( { value: key, type: 'checkbox', name: 'coll_numista_own[]', checked: false } );
	const privateChoice = node( { value: key, type: 'hidden', name: 'coll_numista_private[]', disabled: true } );
	const clear = node( { dataset: { collClearSource: key } } );
	return { input, indicator, own, privateChoice, clear, controls };
}
const fields = [ fixture( 'title', 'coll_name', 'Catalogue title' ), fixture( 'year', 'coll_field_year', '1981', 'number' ) ];
const form = {
	dataset: { collSourcePrivate: 'Private until changed', collSourceConfirmed: 'I’ve verified this' },
	elements: { namedItem: name => fields.find( field => field.input.name === name )?.input },
	querySelectorAll: selector => {
		if ( selector === '[data-coll-numista-key]' ) return fields.map( field => field.indicator );
		if ( selector === '[data-coll-numista-own]' ) return fields.map( field => field.own );
		if ( selector === '[data-coll-numista-private]' ) return fields.map( field => field.privateChoice );
		if ( selector === '[data-coll-clear-source]' ) return fields.map( field => field.clear );
		return [];
	}
};
const document = { querySelectorAll: selector => selector === '[data-coll-item-form]' ? [ form ] : [], addEventListener: () => {} };
vm.runInNewContext( fs.readFileSync( require.resolve( '../assets/collectibles.js' ), 'utf8' ), {
	document, crypto: webcrypto, TextEncoder, Uint8Array, Event, Promise,
	fetch: () => { throw new Error( 'Live status must never make a network request.' ); }
} );
const settle = () => new Promise( resolve => setTimeout( resolve, 30 ) );
function edit( field, value ) { field.input.value = value; field.input.dispatchEvent( new Event( 'input' ) ); }
( async () => {
	const year = fields[ 1 ];
	year.indicator.click();
	assert.equal( year.indicator.textContent, 'I’ve verified this', 'Clicking must immediately confirm the unchanged value.' );
	assert.equal( year.indicator.getAttribute( 'aria-pressed' ), 'true' );
	assert.equal( year.own.checked, true );
	year.indicator.click();
	assert.equal( year.indicator.textContent, 'Private until changed' );
	assert.equal( year.own.checked, false );
	assert.equal( year.privateChoice.disabled, false, 'Toggling back must submit an explicit private choice.' );
	edit( year, '1982' ); await settle();
	assert.equal( year.indicator.dataset.state, 'updated' );
	assert.equal( year.privateChoice.disabled, true, 'A new edit must release the previous private baseline choice.' );
	year.indicator.click();
	assert.equal( year.indicator.dataset.state, 'private', 'Edited values must be toggleable back to private too.' );
	year.indicator.click();
	assert.equal( year.indicator.dataset.state, 'confirmed' );
	edit( year, '1981.0000' ); await settle();
	assert.equal( year.indicator.dataset.state, 'private', 'Numeric formatting alone must not count as a change.' );
	edit( fields[ 0 ], 'Catalogue title  ' ); await settle();
	assert.equal( fields[ 0 ].indicator.dataset.state, 'private', 'Whitespace alone must not count as a change.' );
	edit( year, '1983' ); edit( year, '1981' ); await settle();
	assert.equal( year.indicator.dataset.state, 'private', 'Stale hashing results must not overwrite a newer edit.' );
	year.indicator.click();
	year.clear.click();
	assert.equal( year.input.value, '' );
	assert.equal( year.own.checked, false );
	assert.equal( year.indicator.dataset.state, 'cleared' );
	assert.equal( year.controls.hidden, true, 'Cleared fields must not retain a private label or clear button.' );
	assert.equal( year.input.focused, true );
	edit( year, '1982' ); await settle();
	assert.equal( year.controls.hidden, false, 'Entering another value must restore the source controls.' );
	assert.equal( year.indicator.getAttribute( 'aria-pressed' ), 'true' );
	year.indicator.dataset.collSourceReleased = 'true';
	edit( year, '1981' ); await settle();
	assert.equal( year.indicator.dataset.state, 'confirmed', 'Already released fields must remain confirmed after reload.' );
	year.indicator.click();
	assert.equal( year.indicator.dataset.state, 'private', 'A saved confirmation must still be toggleable back to private.' );
	console.log( 'Client-side Numista checks passed: immediate two-way toggles, edits, normalization, clear, reload history and zero network requests.' );
})().catch( error => { console.error( error ); process.exitCode = 1; } );
