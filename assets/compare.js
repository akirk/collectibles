/* Public collection data is fetched directly by the browser, without credentials. */
(function () {
	'use strict';
	const form = document.getElementById('coll-compare-form');
	if (!form) return;
	const local = JSON.parse(document.getElementById('coll-compare-data').textContent);
	const labels = JSON.parse(document.getElementById('coll-compare-labels').textContent);
	const message = document.getElementById('coll-compare-message');
	const grid = document.getElementById('coll-public-items');
	const originalCards = Array.from(grid.children);
	const element = (tag, className, text) => {
		const node = document.createElement(tag);
		if (className) node.className = className;
		if (text !== undefined) node.textContent = text;
		return node;
	};
	const safeUrl = value => {
		try {
			const url = new URL(value);
			return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password ? url.href : '';
		} catch (error) { return ''; }
	};
	const remoteCard = item => {
		const wrapper = element('div', 'coll-public-card');
		const card = element('article', 'item-card');
		const imageBox = element('div', 'item-card-image');
		const thumb = element('a', 'item-card-thumb');
		const url = safeUrl(item.url);
		if (url) thumb.href = url;
		thumb.referrerPolicy = 'no-referrer';
		const photo = (Array.isArray(item.photos) ? item.photos : []).find(photo => photo && safeUrl(photo.thumbnail || photo.image));
		if (photo) {
			const image = element('img');
			image.src = safeUrl(photo.thumbnail || photo.image);
			image.alt = '';
			image.loading = 'lazy';
			image.referrerPolicy = 'no-referrer';
			thumb.appendChild(image);
		} else {
			thumb.className += ' is-empty';
			thumb.appendChild(element('span', 'kind-icon', local.icon || ''));
		}
		imageBox.appendChild(thumb);
		card.appendChild(imageBox);
		const body = element('div', 'item-card-body');
		const heading = element('div', 'item-card-heading');
		const title = element('h3');
		const link = element('a', '', item.title);
		if (url) link.href = url;
		link.referrerPolicy = 'no-referrer';
		title.appendChild(link);
		heading.appendChild(title);
		body.appendChild(heading);
		const expanded = element('div', 'coll-public-expanded');
		const fields = element('dl', 'coll-public-fields');
		item.fields.forEach(field => {
			const row = element('div');
			row.appendChild(element('dt', '', typeof field.label === 'string' ? field.label : field.key));
			row.appendChild(element('dd', '', typeof field.value === 'string' ? field.value : field.raw));
			fields.appendChild(row);
		});
		expanded.appendChild(fields);
		if (photo) {
			const figure = element('figure', 'coll-public-photo');
			const image = element('img');
			image.src = safeUrl(photo.image || photo.thumbnail);
			image.alt = item.title;
			image.loading = 'lazy';
			image.referrerPolicy = 'no-referrer';
			figure.appendChild(image);
			expanded.appendChild(figure);
		}
		body.appendChild(expanded);
		card.appendChild(body);
		wrapper.appendChild(card);
		return wrapper;
	};
	const normalize = value => String(value || '').normalize('NFKC').trim().replace(/\s+/g, ' ').toLowerCase();
	const key = item => {
		const fields = Object.fromEntries(item.fields.map(field => [field.key, normalize(field.raw)]));
		return fields.catalog_number
			? JSON.stringify(['catalog', fields.catalog_number, fields.origin_code || '', fields.year || ''])
			: JSON.stringify(['title', normalize(item.title)]);
	};
	const valid = data => data && data.version === 1 && typeof data.kind === 'string' && Array.isArray(data.items) && data.items.every(item => item && typeof item.title === 'string' && Array.isArray(item.fields) && item.fields.every(field => field && typeof field.key === 'string' && typeof field.raw === 'string'));
	form.addEventListener('submit', async event => {
		event.preventDefault();
		message.textContent = labels.loading;
		const button = form.querySelector('button');
		button.disabled = true;
		const controller = new AbortController();
		const timeout = setTimeout(() => controller.abort(), 20000);
		try {
			const url = new URL(document.getElementById('coll-compare-url').value);
			if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password) throw new Error('url');
			url.hash = '';
			url.searchParams.set('format', 'json');
			const response = await fetch(url.href, { credentials: 'omit', referrerPolicy: 'no-referrer', cache: 'no-store', signal: controller.signal });
			if (!response.ok) throw new Error('http');
			const remote = await response.json();
			if (!valid(remote)) throw new Error('format');
			if (remote.kind !== local.kind) {
				message.textContent = labels.kind;
				return;
			}
			const localKeys = new Set(local.items.map(key));
			const remoteKeys = new Set(remote.items.map(key));
			const groups = {
				both: local.items.filter(item => remoteKeys.has(key(item))),
				local: local.items.filter(item => !remoteKeys.has(key(item))),
				remote: remote.items.filter(item => !localKeys.has(key(item)))
			};
			grid.replaceChildren();
			const contents = element('nav', 'coll-comparison-toc');
			const contentsTitle = element('span', 'screen-reader-text', labels.contents);
			contentsTitle.id = 'coll-comparison-toc-title';
			contents.setAttribute('aria-labelledby', contentsTitle.id);
			contents.appendChild(contentsTitle);
			grid.appendChild(contents);
			Object.entries(groups).forEach(([name, items]) => {
				const heading = element('h2', 'coll-comparison-heading', labels[name] + ' (' + items.length + ')');
				heading.id = 'coll-comparison-' + name;
				const jump = element('a', '', heading.textContent);
				jump.href = '#' + heading.id;
				contents.appendChild(jump);
				grid.appendChild(heading);
				items.forEach(item => {
					const card = name === 'remote' ? remoteCard(item) : originalCards[local.items.indexOf(item)];
					grid.appendChild(card);
				});
			});
			message.textContent = labels.done;
		} catch (error) {
			message.textContent = labels.error;
		} finally {
			clearTimeout(timeout);
			button.disabled = false;
		}
	});
}());
