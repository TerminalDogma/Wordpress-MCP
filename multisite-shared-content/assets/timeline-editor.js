(() => {
    const editor = document.querySelector('.msc-timeline-editor');
    if (!editor) return;
    const data = editor.querySelector('textarea');
    const controls = editor.querySelector('.msc-timeline-controls');
    const config = JSON.parse(editor.dataset.config);
    const saved = JSON.parse(data.value);
    const state = {
        eras: Array.isArray(saved.eras) ? saved.eras : [],
        entries: Array.isArray(saved.entries) ? saved.entries : []
    };
    const tracks = {movie: 'Movie', show: 'TV show'};
    const colors = ['#a13450', '#2a64ab', '#6a4ab3', '#007b78', '#a55c13', '#506dc7'];
    const sync = () => { data.value = JSON.stringify(state); };

    function element(tag, value, className) {
        const node = document.createElement(tag);
        if (value) node.textContent = value;
        if (className) node.className = className;
        return node;
    }

    function button(label, action, className = 'button') {
        const node = element('button', label, className);
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    }

    function field(caption, input) {
        const label = element('label', '', 'msc-timeline-field');
        label.append(element('span', caption), input);
        return label;
    }

    function input(value, max, placeholder = '', type = 'text') {
        const node = element('input');
        node.type = type;
        node.value = value || '';
        node.maxLength = max;
        node.placeholder = placeholder;
        return node;
    }

    function options(select, choices, value) {
        select.replaceChildren(...Object.entries(choices).map(([id, label]) => new Option(label, id)));
        select.value = value || '';
    }

    const eraList = element('div', '', 'msc-timeline-eras');
    const entryList = element('div', '', 'msc-timeline-entries');
    const entryViews = [];
    const warning = element('p', '', 'notice notice-error inline');
    warning.hidden = true;
    warning.setAttribute('role', 'alert');
    const status = element('p', '', 'screen-reader-text');
    status.setAttribute('role', 'status');

    function refreshEras() {
        const choices = {'': 'No era'};
        state.eras.forEach(era => { choices[era.id] = era.label || 'Unnamed era'; });
        entryViews.forEach(({eraSelect, entry}) => options(eraSelect, choices, entry.era));
    }

    function addEra(era) {
        const row = element('div', '', 'msc-timeline-era');
        const label = input(era.label, 80, 'Phase One, Original Trilogy…');
        const color = input(era.color, 7, '', 'color');
        label.addEventListener('input', () => { era.label = label.value; refreshEras(); sync(); });
        color.addEventListener('input', () => { era.color = color.value; sync(); });
        row.append(field('Era name', label), field('Color', color), button('Remove era', () => {
            state.eras.splice(state.eras.indexOf(era), 1);
            state.entries.forEach(entry => { if (entry.era === era.id) entry.era = ''; });
            row.remove();
            refreshEras();
            sync();
            newEra.focus();
            status.textContent = 'Era removed.';
        }));
        eraList.append(row);
        return label;
    }

    function move(view, target) {
        const index = entryViews.indexOf(view);
        const next = entryViews[target];
        if (!next || target === index) return;
        entryViews.splice(index, 1);
        entryViews.splice(target, 0, view);
        state.entries.splice(index, 1);
        state.entries.splice(target, 0, view.entry);
        if (target < index) next.details.before(view.details);
        else next.details.after(view.details);
        updateOrder();
        sync();
        view.summary.focus();
        status.textContent = 'Moved to position ' + (target + 1) + '.';
    }

    function updateOrder() {
        entryViews.forEach((view, i) => {
            view.up.disabled = i === 0;
            view.down.disabled = i === entryViews.length - 1;
            view.position.value = i + 1;
        });
    }

    function addEntry(entry, expanded = false) {
        const details = element('details');
        details.open = expanded;
        const summary = element('summary');
        const grid = element('div', '', 'msc-timeline-entry-fields');
        const eraSelect = element('select');
        const view = {details, summary, eraSelect, entry};
        entryViews.push(view);
        const summarize = () => {
            summary.textContent = [entry.title || 'New entry', tracks[entry.kind], entry.date].filter(Boolean).join(' · ');
        };
        const set = (key, value) => { entry[key] = value; summarize(); sync(); };
        const textField = (key, label, limit, hint = '') => {
            const node = input(entry[key], limit, hint);
            node.addEventListener('input', () => set(key, node.value));
            grid.append(field(label, node));
            return node;
        };
        const title = textField('title', 'Title', 200);
        const kind = element('select');
        options(kind, tracks, entry.kind);
        grid.append(field('Column', kind));
        const date = textField('date', 'Story date or interval', 140, '1943–1945, c. 2016, five years after the Snap…');
        view.title = title;
        view.date = date;
        const precision = element('select');
        options(precision, config.dates, entry.precision);
        precision.addEventListener('change', () => set('precision', precision.value));
        grid.append(field('Date type', precision));
        eraSelect.addEventListener('change', () => set('era', eraSelect.value));
        grid.append(field('Era or phase', eraSelect));
        textField('section', 'Section heading', 100, 'Repeat a heading for consecutive entries in the same section');
        textField('note', 'Context note', 300, 'Optional relationship to another event');

        const link = element('div', '', 'msc-timeline-entry-tool');
        const linked = element('p');
        const lookup = input('', 150, 'Search by title', 'search');
        const choices = element('div', '', 'msc-timeline-title-results');
        choices.setAttribute('aria-live', 'polite');
        const clear = button('Remove link', () => { set('item', ''); showLink(); });
        function showLink() {
            linked.textContent = entry.item ? 'Linked title: ' + (config.titles[entry.item] || 'Unavailable title') : 'Optional catalog link';
            clear.hidden = !entry.item;
        }
        let searchNumber = 0;
        const search = button('Find catalog title', async () => {
            if (lookup.value.trim().length < 2) { choices.textContent = 'Enter at least two characters.'; lookup.focus(); return; }
            const request = ++searchNumber;
            const params = new URLSearchParams({action: 'msc_timeline_titles', nonce: config.nonce, kind: kind.value, q: lookup.value});
            choices.textContent = 'Searching…';
            try {
                const response = await fetch(config.search + '?' + params.toString(), {credentials: 'same-origin'});
                const result = await response.json();
                if (request !== searchNumber) return;
                if (!response.ok || !result.success) throw new Error();
                choices.replaceChildren(...result.data.map(row => button(row.title + ' (#' + row.id_label + ')', () => {
                    config.titles[row.id] = row.title;
                    set('item', row.id);
                    if (!title.value.trim()) { title.value = row.title; set('title', title.value); }
                    showLink();
                    choices.replaceChildren();
                    lookup.focus();
                })));
                if (!result.data.length) choices.textContent = 'No matching titles on this site.';
            } catch {
                if (request === searchNumber) choices.textContent = 'Could not search the catalog. Try again.';
            }
        });
        lookup.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); search.click(); } });
        kind.addEventListener('change', () => { searchNumber++; set('kind', kind.value); set('item', ''); showLink(); choices.replaceChildren(); });
        link.append(linked, field('Catalog title', lookup), search, clear, choices);
        showLink();

        const artwork = element('div', '', 'msc-timeline-entry-tool');
        const preview = element('img');
        preview.alt = 'Entry artwork';
        const imageStatus = element('p');
        const removeImage = button('Remove image', () => { set('image', []); showImage(); });
        function showImage() {
            const ref = entry.image || {};
            const url = config.previews[ref.site_id + ':' + ref.attachment_id];
            preview.hidden = !url;
            if (url) preview.src = url;
            else preview.removeAttribute('src');
            removeImage.hidden = !ref.attachment_id;
            imageStatus.textContent = ref.attachment_id ? 'Custom artwork' : 'Artwork is optional. Linked public titles can supply their poster.';
        }
        artwork.append(preview, imageStatus);
        if (window.wp?.media) {
            artwork.append(button('Choose artwork', () => {
                const frame = wp.media({title: 'Choose timeline artwork', library: {type: 'image'}, multiple: false});
                frame.on('select', () => {
                    const image = frame.state().get('selection').first().toJSON();
                    const ref = {site_id: config.site, attachment_id: image.id};
                    config.previews[ref.site_id + ':' + ref.attachment_id] = image.sizes?.thumbnail?.url || image.url;
                    set('image', ref);
                    showImage();
                });
                frame.open();
            }));
        }
        artwork.append(removeImage);
        showImage();
        const actions = element('div', '', 'msc-timeline-entry-actions');
        view.up = button('Move up', () => move(view, entryViews.indexOf(view) - 1));
        view.down = button('Move down', () => move(view, entryViews.indexOf(view) + 1));
        const position = input('', 3);
        position.inputMode = 'numeric';
        view.position = position;
        const directMove = button('Move to position', () => {
            const next = Number(position.value);
            if (!Number.isInteger(next) || next < 1 || next > entryViews.length) {
                status.textContent = 'Choose a position from 1 to ' + entryViews.length + '.';
                position.value = entryViews.indexOf(view) + 1;
                position.focus();
                return;
            }
            move(view, next - 1);
        });
        position.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); directMove.click(); } });
        actions.append(view.up, view.down, field('Position', position), directMove, button('Remove entry', () => {
            const index = entryViews.indexOf(view);
            entryViews.splice(index, 1);
            state.entries.splice(index, 1);
            details.remove();
            updateOrder();
            sync();
            (entryViews[index]?.summary || newEntry).focus();
            status.textContent = 'Entry removed.';
        }));
        summarize();
        details.append(summary, grid, link, artwork, actions);
        entryList.append(details);
        return title;
    }

    let nextEra = 1;
    const newEra = button('Add era', () => {
        if (state.eras.length >= 24) { status.textContent = 'This timeline has reached 24 eras.'; return; }
        while (state.eras.some(era => era.id === 'era-' + nextEra)) nextEra++;
        const era = {id: 'era-' + nextEra++, label: '', color: colors[state.eras.length % colors.length]};
        state.eras.push(era);
        addEra(era).focus();
        refreshEras();
        sync();
    });
    const newEntry = button('Add entry', () => {
        if (state.entries.length >= 300) { status.textContent = 'This timeline has reached 300 entries.'; return; }
        const last = state.entries.at(-1);
        const entry = {title: '', kind: 'movie', date: '', precision: 'approximate', era: '', section: last?.section || '', note: '', image: [], item: ''};
        state.entries.push(entry);
        addEntry(entry, true).focus();
        refreshEras();
        updateOrder();
        sync();
    }, 'button button-secondary');

    state.eras.forEach(addEra);
    state.entries.forEach(entry => addEntry(entry));
    refreshEras();
    updateOrder();
    data.form.addEventListener('submit', event => {
        const localOverride = data.form.querySelector('input[name="override[chronology]"]');
        if (localOverride && !localOverride.checked) return;
        warning.hidden = true;
        const eraIndex = state.eras.findIndex(era => !era.label.trim());
        const incomplete = entryViews.find(({entry}) => !entry.title.trim() || (entry.precision !== 'undated' && !entry.date.trim()));
        if (eraIndex < 0 && !incomplete) return;
        event.preventDefault();
        warning.hidden = false;
        if (eraIndex >= 0) {
            warning.textContent = 'Enter a name for each era.';
            eraList.children[eraIndex].querySelector('input').focus();
        } else {
            warning.textContent = 'Each entry needs a title and a story date, unless you choose Undated.';
            incomplete.details.open = true;
            (incomplete.entry.title.trim() ? incomplete.date : incomplete.title).focus();
        }
    });
    controls.append(
        warning,
        element('h3', 'Phases and eras'), eraList, newEra,
        element('h3', 'Entries in story order'),
        element('p', 'Move entries to change their order. Story dates can be exact, approximate, relative or undated.', 'description'),
        entryList, newEntry, status
    );
    data.hidden = true;
    controls.hidden = false;
})();
