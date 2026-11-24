/* Run with npm --prefix tests test. Real DOM and the existing controlled AJAX response seam. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const { JSDOM } = require( 'jsdom' );
let checks = 0;
function check( condition, message ) { assert.ok( condition, message ); checks++; }
const dom = new JSDOM( `<details class="coll-actions-menu"><summary>Actions</summary></details>
    <span data-coll-public-count="10" data-coll-count="0">0</span>
    <div class="coll-share-bulk" data-coll-share-collection="10" data-coll-share-nonce="bulk-nonce" data-coll-share-url="/admin-ajax.php" data-coll-share-error="Reload bulk">
      <button data-coll-share-all="1" hidden>Share all</button><button data-coll-share-all="0" hidden>Make all private</button>
      <span class="coll-share-bulk-message"></span>
    </div>`, { runScripts: 'outside-only' } );
const { window } = dom;
const { document } = window;
function card( id ) {
    const root = document.createElement( 'div' );
    root.className = 'item-card-body';
    root.innerHTML = `<button data-coll-share-item="${id}" data-coll-share-collection="10" data-coll-share-nonce="nonce-${id}" data-coll-share-url="/admin-ajax.php" data-coll-share-title="Item ${id}" data-coll-share-error="Reload and retry" data-coll-share-saving="Saving" data-coll-share-unknown="Check sharing" aria-pressed="false" hidden>
      <span data-coll-share-label>Private</span><span data-coll-share-warning hidden></span>
    </button><span class="coll-share-message screen-reader-text"></span>`;
    document.body.append( root );
    const message = root.querySelector( '.coll-share-message' );
    return { button: root.querySelector( 'button' ), label: root.querySelector( '[data-coll-share-label]' ),
        warning: root.querySelector( '[data-coll-share-warning]' ), message, classes: message.classList };
}
const cards = [ card( 1 ), card( 2 ) ];
const requests = [];
const pending = [];
const menu = document.querySelector( 'details' );
const bulkMessage = document.querySelector( '.coll-share-bulk-message' );
const bulkButtons = Array.from( document.querySelectorAll( '[data-coll-share-all]' ) );
const publicCount = document.querySelector( '[data-coll-public-count]' );
// Same response seam as before: no request leaves this process.
window.fetch = ( url, options ) => {
    requests.push( { url, options } );
    return new Promise( ( resolve, reject ) => pending.push( { resolve, reject } ) );
};
window.eval( fs.readFileSync( require.resolve( '../assets/collectibles.js' ), 'utf8' ) );
function click( index ) { cards[ index ].button.click(); }
function response( selected, label, warning = '' ) { return { ok: true, json: async () => ( { success: true, data: { selected, label, eligible: true, action: selected ? 'Remove' : 'Include', warning, message: label } } ) }; }
const tick = () => new Promise( resolve => setImmediate( resolve ) );
( async () => {
	check( cards.every( c => ! c.button.hidden ), 'JavaScript reveals the AJAX controls.' );
	click( 0 ); click( 1 );
	check( cards.every( c => c.button.disabled && c.label.textContent === 'Saving' ), 'Pending controls show their busy state.' );
	await tick();
	check( requests.length === 1, 'Requests for different cards are serialized.' );
	check( requests[0].options.method === 'POST' && requests[0].options.credentials === 'same-origin' && requests[0].options.body.get( 'nonce' ) === 'nonce-1' && requests[0].options.body.get( 'shared' ) === '1', 'AJAX sends an absolute selection with the item nonce and cookies.' );
	pending[0].resolve( response( true, 'Shared', 'Title is private' ) );
	await tick();
	check( publicCount.textContent === '1', 'Sharing an item updates the public stat.' );
	check( requests.length === 2 && cards[0].button.getAttribute( 'aria-pressed' ) === 'true' && cards[0].label.textContent === 'Shared' && ! cards[0].button.disabled, 'The saved state appears after the server confirms it.' );
	check( ! cards[0].warning.hidden && cards[0].button.title === 'Title is private', 'Individual sharing updates preserve the private-title warning and short tooltip.' );
	pending[1].resolve( { ok: false, json: async () => ( { success: false, data: 'Permission denied' } ) } );
	await tick();
	check( cards[1].label.textContent === 'Private' && ! cards[1].button.disabled && cards[1].button.getAttribute( 'aria-pressed' ) === 'false', 'A rejected request preserves the previous selection.' );
	check( cards[1].message.textContent === 'Permission denied' && ! cards[1].classes.contains( 'screen-reader-text' ), 'Server errors are visible and announced.' );
	click( 1 ); await tick();
	pending[2].reject( new Error( 'Network failed' ) );
	await tick();
	check( cards[1].button.disabled && cards[1].label.textContent === 'Check sharing' && cards[1].button.getAttribute( 'aria-pressed' ) === 'mixed' && cards[1].message.textContent === 'Reload and retry', 'A lost response does not falsely claim the item stayed private.' );
	click( 0 ); await tick();
	check( requests.length === 4 && requests[3].options.body.get( 'shared' ) === '0', 'An earlier failure does not block later unsharing requests.' );
	pending[3].resolve( response( false, 'Private' ) ); await tick();
	check( publicCount.textContent === '0', 'Unsharing an item updates the public stat.' );
	check( cards[0].button.getAttribute( 'aria-pressed' ) === 'false' && cards[0].label.textContent === 'Private', 'Switch-off displays the confirmed private state.' );
	menu.open = true;
	document.dispatchEvent( new window.KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
	check( ! menu.open && document.activeElement === menu.querySelector( 'summary' ), 'Escape closes the actions dropdown and restores focus.' );
	menu.open = true; document.body.click();
	check( ! menu.open, 'Clicking outside closes the actions dropdown.' );

	check( bulkButtons.every( b => ! b.hidden ), 'JavaScript reveals bulk controls.' );
	bulkButtons[0].click();
	await tick();
	check( bulkButtons.every( b => b.disabled ) && requests[4].options.body.get( 'action' ) === 'coll_share_collection' && requests[4].options.body.get( 'nonce' ) === 'bulk-nonce', 'Bulk requests use the collection nonce and disable both controls.' );
	pending[4].resolve( { ok: true, json: async () => ( { success: true, data: { 1: { selected: true, eligible: true, action: 'Remove', label: 'Shared', message: 'Shared', warning: '' }, 2: { selected: true, eligible: true, action: 'Remove', label: 'Shared', message: 'Shared', warning: 'Title is private' } } } ) } );
	await tick();
	check( publicCount.textContent === '2', 'Bulk sharing updates the public stat.' );
	check( cards.every( c => c.label.textContent === 'Shared' && c.button.getAttribute( 'aria-pressed' ) === 'true' ) && bulkButtons.every( b => ! b.disabled ), 'Bulk success updates every visible switch without reloading.' );
	check( cards[0].warning.hidden && ! cards[1].warning.hidden && cards[1].button.title === 'Title is private', 'Bulk sharing updates each title warning independently.' );
	bulkButtons[1].click();
	await tick();
	pending[5].reject( new Error( 'Network failed' ) );
	await tick();
	check( bulkButtons.every( b => b.disabled ) && bulkMessage.textContent === 'Reload bulk', 'Lost bulk responses require checking the saved state.' );
	console.log( 'Passed ' + checks + ' sharing UI checks.' );
} )().catch( error => { console.error( error ); process.exitCode = 1; } ).finally( () => window.close() );
