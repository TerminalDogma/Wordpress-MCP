(() => {
    const scope = document.querySelector('#sat-scope');
    function showTargets() {
        document.querySelectorAll('[data-sat-targets]').forEach(row => {
            row.hidden = row.dataset.satTargets !== scope.value;
        });
    }
    scope.addEventListener('change', showTargets);
    showTargets();

    const $ = window.jQuery;
    document.querySelectorAll('[data-sat-picker]').forEach(picker => {
        const input = $(picker.querySelector('input[type="search"]'));
        const list = picker.querySelector('.sat-selected');
        const status = picker.querySelector('[data-sat-status]');
        const selected = new Set([...list.querySelectorAll('input')].map(field => field.value));
        const kind = picker.dataset.satPicker;

        input.autocomplete({
            minLength: 2,
            delay: 250,
            source({term}, respond) {
                status.textContent = '';
                $.getJSON(window.ajaxurl, {action: 'sat_lookup', nonce: satAdmin.nonce, kind, term})
                    .done(matches => {
                        const items = matches.filter(item => !selected.has(String(item.id)));
                        if (!items.length && input.val() === term) status.textContent = 'No matching results to add.';
                        respond(items);
                    })
                    .fail(() => {
                        if (input.val() === term) status.textContent = 'Search unavailable. Try again.';
                        respond([]);
                    });
            },
            select(event, {item}) {
                const id = String(item.id);
                if (selected.has(id)) return false;
                selected.add(id);
                const row = document.createElement('li');
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = picker.dataset.satInput;
                field.value = id;
                const label = document.createElement('span');
                label.textContent = item.label;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'button-link-delete';
                remove.setAttribute('data-sat-remove-target', '');
                remove.setAttribute('aria-label', `Remove ${item.label}`);
                remove.textContent = 'Remove';
                row.append(field, label, remove);
                list.append(row);
                input.val('');
                return false;
            },
        });
        input.autocomplete('widget').addClass('sat-suggestions');
        input.autocomplete('instance')._renderItem = function (menu, item) {
            const option = $('<div>').append($('<span>').text(item.label));
            if (item.detail) option.append($('<small>').text(item.detail));
            return $('<li>').append(option).appendTo(menu);
        };
        input.on('input', () => { status.textContent = ''; });
        input.on('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });

        list.addEventListener('click', event => {
            const button = event.target.closest('[data-sat-remove-target]');
            if (!button) return;
            const row = button.closest('li');
            selected.delete(row.querySelector('input').value);
            row.remove();
            input.trigger('focus');
        });
    });

    document.querySelectorAll('[data-sat-media]').forEach(field => {
        const value = field.querySelector('input');
        const preview = field.querySelector('[data-sat-preview]');
        const remove = field.querySelector('[data-sat-remove]');
        const frame = wp.media({title: 'Select campaign artwork', library: {type: 'image'}, multiple: false});
        frame.on('open', () => {
            const selection = frame.state().get('selection');
            selection.reset(value.value && value.value !== '0' ? [wp.media.attachment(value.value)] : []);
        });
        frame.on('select', () => {
            const image = frame.state().get('selection').first().toJSON();
            value.value = image.id;
            const thumbnail = new Image();
            thumbnail.src = image.sizes?.thumbnail?.url || image.url;
            thumbnail.alt = image.alt || '';
            thumbnail.style.maxWidth = '150px';
            thumbnail.style.height = 'auto';
            preview.replaceChildren(thumbnail);
            remove.hidden = false;
        });
        field.querySelector('[data-sat-choose]').addEventListener('click', () => frame.open());
        remove.addEventListener('click', () => {
            value.value = '';
            preview.replaceChildren();
            remove.hidden = true;
        });
    });
})();
