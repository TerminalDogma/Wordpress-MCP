(() => {
    for (const section of document.querySelectorAll('[data-franchise-order]')) {
        const switcher = section.querySelector('.movie-order-switch');
        if (!switcher) continue;
        const buttons = Array.from(switcher.querySelectorAll('button'));
        const panels = section.querySelectorAll('.movie-order-panel');
        function activate(index) {
            buttons.forEach((button, i) => button.setAttribute('aria-pressed', String(i === index)));
            panels.forEach((panel, i) => { panel.hidden = i !== index; });
        }
        buttons.forEach((button, index) => button.addEventListener('click', () => activate(index)));
        activate(0);
        switcher.hidden = false;
    }

    const nav = document.querySelector('[data-movie-tabs]');
    if (!nav) return;
    const tabs = Array.from(nav.querySelectorAll('a')).map(link => ({
        link, section: document.getElementById(link.hash.slice(1))
    })).filter(tab => tab.section);
    if (!tabs.length) return;

    function update() {
        let current = tabs[0];
        const top = nav.getBoundingClientRect().bottom + 36;
        for (const tab of tabs) {
            if (tab.section.getBoundingClientRect().top <= top) current = tab;
        }
        for (const tab of tabs) {
            if (tab === current) tab.link.setAttribute('aria-current', 'location');
            else tab.link.removeAttribute('aria-current');
        }
    }

    let scheduled = false;
    function schedule() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => { update(); scheduled = false; });
    }
    addEventListener('scroll', schedule, {passive: true});
    addEventListener('resize', schedule);
    document.querySelector('.movie-page')?.addEventListener('toggle', schedule, true);
    update();
})();
