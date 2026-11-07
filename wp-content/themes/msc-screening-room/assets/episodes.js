document.addEventListener('DOMContentLoaded', () => {
    const guide = document.querySelector('#episodes[data-show-uuid]');
    if (!guide) return;
    const content = guide.querySelector('.episode-guide-content');
    const status = guide.querySelector('.episode-load-status');
    if (!content || !status) return;
    let request;

    async function loadSeason(url, pushHistory) {
        if (request) request.abort();
        const controller = new AbortController(); request = controller;
        guide.dataset.loading = 'true';
        content.setAttribute('aria-busy', 'true');
        status.textContent = 'Loading episodes…';
        try {
            const response = await fetch(url.href, {
                signal: controller.signal, credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'text/html'}
            });
            if (!response.ok) throw new Error('Episodes could not be loaded. Please try the season link again.');
            const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextGuide = nextDocument.querySelector('#episodes[data-show-uuid]');
            const nextContent = nextGuide?.querySelector('.episode-guide-content');
            if (!nextContent || nextGuide.dataset.showUuid !== guide.dataset.showUuid) {
                throw new Error('Episodes could not be loaded. Please try the season link again.');
            }
            if (request !== controller || controller.signal.aborted) return;
            content.replaceChildren(...nextContent.childNodes);
            const nextFooter = nextDocument.querySelector('#msc-source-footer');
            const footer = document.querySelector('#msc-source-footer');
            if (footer && nextFooter) footer.replaceChildren(...nextFooter.childNodes);
            if (pushHistory) history.pushState({}, '', url.href);
            status.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && request === controller) {
                status.textContent = 'Episodes could not be loaded. Please try the season link again.';
            }
        } finally {
            if (request === controller) {
                content.setAttribute('aria-busy', 'false');
                delete guide.dataset.loading;
            }
        }
    }

    guide.addEventListener('click', event => {
        const link = event.target.closest('.season-pagination a,.episode-pagination a');
        if (!link || !guide.contains(link) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || url.pathname !== location.pathname) return;
        event.preventDefault();
        loadSeason(url, true);
    });
    window.addEventListener('popstate', () => loadSeason(new URL(location.href), false));
});
