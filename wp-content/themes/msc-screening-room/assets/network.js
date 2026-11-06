(() => {
    const button = document.querySelector('.menu-toggle');
    const nav = document.querySelector('#network-navigation');
    if (!button || !nav) return;
    button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        nav.classList.toggle('is-open', open);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            button.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); button.focus();
        }
    });
})();
