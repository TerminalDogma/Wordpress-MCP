document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.release-calendar[data-endpoint]').forEach(calendar => {
        const initialMonth = calendar.querySelector('[name="calendar_month"]').value;
        let request;
        async function update(params, url) {
            if (request) request.abort();
            const controller = new AbortController(); request = controller;
            calendar.setAttribute('aria-busy', 'true');
            try {
                const endpoint = new URL(calendar.dataset.endpoint, window.location.href);
                endpoint.search = new URLSearchParams({month: params.get('calendar_month') || initialMonth, type: params.get('release_type') || '', platform: params.get('release_platform') || '', region: params.get('release_region') || '', service: params.get('release_service') || '', view: params.get('calendar_view') || 'month', date: params.get('calendar_date') || '', limit: params.get('calendar_limit') || '3'});
                const response = await fetch(endpoint, {signal: controller.signal, credentials: 'same-origin'});
                const data = await response.json();
                if (!response.ok || !data.html) throw new Error(data.message || 'Calendar could not be loaded.');
                if (request !== controller || controller.signal.aborted) return;
                calendar.innerHTML = data.html;
                const credits = document.querySelector('#msc-source-footer');
                if (credits && typeof data.credits_html === 'string') credits.innerHTML = data.credits_html;
                if (url) history.pushState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError' && request === controller) {
                    let status = calendar.querySelector('.calendar-count');
                    if (status) status.textContent = error.message + ' Use the calendar links to retry.';
                }
            } finally { if (request === controller) calendar.removeAttribute('aria-busy'); }
        }
        calendar.addEventListener('click', event => {
            const link = event.target.closest('.calendar-month-link,.calendar-view-link');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
            const url = new URL(link.href); if (url.origin !== location.origin || url.pathname !== location.pathname) return;
            event.preventDefault(); update(url.searchParams, url.href);
        });
        calendar.addEventListener('submit', event => {
            if (!event.target.matches('.calendar-filters')) return;
            event.preventDefault(); const form = event.target; const params = new URLSearchParams(new FormData(form));
            const url = new URL(form.action); url.search = params; url.hash = 'release-calendar'; update(params, url.href);
        });
        window.addEventListener('popstate', () => update(new URL(location.href).searchParams, null));
    });
});
