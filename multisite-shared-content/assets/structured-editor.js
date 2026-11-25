(() => {
    const types = {text: 'Text', number: 'Number', boolean: 'Yes / No', list: 'List', map: 'Group', null: 'Empty'};
    const collection = node => node.type === 'map' || node.type === 'list';
    const blank = (type = 'text') => ({type, ...(type === 'map' || type === 'list' ? {items: []} : {value: type === 'number' ? '0' : type === 'boolean' ? false : ''})});

    // Number literals are kept as text in the form so editing a neighboring value cannot round a large identifier.
    function encode(node) {
        if (collection(node)) {
            const items = node.items.filter(entry => !entry.draft);
            if (node.type === 'map') return `{${items.map(({key, node: child}) => `${JSON.stringify(key)}:${encode(child)}`).join(',')}}`;
            return `[${items.map(entry => encode(entry.node)).join(',')}]`;
        }
        if (node.type === 'number') return node.value;
        return node.type === 'null' ? 'null' : JSON.stringify(node.value);
    }

    function button(label, action) {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'button';
        element.textContent = label;
        element.addEventListener('click', action);
        return element;
    }

    function labeled(label, field) {
        const wrapper = document.createElement('label');
        wrapper.append(label, field);
        return wrapper;
    }

    function input(type, value) {
        const field = document.createElement(type === 'textarea' ? 'textarea' : 'input');
        if (type === 'textarea') field.rows = 3;
        else field.type = type === 'number' ? 'text' : type;
        if (type === 'number') field.inputMode = 'decimal';
        field.value = value;
        if (type !== 'textarea' && value && (field.value !== value || !field.validity.valid)) {
            field.type = 'text';
            field.value = value;
        }
        return field;
    }

    for (const control of document.querySelectorAll('[data-structured]')) {
        const {spec, root, key} = JSON.parse(control.dataset.structured);
        const hidden = control.querySelector('input[type="hidden"]');
        const body = control.querySelector('.msc-structured-body');
        const override = control.closest('td').querySelector(`input[name="override[${key}]"]`);
        let layout;

        function sync() {
            if (layout) layout.disabled = root.items.some(entry => !entry.draft);
            const value = encode(root);
            if (hidden.value === value) return;
            hidden.value = value;
            if (override) override.checked = true;
        }

        function scalar(node, type, change) {
            if (node.type === 'null') return document.createTextNode('Empty');
            if (node.type === 'boolean') {
                const field = document.createElement('input');
                field.type = 'checkbox';
                field.checked = node.value;
                field.addEventListener('change', () => { node.value = field.checked; change(); });
                return field;
            }
            const field = input(type, node.value);
            field.addEventListener('input', () => {
                const valid = node.type !== 'number' || /^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$/.test(field.value);
                field.setCustomValidity(valid ? '' : 'Enter a number.');
                if (valid) { node.value = field.value; change(); }
            });
            return field;
        }

        function group(node, host, mode = 'generic', item = node.type === 'map' ? 'field' : 'value') {
            const rows = document.createElement('div');
            rows.className = 'msc-structured-rows';
            function renderRow(entry, index) {
                const row = document.createElement('fieldset');
                row.className = 'msc-structured-row';
                const legend = document.createElement('legend');
                legend.textContent = `${item[0].toUpperCase()}${item.slice(1)} ${index + 1}`;
                row.append(legend);
                if (mode === 'rows') {
                    const known = new Set(spec.columns.map(([column]) => column));
                    for (const [column, title, type] of spec.columns) {
                        const existing = entry.node.items.find(field => field.key === column);
                        if (existing && collection(existing.node)) {
                            row.append(nested(existing.node, title));
                            continue;
                        }
                        const field = input(type, String(existing?.node.value ?? ''));
                        field.addEventListener('input', () => {
                            const value = {type: 'text', value: field.value};
                            const saved = entry.node.items.find(child => child.key === column);
                            if (saved) saved.node = value;
                            else entry.node.items.push({key: column, node: value});
                            entry.draft = false;
                            sync();
                        });
                        row.append(labeled(title, field));
                    }
                    for (const field of entry.node.items.filter(field => !known.has(field.key))) {
                        if (collection(field.node)) row.append(nested(field.node, field.key));
                        else row.append(labeled(field.key, scalar(field.node, field.node.type === 'number' ? 'number' : 'text', sync)));
                    }
                } else {
                    if (node.type === 'map') {
                        const name = input('text', entry.key);
                        name.addEventListener('input', () => {
                            const key = name.value.trim();
                            const duplicate = node.items.some(other => other !== entry && other.key === key);
                            name.setCustomValidity(!key ? 'Enter a name or remove this entry.' : duplicate ? 'Choose a unique name.' : '');
                            if (!key || duplicate) return;
                            entry.key = key;
                            entry.draft = false;
                            sync();
                        });
                        row.append(labeled(mode === 'identifiers' ? 'Provider' : 'Name', name));
                    }
                    const value = document.createElement('div');
                    value.className = 'msc-structured-value';
                    const addValue = () => {
                        const child = entry.node;
                        const textType = child.type === 'text' && child.value?.includes('\n') ? 'textarea' : 'text';
                        value.replaceChildren(collection(child) ? nested(child, types[child.type]) : labeled(mode === 'identifiers' ? 'Identifier' : 'Value', scalar(child, child.type === 'number' ? 'number' : textType, () => {
                            if (node.type === 'list') entry.draft = false;
                            sync();
                        })));
                    };
                    if (mode === 'generic') {
                        const choices = document.createElement('select');
                        for (const [type, title] of Object.entries(types)) choices.add(new Option(title, type));
                        choices.value = entry.node.type;
                        choices.addEventListener('change', () => {
                            entry.node = blank(choices.value);
                            if (node.type === 'list') entry.draft = false;
                            addValue();
                            sync();
                        });
                        row.append(labeled('Value type', choices));
                    }
                    addValue();
                    row.append(value);
                }
                row.append(button(`Remove ${item}`, () => {
                    node.items.splice(node.items.indexOf(entry), 1);
                    row.remove();
                    [...rows.children].forEach((other, i) => { other.querySelector('legend').textContent = `${item[0].toUpperCase()}${item.slice(1)} ${i + 1}`; });
                    sync();
                    add.focus();
                }));
                return row;
            }
            const add = button(`Add ${item}`, () => {
                const type = mode === 'rows' ? 'map' : mode === 'values' && spec.type === 'number' ? 'number' : 'text';
                const entry = {key: '', node: blank(type), draft: true};
                node.items.push(entry);
                const row = renderRow(entry, node.items.length - 1);
                rows.append(row);
                row.querySelector('input, select, textarea')?.focus();
            });
            rows.append(...node.items.map(renderRow));
            host.append(rows, add);
        }

        function nested(node, label) {
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = label;
            details.append(summary);
            function open() {
                if (!details.open) return;
                group(node, details);
                details.removeEventListener('toggle', open);
            }
            details.addEventListener('toggle', open);
            return details;
        }

        const canUseRows = root.type === 'list' && root.items.every(entry => entry.node.type === 'map' || (entry.node.type === 'list' && !entry.node.items.length));
        if (spec.mode === 'rows' && canUseRows) {
            root.items.forEach(entry => { entry.node.type = 'map'; });
            group(root, body, 'rows', spec.item);
        } else if ((spec.mode === 'identifiers' && root.type === 'map') || (spec.mode === 'values' && root.type === 'list')) {
            group(root, body, spec.mode, spec.item);
        } else if (spec.mode === 'generic' && key !== 'additional_metadata' && !root.items.length) {
            layout = document.createElement('select');
            const entries = document.createElement('div');
            for (const type of ['map', 'list']) layout.add(new Option(types[type], type));
            layout.value = root.type;
            layout.addEventListener('change', () => {
                root.type = layout.value;
                root.items = [];
                entries.replaceChildren();
                group(root, entries);
            });
            body.append(labeled('Layout', layout), entries);
            group(root, entries);
        } else {
            group(root, body);
        }
    }
})();
