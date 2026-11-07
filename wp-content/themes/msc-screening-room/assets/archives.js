document.addEventListener('DOMContentLoaded', () => {
    const archive = document.querySelector('#catalog-archive[data-catalog-key]');
    if (!archive) return;
    let request;
    async function load(url, push, scroll) {
        if (request) request.abort();
        const controller = new AbortController(); request = controller;
        archive.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal:controller.signal, credentials:'same-origin', cache:'no-store', headers:{Accept:'text/html'}});
            if (!response.ok) throw new Error();
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.querySelector('#catalog-archive[data-catalog-key]');
            if (!next || next.dataset.catalogKey !== archive.dataset.catalogKey) throw new Error();
            if (request !== controller || controller.signal.aborted) return;
            archive.replaceChildren(...next.childNodes);
            const footer = document.querySelector('#msc-source-footer'), nextFooter = doc.querySelector('#msc-source-footer');
            if (footer && nextFooter) footer.replaceChildren(...nextFooter.childNodes);
            if (push) history.pushState({}, '', url);
            if (scroll) archive.querySelector('#catalog-results').scrollIntoView({block:'start', behavior:'instant'});
        } catch (error) {
            if (error.name !== 'AbortError' && request === controller) {
                const status = archive.querySelector('.catalog-load-status');
                status.hidden = false; status.textContent = 'The catalog could not be loaded. Use Apply filters or a page link to retry.';
            }
        } finally { if (request === controller) archive.removeAttribute('aria-busy'); }
    }
    archive.addEventListener('submit', event => {
        if (!event.target.matches('.catalog-filter-form')) return;
        event.preventDefault(); const url = new URL(event.target.action); url.search = new URLSearchParams(new FormData(event.target));
        load(url.href, true, false);
    });
    archive.addEventListener('click', event => {
        const link = event.target.closest('.archive-pagination a,.catalog-filter-link');
        if (!link || !archive.contains(link) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href); if (url.origin !== location.origin) return;
        event.preventDefault(); load(url.href, true, Boolean(link.closest('.archive-pagination')));
    });
    window.addEventListener('popstate', () => load(location.href, false, false));
});
