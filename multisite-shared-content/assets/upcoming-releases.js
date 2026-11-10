document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.msc-upcoming[data-endpoint]').forEach(root => {
        const defaults = JSON.parse(root.querySelector('[data-filters]').dataset.filters);
        const keys = ['month', 'type', 'service', 'search', 'view', 'past', 'page'];
        let active;
        async function update(params, destination) {
            if (active) active.abort();
            const controller = new AbortController(); active = controller;
            root.setAttribute('aria-busy', 'true');
            try {
                const endpoint = new URL(root.dataset.endpoint, location.href);
                keys.forEach(key => endpoint.searchParams.set(key, params.get('release_' + key) ?? defaults[key]));
                endpoint.searchParams.set('base', root.dataset.base);
                endpoint.searchParams.set('heading_level', root.dataset.headingLevel || '1');
                const response = await fetch(endpoint, {signal: controller.signal, credentials: 'same-origin'});
                const result = await response.json();
                if (!response.ok || !result.html) throw new Error(result.message || 'Releases could not be loaded. Please retry.');
                if (active !== controller || controller.signal.aborted) return;
                root.innerHTML = result.html;
                const footer = document.querySelector('#msc-source-footer');
                if (footer && typeof result.credits_html === 'string') footer.innerHTML = result.credits_html;
                if (destination) history.pushState({}, '', destination);
            } catch (error) {
                if (error.name === 'AbortError' || active !== controller) return;
                let message = root.querySelector('.release-guide-error');
                if (!message) { message = document.createElement('p'); message.className = 'release-guide-error'; message.setAttribute('role', 'alert'); root.prepend(message); }
                message.textContent = error.message;
            } finally { if (active === controller) root.removeAttribute('aria-busy'); }
        }
        root.addEventListener('click', event => {
            const link = event.target.closest('[data-release-nav]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
            const url = new URL(link.href, location.href);
            if (url.origin !== location.origin || url.pathname !== location.pathname) return;
            event.preventDefault(); update(url.searchParams, url.href);
        });
        root.addEventListener('submit', event => {
            const form = event.target;
            if (!form.matches('.release-guide-filters')) return;
            event.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            params.set('release_past', form.querySelector('[name="release_past"]').checked ? '1' : '0');
            params.set('release_page', '1');
            const url = new URL(form.action); url.search = params.toString(); url.hash = 'upcoming-releases'; update(params, url.href);
        });
        window.addEventListener('popstate', () => update(new URL(location.href).searchParams, null));
    });
});
