(() => {
    const pending = new WeakMap();
    const coverage = document.querySelector('[data-movie-coverage]');
    const coveragePath = location.pathname;
    const coverageFilters = ['coverage_type', 'coverage_order', 'recent_articles_page'];
    const coverageState = url => coverageFilters.map(key => url.searchParams.get(key)).join(':');
    let visibleCoverage = coverageState(new URL(location.href));

    function start(section) {
        pending.get(section)?.abort();
        const request = new AbortController();
        pending.set(section, request);
        section.setAttribute('aria-busy', 'true');
        return request;
    }

    function finish(section, request) {
        if (pending.get(section) !== request) return;
        pending.delete(section);
        section.removeAttribute('aria-busy');
    }

    async function fetchPage(url, signal) {
        const response = await fetch(url, {credentials: 'same-origin', signal});
        if (!response.ok) throw new Error('Could not load the requested page');
        return new DOMParser().parseFromString(await response.text(), 'text/html');
    }

    function coverageMessage(message) {
        coverage.querySelector('.movie-coverage-status').textContent = message;
    }

    async function filterCoverage(url, push = false) {
        const request = start(coverage);
        coverageMessage('');
        try {
            const page = await fetchPage(url, request.signal);
            if (request.signal.aborted) return;
            const updated = page.querySelector('[data-movie-coverage]');
            if (!updated) throw new Error('Coverage is unavailable');
            const focused = document.activeElement;
            const selected = coverage.contains(focused) ? focused.dataset.coverageFilter : null;
            const scrollTop = window.scrollY;
            coverage.style.minHeight = '';
            coverage.replaceChildren(...updated.childNodes);
            // Shorter results can otherwise clamp the scroll position near the footer.
            const shortfall = scrollTop + window.innerHeight - document.scrollingElement.scrollHeight;
            if (shortfall > 0) coverage.style.minHeight = `${Math.ceil(coverage.getBoundingClientRect().height + shortfall)}px`;
            if (window.scrollY !== scrollTop) window.scrollTo({top: scrollTop, behavior: 'instant'});
            if (selected) {
                coverage.querySelector(`[data-coverage-filter="${CSS.escape(selected)}"]`)?.focus({preventScroll: true});
            }
            if (push && url.href !== location.href) history.pushState(null, '', url);
            visibleCoverage = coverageState(url);
        } catch {
            if (!request.signal.aborted) coverageMessage('Coverage could not load. Try the filter again.');
        } finally {
            finish(coverage, request);
        }
    }

    if (coverage) {
        addEventListener('popstate', () => {
            const url = new URL(location.href);
            if (url.pathname !== coveragePath) return;
            if (coverageState(url) !== visibleCoverage || pending.has(coverage)) filterCoverage(url);
        });
    }

    function addCredits(page) {
        const incoming = page.querySelector('#msc-source-footer .msc-footer-credits');
        const footer = document.getElementById('msc-source-footer');
        if (!incoming || !footer) return;
        const list = footer.querySelector('.msc-footer-credits ul');
        if (!list) {
            footer.append(incoming);
            return;
        }
        const credits = new Set(Array.from(list.children, item => item.innerHTML));
        for (const item of Array.from(incoming.querySelectorAll('li'))) {
            if (!credits.has(item.innerHTML)) {
                credits.add(item.innerHTML);
                list.append(item);
            }
        }
    }

    document.addEventListener('click', async event => {
        const link = event.target.closest('[data-collection-more], [data-coverage-filter]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.hasAttribute('data-coverage-filter')) {
            if (!coverage?.contains(link)) return;
            const url = new URL(link.href);
            if (url.origin !== location.origin || url.pathname !== coveragePath) return;
            event.preventDefault();
            if (coverageState(url) !== visibleCoverage || pending.has(coverage)) filterCoverage(url, true);
            return;
        }
        const section = link.closest('[data-catalog-collection]');
        const list = section?.querySelector('[data-collection-items]');
        if (!list) return;
        event.preventDefault();
        if (pending.has(section)) return;
        const request = start(section);
        if (section === coverage) coverageMessage('');
        try {
            const page = await fetchPage(link.href, request.signal);
            if (request.signal.aborted) return;
            const next = page.getElementById(section.id);
            const items = next?.querySelector('[data-collection-items]');
            if (!items?.children.length) throw new Error('No more entries');
            const firstLink = items.querySelector('h3 a') || items.querySelector('a');
            list.append(...items.children);
            if (section.id === 'franchise-titles') addCredits(page);
            const nextLink = next.querySelector('[data-collection-more]');
            if (nextLink) {
                link.href = nextLink.href;
            } else {
                const pager = link.parentElement;
                link.remove();
                if (!pager.children.length) pager.remove();
            }
            firstLink?.focus();
        } catch {
            if (!request.signal.aborted) {
                if (section === coverage) coverageMessage('More articles could not load. Try again.');
                else location.assign(link.href);
            }
        } finally {
            finish(section, request);
        }
    });
})();
