(() => {
    const track = document.querySelector('[data-release-track]');
    if (!track || track.children.length < 2) return;

    const section = track.closest('[data-cs-releases]');
    const controls = section.querySelector('[data-release-controls]');
    const previous = section.querySelector('[data-release-prev]');
    const next = section.querySelector('[data-release-next]');

    function update() {
        const maximum = track.scrollWidth - track.clientWidth;
        const position = Math.abs(track.scrollLeft);
        controls.hidden = maximum <= 2;
        previous.disabled = position <= 2;
        next.disabled = position >= maximum - 2;
    }

    function move(direction) {
        const position = Math.abs(track.scrollLeft);
        const first = track.firstElementChild.offsetLeft;
        const offsets = Array.from(track.children, item => Math.abs(item.offsetLeft - first));
        if (direction < 0) offsets.reverse();
        const target = offsets.find(offset => direction * (offset - position) > 2);
        const end = track.scrollWidth - track.clientWidth;
        const inlineDirection = getComputedStyle(track).direction === 'rtl' ? -1 : 1;
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        track.scrollTo({left: inlineDirection * (target ?? (direction < 0 ? 0 : end)), behavior: reduced ? 'auto' : 'smooth'});
    }

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('scroll', update, {passive: true});
    new ResizeObserver(update).observe(track);
    update();
})();
