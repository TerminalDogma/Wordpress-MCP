(() => {
    const $ = window.jQuery;
    document.querySelectorAll('.msc-field-picker').forEach(picker => {
        const config = JSON.parse(picker.dataset.picker);
        const value = picker.querySelector('input[type="hidden"]');
        const input = $(picker.querySelector('input[type="search"]'));
        const selected = new Map(config.selected.map(item => [item.id, item]));
        const list = picker.querySelector('ul');
        const status = picker.querySelector('[role="status"]');

        function render() {
            list.replaceChildren();
            for (const {id, label} of selected.values()) {
                const row = document.createElement('li');
                const text = document.createElement('span');
                text.textContent = label;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button-link-delete';
                remove.textContent = 'Remove';
                remove.setAttribute('aria-label', `Remove ${label}`);
                remove.addEventListener('click', () => {
                    selected.delete(id);
                    render();
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
                function show(matches) {
                    const items = matches.filter(item => !selected.has(item.id)).slice(0, 30);
                    if (input.val() === term && !items.length) status.textContent = 'No matching choices to add.';
                    respond(items);
                }
                if (!config.target) {
                    const query = term.trim().toLocaleLowerCase();
                    show(config.choices.filter(item => item.label.toLocaleLowerCase().includes(query)));
                    return;
                }
                $.getJSON(window.ajaxurl, {action: 'msc_field_picker', nonce: mscFieldPicker.nonce, type: config.target, term})
                    .done(show)
                    .fail(() => {
                        if (input.val() === term) status.textContent = 'Search unavailable. Try again.';
                        respond([]);
                    });
            },
            select(event, {item}) {
                if (!config.multiple) selected.clear();
                selected.set(item.id, item);
                input.val('');
                status.textContent = '';
                render();
                return false;
            },
        });
        input.autocomplete('widget').addClass('msc-field-picker-menu');
        input.on('input', () => { status.textContent = ''; });
        if (!config.target) input.on('focus', () => input.autocomplete('search'));
        input.on('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    });
})();
