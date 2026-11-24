(() => {
    const stage = document.querySelector('.sat-stage');
    const rails = stage?.querySelectorAll('.sat-rail-track > .sat-slot');
    if (!rails?.length) return;

    const track = rails[0].parentElement;
    const footer = document.querySelector('.site-footer, footer[role="contentinfo"], footer.wp-block-template-part, body > footer');
    const adminBar = document.getElementById('wpadminbar');

    function fitTracks() {
        if (!track.offsetWidth) return;
        const gap = track.offsetTop;
        const start = stage.getBoundingClientRect().top;
        const end = footer?.getBoundingClientRect().top ?? document.body.getBoundingClientRect().bottom;
        const top = gap + (adminBar?.offsetHeight ?? 0);
        const insets = Array.from(rails, rail => Math.min(top, window.innerHeight - rail.offsetHeight - gap));

        stage.style.setProperty('--sat-track-height', `${Math.max(0, end - start - 2 * gap)}px`);
        // Tall ads scroll until their bottom is visible, then stick at that position.
        rails.forEach((rail, index) => rail.style.setProperty('--sat-sticky-top', `${insets[index]}px`));
    }

    fitTracks();
    const observer = new ResizeObserver(fitTracks);
    [document.body, stage, ...rails].forEach(element => observer.observe(element));
    window.addEventListener('resize', fitTracks);
})();
