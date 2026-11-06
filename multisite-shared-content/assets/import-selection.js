document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-msc-import-selection]').forEach(form => {
        const master = form.querySelector('[data-msc-select-all]');
        if (!master) return;
        const items = () => [...form.querySelectorAll('input[type="checkbox"][data-msc-import-option]:not(:disabled)')];
        const sync = () => {
            const inputs = items();
            const count = inputs.filter(input => input.checked).length;
            master.disabled = !inputs.length;
            master.checked = !!inputs.length && count === inputs.length;
            master.indeterminate = count > 0 && count < inputs.length;
        };
        master.addEventListener('change', () => {
            const checked = master.checked;
            items().forEach(input => { input.checked = checked || input.required; input.dispatchEvent(new Event('change', {bubbles: true})); });
            sync();
        });
        form.addEventListener('change', event => { if (event.target.matches('[data-msc-import-option]')) sync(); });
        sync();
    });
});
