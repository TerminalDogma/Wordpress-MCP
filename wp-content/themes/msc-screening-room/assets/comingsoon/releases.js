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
        const step = track.children[1].offsetLeft - track.children[0].offsetLeft;
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        track.scrollBy({left: direction * step, behavior: reduced ? 'auto' : 'smooth'});
    }

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('scroll', update, {passive: true});
    new ResizeObserver(update).observe(track);
    update();
})();
