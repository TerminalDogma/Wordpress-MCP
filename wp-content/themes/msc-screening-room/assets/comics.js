(() => {
    if (!document.querySelector('[data-comic-browser]')) return;
    let pending;
    async function load(url, push) {
        pending?.abort(); const controller = new AbortController(); pending = controller;
        const current = document.querySelector('[data-comic-browser]');
        current?.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {credentials:'same-origin', signal:controller.signal});
            if (!response.ok) throw new Error('Navigation failed');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = parsed.querySelector('[data-comic-browser]');
            if (!next || !current || next.dataset.comicBrowser !== current.dataset.comicBrowser) throw new Error('Issue browser unavailable');
            current.replaceWith(next);
            const footer = parsed.querySelector('.msc-footer-credits');
            const oldFooter = document.querySelector('.msc-footer-credits');
            if (footer && oldFooter) oldFooter.replaceWith(footer);
            else if (oldFooter) oldFooter.remove();
            else if (footer) document.querySelector('.site-footer')?.insertAdjacentElement('afterend', footer);
            if (push) history.pushState({}, '', url);
        } catch (error) { if (error.name !== 'AbortError') location.assign(url); }
        finally { if (pending === controller) document.querySelector('[data-comic-browser]')?.removeAttribute('aria-busy'); }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-comic-nav]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); load(link.href, true);
    });
    window.addEventListener('popstate', () => load(location.href, false));
})();
