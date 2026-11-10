document.querySelectorAll('[data-comic-check-all]').forEach(control => {
    control.addEventListener('change', () => control.closest('form').querySelectorAll('input[name="issues[]"]').forEach(input => { input.checked = control.checked; }));
});
