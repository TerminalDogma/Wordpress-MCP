(() => {
    const feed = document.querySelector('[data-cs-feed]');
    if (!feed) return;

    const filters = [...feed.querySelectorAll('[data-cs-filter]')];
    const stories = [...feed.querySelectorAll('[data-cs-format]')];
    const more = feed.querySelector('[data-cs-feed-more]');
    const status = feed.querySelector('[data-cs-feed-status]');
    const limit = Number(feed.dataset.limit);
    let active = filters.find(filter => filter.hasAttribute('aria-current'));

    function update(filter, expanded) {
        let count = 0;
        let firstNewStory;
        for (const story of stories) {
            const matches = filter.dataset.csFilter === 'all' || story.dataset.csFormat === filter.dataset.csFilter;
            if (matches) count++;
            const visible = matches && (expanded || count <= limit);
            if (visible && story.hidden && !firstNewStory) firstNewStory = story;
            story.hidden = !visible;
        }
        active.removeAttribute('aria-current');
        filter.setAttribute('aria-current', 'true');
        active = filter;
        more.hidden = expanded || count <= limit;
        const url = new URL(filter.href);
        if (expanded) url.searchParams.set('cs_all', '1');
        history.replaceState(history.state, '', url);
        url.searchParams.set('cs_all', '1');
        more.href = url.href;
        const shown = expanded ? count : Math.min(count, limit);
        status.textContent = `Showing ${shown} of ${count} stories`;
        if (expanded) firstNewStory?.querySelector('a').focus({preventScroll: true});
    }

    function plainClick(event) {
        return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
    }

    for (const filter of filters) {
        filter.addEventListener('click', event => {
            if (!plainClick(event)) return;
            event.preventDefault();
            update(filter, false);
        });
    }
    more.addEventListener('click', event => {
        if (!plainClick(event)) return;
        event.preventDefault();
        update(active, true);
    });
})();
