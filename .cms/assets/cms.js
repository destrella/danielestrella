const rows = [...document.querySelectorAll('[data-row]')];
const titleFilter = document.querySelector('#filter-title');
const dateFromFilter = document.querySelector('#filter-date-from');
const dateToFilter = document.querySelector('#filter-date-to');
const providerFilter = document.querySelector('#filter-provider');
const mediaTypeFilter = document.querySelector('#filter-media-type');
const clearFiltersButton = document.querySelector('[data-clear-filters]');
const visibleCount = document.querySelector('#visible-count');
const combineButton = document.querySelector('[data-combine]');
const combineForm = document.querySelector('#combine-form');
const sectionBlocks = document.querySelector('[data-section-blocks]');
const sectionBlockTemplate = document.querySelector('#section-block-template');
const addBlockButton = document.querySelector('[data-add-block]');
const imageOptionsList = document.querySelector('#entry-image-options');
const initialImageOptions = imageOptionsList ? [...imageOptionsList.querySelectorAll('option')].map((option) => option.value) : [];
const filterControls = [
	{ control: titleFilter, param: 'filter_title' },
	{ control: dateFromFilter, param: 'filter_date_from' },
	{ control: dateToFilter, param: 'filter_date_to' },
	{ control: providerFilter, param: 'filter_provider' },
	{ control: mediaTypeFilter, param: 'filter_media_type' },
];

function updateVisibleCount() {
	if (!visibleCount) return;
	visibleCount.textContent = rows.filter((row) => !row.classList.contains('is-hidden')).length;
}

function currentListUrl(focusRowId = '') {
	const params = new URLSearchParams();
	for (const { control, param } of filterControls) {
		const value = control?.value.trim() || '';
		if (value !== '') {
			params.set(param, value);
		}
	}
	if (focusRowId !== '') {
		params.set('focus', focusRowId);
	}
	const query = params.toString();
	return `index.php${query !== '' ? `?${query}` : ''}`;
}

function syncFilterUrl() {
	if (!rows.length) return;
	window.history.replaceState(null, '', new URL(currentListUrl(), window.location.href));
}

function setFiltersFromUrl() {
	const params = new URLSearchParams(window.location.search);
	for (const { control, param } of filterControls) {
		if (control && params.has(param)) {
			control.value = params.get(param) || '';
		}
	}
}

function applyFilters(options = {}) {
	const updateUrl = options.updateUrl !== false;
	const titleQuery = titleFilter?.value.trim().toLocaleLowerCase() || '';
	const dateFrom = dateFromFilter?.value || '';
	const dateTo = dateToFilter?.value || '';
	const provider = providerFilter?.value || '';
	const mediaType = mediaTypeFilter?.value || '';

	for (const row of rows) {
		const rowDate = row.dataset.date || '';
		const matchesTitle = !titleQuery || (row.dataset.title || '').includes(titleQuery);
		const matchesFrom = !dateFrom || (rowDate !== '' && rowDate >= dateFrom);
		const matchesTo = !dateTo || (rowDate !== '' && rowDate <= dateTo);
		const matchesProvider = !provider || row.dataset.provider === provider;
		const matchesMediaType = !mediaType || row.dataset.mediaType === mediaType;
		row.classList.toggle('is-hidden', !(matchesTitle && matchesFrom && matchesTo && matchesProvider && matchesMediaType));
	}
	updateVisibleCount();
	if (updateUrl) {
		syncFilterUrl();
	}
}

for (const control of [titleFilter, dateFromFilter, dateToFilter, providerFilter, mediaTypeFilter]) {
	if (control) {
		control.addEventListener('input', applyFilters);
		control.addEventListener('change', applyFilters);
	}
}

if (clearFiltersButton) {
	clearFiltersButton.addEventListener('click', () => {
		for (const control of [titleFilter, dateFromFilter, dateToFilter, providerFilter, mediaTypeFilter]) {
			if (control) control.value = '';
		}
		applyFilters();
	});
}

function selectedSources() {
	return [...document.querySelectorAll('.select-entry:checked')];
}

function updateCombineState() {
	if (!combineButton) return;
	combineButton.disabled = selectedSources().length === 0;
}

document.addEventListener('change', (event) => {
	if (event.target.matches('.select-entry')) {
		updateCombineState();
	}
});

document.addEventListener('click', (event) => {
	const entryEditLink = event.target.closest('[data-entry-edit-link]');
	if (entryEditLink) {
		const row = entryEditLink.closest('[data-row]');
		if (row) {
			const url = new URL(entryEditLink.href, window.location.href);
			url.searchParams.set('return', currentListUrl(row.dataset.rowId || ''));
			entryEditLink.href = url.toString();
		}
		return;
	}
	const edit = event.target.closest('[data-edit]');
	const cancel = event.target.closest('[data-cancel]');
	if (!edit && !cancel) return;
	const row = event.target.closest('[data-row]');
	if (!row) return;
	const form = row.querySelector('[data-edit-form]');
	const view = row.querySelector('[data-title-view]');
	const open = Boolean(edit);
	form.classList.toggle('is-open', open);
	view.classList.toggle('is-hidden', open);
	if (open) {
		const input = form.querySelector('input[name="title"]');
		input.focus();
		input.select();
	}
});

document.addEventListener('submit', (event) => {
	const deleteForm = event.target.closest('[data-delete-form]');
	if (deleteForm && !window.confirm('Esta entrada se quitara del archivo publico y se movera a .cms/trash.')) {
		event.preventDefault();
		return;
	}
	if (event.target === combineForm) {
		const count = selectedSources().length;
		if (count === 0 || !window.confirm(`Se combinaran ${count} entrada(s) seleccionada(s) en el destino y luego se moveran a .cms/trash.`)) {
			event.preventDefault();
		}
	}
});

const focusRow = document.body.dataset.focusRow;
setFiltersFromUrl();
applyFilters({ updateUrl: false });
if (focusRow) {
	const row = rows.find((candidate) => candidate.dataset.rowId === focusRow);
	if (row) {
		row.classList.remove('is-hidden');
		row.scrollIntoView({ block: 'center', behavior: 'instant' });
		row.focus({ preventScroll: true });
		row.classList.add('is-focused');
		setTimeout(() => row.classList.remove('is-focused'), 4500);
		updateVisibleCount();
	}
}

updateCombineState();

function updateBlockVisibility(block) {
	const layout = block.querySelector('[data-layout-select]')?.value || 'texto';
	const sideField = block.querySelector('[data-side-field]');
	const secondColumn = block.querySelector('[data-second-column]');
	if (sideField) {
		sideField.classList.toggle('is-hidden', layout !== 'imagen-y-texto');
	}
	if (secondColumn) {
		secondColumn.classList.toggle('is-hidden', layout !== 'columnas-texto');
	}
	for (const guide of block.querySelectorAll('[data-guide-for]')) {
		guide.classList.toggle('is-hidden', guide.dataset.guideFor !== layout);
	}
	updateBlockSummary(block);
}

function updateBlockSummary(block) {
	const summary = block.querySelector('[data-block-summary]');
	if (!summary) return;
	const layoutSelect = block.querySelector('[data-layout-select]');
	const layoutText = layoutSelect?.selectedOptions?.[0]?.textContent?.trim() || '.texto';
	const subtitle = block.querySelector('input[name$="[subtitle]"]')?.value.trim();
	const mediaStyle = block.querySelector('select[name$="[mediaStyle]"]')?.value;
	const mediaCount = [...block.querySelectorAll('[data-media-item]')].filter((item) => (item.querySelector('[data-media-route]')?.value.trim() || '') !== '').length;
	const pieces = [layoutText.replace(/\s+-\s+.*$/, '')];
	if (subtitle) pieces.push(subtitle);
	if (mediaStyle) pieces.push(mediaStyle);
	if (mediaCount > 0) pieces.push(`${mediaCount} media`);
	summary.textContent = pieces.join(' · ');
}

function mediaItemType(item) {
	return item.querySelector('[data-media-type-select]')?.value || 'image';
}

function updateMediaItemVisibility(item) {
	const type = mediaItemType(item);
	const primaryLabel = item.querySelector('[data-media-primary-label]');
	const liveVideoField = item.querySelector('[data-live-video-field]');
	const posterField = item.querySelector('[data-poster-field]');
	if (primaryLabel) {
		primaryLabel.textContent = type === 'video' ? 'Ruta del video' : (type === 'live-photo' ? 'Ruta de la foto' : 'Ruta de la imagen');
	}
	if (liveVideoField) {
		liveVideoField.classList.toggle('is-hidden', type !== 'live-photo');
	}
	if (posterField) {
		posterField.classList.toggle('is-hidden', type !== 'video');
	}
	updateMediaItemSummary(item);
}

function updateMediaItemSummary(item) {
	const summary = item.querySelector('[data-media-summary]');
	if (!summary) return;
	const typeSelect = item.querySelector('[data-media-type-select]');
	const typeLabel = typeSelect?.selectedOptions?.[0]?.textContent?.trim() || 'Imagen';
	const style = item.querySelector('[data-media-style-select]')?.value || 'auto';
	const route = item.querySelector('[data-media-route]')?.value.trim() || 'sin ruta';
	const shortRoute = route.length > 46 ? `${route.slice(0, 43)}...` : route;
	summary.textContent = `${typeLabel} · ${style} · ${shortRoute}`;
}

function renumberMediaItems(block) {
	const mediaItems = [...block.querySelectorAll('[data-media-item]')];
	mediaItems.forEach((item, index) => {
		const number = item.querySelector('[data-media-number]');
		if (number) number.textContent = String(index + 1);
		for (const field of item.querySelectorAll('[name]')) {
			field.name = field.name.replace(/\[mediaItems\]\[[^\]]+\]/, `[mediaItems][${index}]`);
		}
		const removeButton = item.querySelector('[data-remove-media-item]');
		if (removeButton) {
			removeButton.disabled = mediaItems.length === 1;
		}
		updateMediaItemVisibility(item);
	});
}

function renumberBlocks() {
	if (!sectionBlocks) return;
	const blocks = [...sectionBlocks.querySelectorAll('[data-section-block]')];
	blocks.forEach((block, index) => {
		const number = block.querySelector('[data-block-number]');
		if (number) number.textContent = String(index + 1);
		for (const field of block.querySelectorAll('[name]')) {
			field.name = field.name.replace(/blocks\[[^\]]+\]/, `blocks[${index}]`);
		}
		const removeButton = block.querySelector('[data-remove-block]');
		if (removeButton) {
			removeButton.disabled = blocks.length === 1;
		}
		renumberMediaItems(block);
		updateBlockVisibility(block);
	});
	updateImageOptions();
}

function isImageRoute(route) {
	return route !== '' && !/\.(mp4|webm|mov|m4v)(\?.*)?$/i.test(route);
}

function updateImageOptions() {
	if (!imageOptionsList) return;
	const images = new Set(initialImageOptions.filter(Boolean));
	for (const item of document.querySelectorAll('[data-media-item]')) {
		const type = mediaItemType(item);
		const route = item.querySelector('[data-media-route]')?.value.trim() || '';
		const poster = item.querySelector('[data-media-poster]')?.value.trim() || '';
		if ((type === 'image' || type === 'live-photo') && isImageRoute(route)) {
			images.add(route);
		}
		if (type === 'video' && isImageRoute(poster)) {
			images.add(poster);
		}
	}
	imageOptionsList.replaceChildren(...[...images].map((image) => {
		const option = document.createElement('option');
		option.value = image;
		return option;
	}));
}

if (addBlockButton && sectionBlocks && sectionBlockTemplate) {
	addBlockButton.addEventListener('click', () => {
		const fragment = sectionBlockTemplate.content.cloneNode(true);
		sectionBlocks.append(fragment);
		renumberBlocks();
		const blocks = sectionBlocks.querySelectorAll('[data-section-block]');
		const lastBlock = blocks[blocks.length - 1];
		const firstField = lastBlock?.querySelector('select, input, textarea');
		if (lastBlock) lastBlock.open = true;
		lastBlock?.scrollIntoView({ block: 'center', behavior: 'smooth' });
		firstField?.focus({ preventScroll: true });
	});
}

document.addEventListener('click', (event) => {
	const removeButton = event.target.closest('[data-remove-block]');
	const addMediaButton = event.target.closest('[data-add-media-item]');
	const removeMediaButton = event.target.closest('[data-remove-media-item]');
	if (addMediaButton) {
		const block = addMediaButton.closest('[data-section-block]');
		const list = block?.querySelector('[data-media-items]');
		const template = block?.querySelector('[data-media-item-template]');
		if (!block || !list || !template) return;
		const fragment = template.content.cloneNode(true);
		list.append(fragment);
		renumberBlocks();
		const items = list.querySelectorAll('[data-media-item]');
		const lastItem = items[items.length - 1];
		const firstField = lastItem?.querySelector('input, select, textarea');
		if (lastItem) lastItem.open = true;
		firstField?.focus({ preventScroll: true });
		return;
	}
	if (removeMediaButton) {
		const block = removeMediaButton.closest('[data-section-block]');
		const item = removeMediaButton.closest('[data-media-item]');
		const list = block?.querySelector('[data-media-items]');
		if (!block || !item || !list || list.querySelectorAll('[data-media-item]').length <= 1) return;
		item.remove();
		renumberBlocks();
		return;
	}
	if (removeButton) {
		const block = removeButton.closest('[data-section-block]');
		if (!block || !sectionBlocks) return;
		const blocks = sectionBlocks.querySelectorAll('[data-section-block]');
		if (blocks.length <= 1) return;
		block.remove();
		renumberBlocks();
	}
});

document.addEventListener('change', (event) => {
	const layoutSelect = event.target.closest('[data-layout-select]');
	const mediaTypeSelect = event.target.closest('[data-media-type-select]');
	if (layoutSelect) {
		const block = layoutSelect.closest('[data-section-block]');
		if (block) updateBlockVisibility(block);
		return;
	}
	if (mediaTypeSelect) {
		const item = mediaTypeSelect.closest('[data-media-item]');
		const block = mediaTypeSelect.closest('[data-section-block]');
		if (item) updateMediaItemVisibility(item);
		if (block) updateBlockSummary(block);
		updateImageOptions();
	}
});

document.addEventListener('input', (event) => {
	const mediaItem = event.target.closest('[data-media-item]');
	if (mediaItem) {
		updateMediaItemSummary(mediaItem);
		updateImageOptions();
	}
	const block = event.target.closest('[data-section-block]');
	if (block) updateBlockSummary(block);
});

renumberBlocks();
