/* Run with node tests/sharing-controls.js. Uses the real UI script and a small DOM fixture. */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
let checks = 0;
function check( condition, message ) { assert.ok( condition, message ); checks++; }
function card( id ) {
	const attributes = { 'aria-pressed': 'false' };
	const label = { textContent: 'Private' };
	const classes = new Set( [ 'screen-reader-text' ] );
	const message = { textContent: '', classList: { add: x => classes.add( x ), remove: x => classes.delete( x ) } };
	const button = {
		dataset: { collShareItem: String( id ), collShareCollection: '10', collShareNonce: 'nonce-' + id, collShareUrl: '/admin-ajax.php', collShareTitle: 'Item ' + id, collShareError: 'Reload and retry', collShareSaving: 'Saving', collShareUnknown: 'Check sharing' },
		hidden: true, disabled: false,
		getAttribute: name => attributes[ name ],
		setAttribute: ( name, value ) => { attributes[ name ] = value; },
		removeAttribute: name => { delete attributes[ name ]; },
		querySelector: () => label,
		closest: selector => selector === '[data-coll-share-item]' ? button : ( selector === '.item-card-body' ? { querySelector: () => message } : null )
	};
	return { button, label, message, classes };
}
const cards = [ card( 1 ), card( 2 ) ];
const handlers = {};
const requests = [];
const pending = [];
let focused = false;
const menu = { open: false, contains: () => false, querySelector: () => ( { focus: () => { focused = true; } } ) };
const document = {
	querySelectorAll: selector => selector === '[data-coll-share-item]' ? cards.map( c => c.button ) : ( selector === '.coll-actions-menu[open]' && menu.open ? [ menu ] : [] ),
	addEventListener: ( type, callback ) => { ( handlers[ type ] ||= [] ).push( callback ); }
};
const context = { document, FormData, AbortController, setTimeout, clearTimeout, Promise, JSON,
	fetch: ( url, options ) => {
		requests.push( { url, options } );
		return new Promise( ( resolve, reject ) => pending.push( { resolve, reject } ) );
	}
};
vm.runInNewContext( fs.readFileSync( require.resolve( '../assets/collectibles.js' ), 'utf8' ), context );
function click( index ) { handlers.click.forEach( callback => callback( { target: cards[ index ].button } ) ); }
function response( selected, label ) { return { ok: true, json: async () => ( { success: true, data: { selected, label, eligible: true, action: selected ? 'Remove' : 'Include', message: label } } ) }; }
const tick = () => new Promise( resolve => setImmediate( resolve ) );
( async () => {
	check( cards.every( c => ! c.button.hidden ), 'JavaScript reveals the AJAX controls.' );
	click( 0 ); click( 1 );
	check( cards.every( c => c.button.disabled && c.label.textContent === 'Saving' ), 'Pending controls show their busy state.' );
	await tick();
	check( requests.length === 1, 'Requests for different cards are serialized.' );
	check( requests[0].options.method === 'POST' && requests[0].options.credentials === 'same-origin' && requests[0].options.body.get( 'nonce' ) === 'nonce-1' && requests[0].options.body.get( 'shared' ) === '1', 'AJAX sends an absolute selection with the item nonce and cookies.' );
	pending[0].resolve( response( true, 'Shared' ) );
	await tick();
	check( requests.length === 2 && cards[0].button.getAttribute( 'aria-pressed' ) === 'true' && cards[0].label.textContent === 'Shared' && ! cards[0].button.disabled, 'The saved state appears after the server confirms it.' );
	pending[1].resolve( { ok: false, json: async () => ( { success: false, data: 'Permission denied' } ) } );
	await tick();
	check( cards[1].label.textContent === 'Private' && ! cards[1].button.disabled && cards[1].button.getAttribute( 'aria-pressed' ) === 'false', 'A rejected request preserves the previous selection.' );
	check( cards[1].message.textContent === 'Permission denied' && ! cards[1].classes.has( 'screen-reader-text' ), 'Server errors are visible and announced.' );
	click( 1 ); await tick();
	pending[2].reject( new Error( 'Network failed' ) );
	await tick();
	check( cards[1].button.disabled && cards[1].label.textContent === 'Check sharing' && cards[1].button.getAttribute( 'aria-pressed' ) === 'mixed' && cards[1].message.textContent === 'Reload and retry', 'A lost response does not falsely claim the item stayed private.' );
	click( 0 ); await tick();
	check( requests.length === 4 && requests[3].options.body.get( 'shared' ) === '0', 'An earlier failure does not block later unsharing requests.' );
	pending[3].resolve( response( false, 'Private' ) ); await tick();
	check( cards[0].button.getAttribute( 'aria-pressed' ) === 'false' && cards[0].label.textContent === 'Private', 'Switch-off displays the confirmed private state.' );
	menu.open = true;
	handlers.keydown.forEach( callback => callback( { key: 'Escape' } ) );
	check( ! menu.open && focused, 'Escape closes the actions dropdown and restores focus.' );
	menu.open = true; click( 1 );
	check( ! menu.open, 'Clicking outside closes the actions dropdown.' );
	console.log( 'Passed ' + checks + ' sharing UI checks.' );
} )().catch( error => { console.error( error ); process.exitCode = 1; } );
