(() => {
    const studio = document.querySelector('[data-studio]');
    if (!studio) return;

    const stops = [...studio.querySelectorAll('[data-studio-stop]')];
    const panels = [...studio.querySelectorAll('[data-studio-panel]')];
    if (!stops.length) return;

    const core = studio.querySelector('[data-studio-core]');
    const step = studio.querySelector('[data-studio-step]');
    const scrub = studio.querySelector('[data-studio-scrub]');
    const range = scrub?.querySelector('input');
    const previous = step.querySelector('[data-studio-direction="-1"]');
    const next = step.querySelector('[data-studio-direction="1"]');
    const status = step.querySelector('[data-studio-status]');
    let active = 0;

    function select(index, historyMethod) {
        active = Math.max(0, Math.min(index, stops.length - 1));
        const stop = stops[active];
        const key = stop.dataset.studioStop;
        const label = stop.querySelector('.studio-stop-label').textContent.trim();
        for (const item of stops) {
            if (item === stop) item.setAttribute('aria-current', 'location');
            else item.removeAttribute('aria-current');
        }
        for (const panel of panels) panel.hidden = panel.dataset.studioPanel !== key;
        if (core) {
            const image = stop.dataset.studioImage || core.dataset.studioFallback;
            if (image !== core.getAttribute('src')) core.src = image;
        }
        if (range) {
            range.value = active;
            range.setAttribute('aria-valuetext', label);
        }
        status.textContent = `${active + 1} of ${stops.length} · ${label}`;
        // Keep the focused step button available to keyboard users at either endpoint.
        previous.setAttribute('aria-disabled', String(active === 0));
        next.setAttribute('aria-disabled', String(active === stops.length - 1));
        studio.style.setProperty('--active-stop', active);
        if (historyMethod && location.hash !== stop.hash) history[historyMethod](null, '', stop.hash);
    }

    function fromHash() {
        const target = document.getElementById(location.hash.slice(1));
        const panel = target?.closest('[data-studio-panel]');
        const index = stops.findIndex(stop => stop.dataset.studioStop === panel?.dataset.studioPanel);
        select(Math.max(0, index));
    }

    for (const [index, stop] of stops.entries()) {
        stop.addEventListener('click', event => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            select(index, 'pushState');
            if (matchMedia('(max-width: 760px)').matches) panels[index].scrollIntoView({block: 'start'});
        });
        stop.addEventListener('keydown', event => {
            const destination = {
                ArrowLeft: active - 1, ArrowUp: active - 1, ArrowRight: active + 1,
                ArrowDown: active + 1, Home: 0, End: stops.length - 1,
            }[event.key];
            if (destination === undefined) return;
            event.preventDefault();
            select(destination, 'replaceState');
            stops[active].focus();
        });
    }

    for (const button of [previous, next]) {
        button.addEventListener('click', () => {
            if (button.getAttribute('aria-disabled') === 'true') return;
            select(active + Number(button.dataset.studioDirection), 'pushState');
            panels[active].scrollIntoView({block: 'nearest'});
        });
    }
    if (range) {
        range.addEventListener('input', () => select(Number(range.value), 'replaceState'));
        scrub.hidden = false;
    }
    addEventListener('hashchange', fromHash);
    fromHash();
    if (stops.length > 1) step.hidden = false;
    studio.classList.add('studio-ready');
})();
