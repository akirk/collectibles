/* Run with node tests/comparison.js. No network or browser required. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const item = (title, catalog, year = '2000') => ({ title, fields: [
	{ key: 'catalog_number', raw: catalog }, { key: 'origin_code', raw: 'at' }, { key: 'year', raw: year }
] });
const nodes = new Map();
const node = id => {
	if (!nodes.has(id)) nodes.set(id, { textContent: '', children: [], appendChild(child) { this.children.push(child); }, replaceChildren() { this.children = []; } });
	return nodes.get(id);
};
const local = { version: 1, kind: 'coins', items: [item('Local title', 'KM 1'), item('Local only', 'KM 2'), item('Title match', '')] };
node('coll-compare-data').textContent = JSON.stringify(local);
node('coll-public-items').children = local.items.map((item, index) => ({ original: index }));
node('coll-compare-labels').textContent = JSON.stringify({ both: 'Both', local: 'Local', remote: 'Remote', loading: 'loading', done: 'done', error: 'error', kind: 'kind' });
node('coll-compare-url').value = 'https://other.example/collectibles/test/';
let submit;
node('coll-compare-form').addEventListener = (name, callback) => { submit = callback; };
node('coll-compare-form').querySelector = () => node('button');
let remote = { version: 1, kind: 'coins', items: [item('Different title', 'km 1'), item('<script>remote only</script>', 'KM 3'), item(' title MATCH ', '')] };
remote.items[1].url = 'javascript:alert(1)';
remote.items[1].photos = [{ thumbnail: 'javascript:alert(1)' }];
let requested;
vm.runInNewContext(fs.readFileSync('assets/compare.js', 'utf8'), {
	document: { getElementById: node, createElement: () => ({ setAttribute(name, value) { this[name] = value; }, textContent: '', children: [], appendChild(child) { this.children.push(child); } }) },
	URL, AbortController, setTimeout, clearTimeout,
	fetch: async (url, options) => { requested = { url, options }; return { ok: true, json: async () => remote }; }
});
(async () => {
	const comparison = submit({ preventDefault() {} });
	assert.equal(node('coll-compare-message').textContent, 'loading');
	assert.equal(node('button').disabled, true);
	await comparison;
	assert.equal(node('coll-compare-message').textContent, '');
	assert.equal(node('button').disabled, false);
	assert.equal(requested.url, 'https://other.example/collectibles/test/?format=json');
	assert.equal(requested.options.credentials, 'omit');
	assert.equal(requested.options.referrerPolicy, 'no-referrer');
	const grouped = node('coll-public-items').children;
	assert.equal(grouped[0].className, 'coll-comparison-toc');
	assert.deepEqual(grouped[0].children.slice(1).map(link => link.href), ['#coll-comparison-both', '#coll-comparison-local', '#coll-comparison-remote']);
	assert.equal(grouped[1].id, 'coll-comparison-both');
	assert.deepEqual(grouped.filter(child => child.className === 'coll-comparison-heading').map(child => child.textContent), ['Both (2)', 'Local (1)', 'Remote (1)']);
	assert.equal(grouped[2].original, 0);
	assert.equal(grouped[3].original, 2);
	assert.equal(grouped[5].original, 1);
	const remoteCard = grouped[7];
	assert.equal(remoteCard.className, 'coll-public-card');
	assert.equal(remoteCard.children[0].children[0].children[0].href, undefined);
	assert.equal(remoteCard.children[0].children[0].children[0].children[0].className, 'kind-icon');
	assert.equal(remoteCard.children[0].children[1].children[0].children[0].children[0].textContent, '<script>remote only</script>');
	// A repeated comparison must reuse the original cards, without duplicating headings.
	await submit({ preventDefault() {} });
	assert.equal(node('coll-public-items').children.length, 8);
	remote.kind = 'banknotes';
	await submit({ preventDefault() {} });
	assert.equal(node('coll-compare-message').textContent, 'kind');
	assert.equal(node('coll-public-items').children.length, 8);
	remote = { items: [{ title: 'broken' }] };
	await submit({ preventDefault() {} });
	assert.equal(node('coll-compare-message').textContent, 'error');
	assert.equal(node('button').disabled, false);
	// Private comparison cards must not expose the expanded public detail/photo block.
	node('coll-compare-form').dataset = { private: '1' };
	node('coll-compare-prompt').addEventListener = () => {};
	node('coll-public-items').replaceChildren();
	remote = { version: 1, kind: 'coins', items: [item('Remote', 'KM 3')] };
	remote.items[0].photos = [{ thumbnail: 'https://example.org/photo.jpg' }];
	vm.runInNewContext(fs.readFileSync('assets/compare.js', 'utf8'), {
		document: { getElementById: node, createElement: () => ({ setAttribute(name, value) { this[name] = value; }, textContent: '', children: [], appendChild(child) { this.children.push(child); } }) },
		URL, AbortController, setTimeout, clearTimeout,
		fetch: async () => ({ ok: true, json: async () => remote })
	});
	await submit({ preventDefault() {} });
	const privateCards = node('coll-public-items').children.filter(child => child.className === 'coll-public-card');
	assert.equal(privateCards.length, 4);
	privateCards.forEach(wrapper => {
		const body = wrapper.children[0].children[1];
		assert.equal(body.children.some(child => child.className === 'coll-public-expanded'), false);
	});
	console.log('Client comparison checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
