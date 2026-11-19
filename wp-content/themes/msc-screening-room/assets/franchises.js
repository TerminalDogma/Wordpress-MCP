(() => {
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
        const link = event.target.closest('[data-franchise-more]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const section = link.closest('[data-franchise-collection]');
        const list = section.querySelector('[data-franchise-items]');
        if (!list) return;
        event.preventDefault();
        if (section.hasAttribute('aria-busy')) return;
        section.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(link.href, {credentials: 'same-origin'});
            if (!response.ok) throw new Error('Could not load the next page');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.getElementById(section.id);
            const items = next?.querySelector('[data-franchise-items]');
            if (!items?.children.length) throw new Error('No more entries');
            const firstLink = items.querySelector('h3 a') || items.querySelector('a');
            list.append(...items.children);
            if (section.id === 'franchise-titles') addCredits(page);
            const nextLink = next.querySelector('[data-franchise-more]');
            if (nextLink) {
                link.href = nextLink.href;
            } else {
                const pager = link.parentElement;
                link.remove();
                if (!pager.children.length) pager.remove();
            }
            firstLink?.focus();
        } catch {
            location.assign(link.href);
        } finally {
            section.removeAttribute('aria-busy');
        }
    });
})();
