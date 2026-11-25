(() => {
    const $ = window.jQuery;
    document.addEventListener('invalid', event => {
        if (!event.target.closest('.msc-record-editor')) return;
        let panel = event.target.closest('details');
        while (panel) {
            panel.open = true;
            panel = panel.parentElement.closest('details');
        }
    }, true);
    window.mscLookupFields = (type, term) => $.getJSON(window.ajaxurl, {action: 'msc_field_picker', nonce: mscFieldPicker.nonce, type, term});
    document.querySelectorAll('.msc-field-picker').forEach(picker => {
        const config = JSON.parse(picker.dataset.picker);
        const value = picker.querySelector('input[type="hidden"]');
        const input = $(picker.querySelector('input[type="search"]'));
        const selected = new Map(config.selected.map(item => [item.id, item]));
        const list = picker.querySelector('ul');
        const status = picker.querySelector('[role="status"]');
        const entity = picker.closest('.msc-entity-editor');
        const override = entity?.querySelector('input[type="checkbox"]');
        const normalize = label => label.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
        const isName = id => id.startsWith('name:');

        function changed() {
            if (override) override.checked = true;
            render();
        }

        function render() {
            list.replaceChildren();
            for (const {id, label} of selected.values()) {
                const row = document.createElement('li');
                const text = document.createElement('span');
                text.textContent = label;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'msc-chip-remove';
                remove.textContent = '×';
                remove.setAttribute('aria-label', `Remove ${label}`);
                remove.addEventListener('click', () => {
                    selected.delete(id);
                    changed();
                    input.trigger('focus');
                });
                row.append(text, remove);
                list.append(row);
            }
            const ids = [...selected.keys()];
            value.value = config.multiple ? JSON.stringify(ids) : (ids[0] || '');
        }

        render();
        input.autocomplete({
            minLength: config.target ? 2 : 0,
            delay: config.target ? 250 : 0,
            source({term}, respond) {
                const query = normalize(term);
                const choices = config.choices.filter(item => normalize(item.label).includes(query));
                function show(matches) {
                    const items = matches.filter(item => !selected.has(item.id)).slice(0, 30);
                    if (input.val() === term && !items.length) status.textContent = 'No matching choices to add.';
                    respond(items);
                }
                if (!config.target) {
                    show(choices);
                    return;
                }
                window.mscLookupFields(config.target, term)
                    .done(matches => {
                        if (!entity) { show(matches); return; }
                        const profileNames = new Set([...matches, ...selected.values()]
                            .filter(item => !isName(item.id))
                            .map(item => normalize(item.label)));
                        const names = choices.filter(item => !profileNames.has(normalize(item.label)));
                        if (config.allowName && query && !profileNames.has(query)
                            && ![...selected.values()].some(item => normalize(item.label) === query)) {
                            names.push({id: 'name:' + term.trim(), label: 'Use “' + term.trim() + '”', name: term.trim()});
                        }
                        show([...matches, ...names]);
                    })
                    .fail(() => {
                        if (input.val() === term) status.textContent = 'Search unavailable. Try again.';
                        respond([]);
                    });
            },
            select(event, {item}) {
                if (!config.multiple) selected.clear();
                if (entity) {
                    if (item.name) item = {...item, label: item.name};
                    for (const existing of selected.values()) {
                        if (isName(existing.id) && (normalize(existing.label) === normalize(item.label)
                            || (config.singleName && isName(item.id)))) selected.delete(existing.id);
                    }
                }
                selected.set(item.id, item);
                input.val('');
                status.textContent = '';
                changed();
                return false;
            },
        });
        input.autocomplete('widget').addClass('msc-field-picker-menu');
        input.on('input', () => { status.textContent = ''; });
        if (!config.target) input.on('focus', () => input.autocomplete('search'));
        input.on('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    });
})();
