(() => {
    const $ = window.jQuery;
    document.querySelectorAll('[data-credits]').forEach(editor => {
        const config = JSON.parse(editor.dataset.credits);
        const people = new Map(config.people.map(person => [person.id, person]));
        const credits = new Map(Object.entries(config.values).map(([role, ids]) => [role, new Set(ids)]));
        const editedCharacters = new Set();
        const values = new Map([...editor.querySelectorAll('[data-msc-credit-value]')].map(input => [input.dataset.mscCreditValue, input]));
        const overrides = new Map([...editor.querySelectorAll('[data-msc-credit-override]')].map(input => [input.dataset.mscCreditOverride, input]));
        const search = $(editor.querySelector('[data-msc-credit-search]'));
        const roleSelect = editor.querySelector('[data-msc-add-role]');
        const list = editor.querySelector('[data-msc-credit-people]');
        const status = editor.querySelector('[role="status"]');

        function write(role, value) {
            const field = values.get(role);
            if (!field || value === field.value) return;
            field.value = value;
            if (overrides.has(role)) overrides.get(role).checked = true;
        }

        function saveCharacters() {
            const lines = config.lines.filter(line => !editedCharacters.has(line.key)).map(line => line.text);
            for (const id of credits.get('actors') || []) {
                const person = people.get(id);
                if (editedCharacters.has(person.key) && person.character.trim()) {
                    lines.push(`${person.label} | ${person.character.trim()}`);
                }
            }
            write('cast_roles', lines.join('\n'));
        }

        function setRole(person, role, enabled) {
            const members = credits.get(role);
            if (enabled) members.add(person.id);
            else members.delete(person.id);
            write(role, JSON.stringify([...members]));
            if (role === 'actors' && config.editCast && (person.character || editedCharacters.has(person.key))) {
                editedCharacters.add(person.key);
                saveCharacters();
            }
        }

        function rowFor(person) {
            const row = document.createElement('li');
            const header = document.createElement('div');
            header.className = 'msc-credit-header';
            const title = document.createElement('strong');
            title.textContent = person.label;
            const assigned = document.createElement('div');
            assigned.className = 'msc-credit-assigned';
            const roles = document.createElement('div');
            roles.className = 'msc-credit-roles';
            const rolePanel = document.createElement('details');
            rolePanel.className = 'msc-credit-role-panel';
            const summary = document.createElement('summary');
            summary.textContent = 'Edit roles';
            rolePanel.append(summary, roles);
            function showAssigned() {
                assigned.replaceChildren();
                for (const [role, options] of Object.entries(config.roles)) {
                    if (!credits.get(role).has(person.id)) continue;
                    const badge = document.createElement('span');
                    badge.textContent = options.label;
                    assigned.append(badge);
                }
            }
            const characterRow = document.createElement('label');
            const character = document.createElement('input');
            characterRow.className = 'msc-credit-character';
            characterRow.hidden = !credits.get('actors')?.has(person.id);
            character.type = 'text';
            character.value = person.character;
            character.maxLength = 200;
            character.readOnly = !config.editCast;
            character.setAttribute('aria-label', `Character for ${person.label}`);
            character.addEventListener('input', () => {
                person.character = character.value;
                editedCharacters.add(person.key);
                saveCharacters();
            });
            characterRow.append('Character', character);
            for (const [role, options] of Object.entries(config.roles)) {
                const label = document.createElement('label');
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.checked = credits.get(role).has(person.id);
                box.disabled = options.locked;
                box.setAttribute('aria-label', `${options.label} for ${person.label}`);
                box.addEventListener('change', () => {
                    setRole(person, role, box.checked);
                    showAssigned();
                    if (role === 'actors') characterRow.hidden = !box.checked;
                    if (![...credits.values()].some(members => members.has(person.id))) {
                        row.remove();
                        search.trigger('focus');
                    }
                });
                label.append(box, options.label);
                roles.append(label);
            }
            showAssigned();
            header.append(title, assigned);
            row.append(header, rolePanel);
            if (config.hasCast) row.append(characterRow);
            return row;
        }

        function render() {
            const current = new Set([...credits.values()].flatMap(members => [...members]));
            list.replaceChildren(...[...current].map(id => rowFor(people.get(id))));
        }

        render();
        if (!search.length) return;
        search.autocomplete({
            minLength: 2,
            delay: 250,
            source({term}, respond) {
                window.mscLookupFields('msc_person', term)
                    .done(items => {
                        if (!items.length && search.val() === term) status.textContent = 'No matching People records.';
                        respond(items);
                    })
                    .fail(() => {
                        if (search.val() === term) status.textContent = 'Person search unavailable. Try again.';
                        respond([]);
                    });
            },
            select(event, {item}) {
                const person = people.get(item.id) || {...item, character: Object.hasOwn(config.characters, item.key) ? config.characters[item.key] : ''};
                people.set(person.id, person);
                setRole(person, roleSelect.value, true);
                search.val('');
                status.textContent = '';
                render();
                return false;
            },
        });
        search.autocomplete('widget').addClass('msc-field-picker-menu');
        search.on('input', () => { status.textContent = ''; });
        search.on('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    });
})();
