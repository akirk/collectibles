/* Run with npm --prefix tests test. Real in-memory DOM; no network or WordPress. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const { JSDOM } = require( 'jsdom' );
const { createHash, webcrypto } = require( 'node:crypto' );
const dom = new JSDOM( `<form data-coll-item-form data-coll-source-private="Private until changed" data-coll-source-confirmed="I’ve verified this"></form>`, { runScripts: 'outside-only' } );
const { window } = dom;
const { document, Event } = window;
Object.defineProperty( window, 'crypto', { value: webcrypto } );
window.TextEncoder = TextEncoder;
// Reuse the existing no-network contract: source feedback must remain local.
window.fetch = () => { throw new Error( 'Live status must never make a network request.' ); };
function fixture( key, name, value, type = 'text' ) {
    const field = document.createElement( 'div' );
    const hash = createHash( 'sha256' ).update( value ).digest( 'hex' );
    field.innerHTML = `<input name="${name}" value="${value}" type="${type}">
        <span class="coll-numista-controls"><button type="button" data-coll-numista-key="${key}" data-coll-source-input="${name}" data-state="private" data-coll-source-hash="${hash}" data-coll-source-released="false" aria-pressed="false">Private until changed</button>
        <button type="button" data-coll-clear-source="${key}">Clear</button></span>
        <input type="checkbox" name="coll_numista_own[]" value="${key}" data-coll-numista-own>
        <input type="hidden" name="coll_numista_private[]" value="${key}" data-coll-numista-private disabled>`;
    document.querySelector( 'form' ).append( field );
    return { input: field.querySelector( 'input' ), indicator: field.querySelector( '[data-coll-numista-key]' ),
        own: field.querySelector( '[data-coll-numista-own]' ), privateChoice: field.querySelector( '[data-coll-numista-private]' ),
        clear: field.querySelector( '[data-coll-clear-source]' ), controls: field.querySelector( '.coll-numista-controls' ) };
}
const fields = [ fixture( 'title', 'coll_name', 'Catalogue title' ), fixture( 'year', 'coll_field_year', '1981', 'number' ) ];
window.eval( fs.readFileSync( require.resolve( '../assets/collectibles.js' ), 'utf8' ) );
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
	assert.equal( document.activeElement, year.input );
	edit( year, '1982' ); await settle();
	assert.equal( year.controls.hidden, false, 'Entering another value must restore the source controls.' );
	assert.equal( year.indicator.getAttribute( 'aria-pressed' ), 'true' );
	year.indicator.dataset.collSourceReleased = 'true';
	edit( year, '1981' ); await settle();
	assert.equal( year.indicator.dataset.state, 'confirmed', 'Already released fields must remain confirmed after reload.' );
	year.indicator.click();
	assert.equal( year.indicator.dataset.state, 'private', 'A saved confirmation must still be toggleable back to private.' );
	console.log( 'Client-side Numista checks passed: immediate two-way toggles, edits, normalization, clear, reload history and zero network requests.' );
})().catch( error => { console.error( error ); process.exitCode = 1; } ).finally( () => window.close() );
