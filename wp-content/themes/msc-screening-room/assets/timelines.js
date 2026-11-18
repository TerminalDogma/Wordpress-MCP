document.querySelectorAll('[data-story-timeline]').forEach(timeline => {
    const form = timeline.querySelector('.timeline-filter');
    if (!form) return;
    const entries = [...timeline.querySelectorAll('[data-timeline-entry]')];
    const sections = [...timeline.querySelectorAll('[data-timeline-section]')];
    const status = timeline.querySelector('[data-timeline-count]');
    const empty = timeline.querySelector('[data-timeline-empty]');
    const filter = () => {
        const track = form.elements.track.value;
        const era = form.elements.era?.value;
        let visible = 0;
        for (const entry of entries) {
            entry.hidden = (track && entry.dataset.track !== track) || (era && entry.dataset.era !== era) || false;
            if (!entry.hidden) visible++;
        }
        sections.forEach(section => { section.hidden = !section.querySelector('[data-timeline-entry]:not([hidden])'); });
        empty.hidden = visible !== 0;
        status.textContent = visible + ' of ' + entries.length + ' entries';
    };
    form.addEventListener('change', filter);
    form.addEventListener('submit', event => event.preventDefault());
    filter();
    form.hidden = false;
});
