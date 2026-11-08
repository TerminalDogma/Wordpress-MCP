(() => {
    const init = () => document.querySelectorAll('textarea[data-msc-trailers], .acf-field[data-name="msc_trailers"] textarea').forEach(field => {
        if (field.dataset.trailerPicker) return;
        field.dataset.trailerPicker = 'true';
        const box = document.createElement('div'); box.className = 'msc-trailer-picker';
        const search = document.createElement('div'); search.className = 'msc-trailer-search';
        const query = document.createElement('input'); query.type = 'search'; query.placeholder = 'Title + official trailer'; query.setAttribute('aria-label', 'Search YouTube trailers');
        const title = document.querySelector('#title, input[name="values[title]"], input[name="fields[title]"]');
        const sharedTitle = document.querySelector('.wrap h1')?.textContent.includes('ACF Shared Fields') ? document.querySelector('.wrap h2')?.textContent : '';
        if (title?.value || sharedTitle) query.value = `${title?.value || sharedTitle} official trailer`;
        const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = 'Search YouTube';
        search.append(query, button);
        const status = document.createElement('p'); status.className = 'msc-trailer-status'; status.setAttribute('role', 'status');
        const results = document.createElement('div'); results.className = 'msc-trailer-results';
        const preview = document.createElement('div'); preview.className = 'msc-trailer-preview';
        const more = document.createElement('button'); more.type = 'button'; more.className = 'button msc-trailer-more'; more.textContent = 'More results'; more.hidden = true;
        box.append(search, status, preview, results, more); field.after(box);
        if (!window.mscTrailers?.configured) status.textContent = 'YouTube search uses the Serper key configured under Network Admin → Shared Content → Image Search. You can also paste trailer URLs above.';
        let generation = 0, nextPage = null, currentQuery = '', controller;
        const urls = () => field.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean);
        const canonical = value => {
            try {
                const u = new URL(value);
                if (['youtu.be', 'www.youtu.be'].includes(u.hostname)) return `https://www.youtube.com/watch?v=${u.pathname.slice(1)}`;
                if (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'].includes(u.hostname)) {
                    const id = u.pathname === '/watch' ? u.searchParams.get('v') : u.pathname.split('/')[2];
                    if (id) return `https://www.youtube.com/watch?v=${id}`;
                }
            } catch (_) {}
            return value;
        };
        const refresh = () => box.querySelectorAll('[data-add-trailer]').forEach(add => {
            const added = urls().map(canonical).includes(add.dataset.addTrailer);
            add.disabled = added; add.textContent = added ? 'Added' : 'Add trailer';
        });
        field.addEventListener('input', refresh);
        const render = items => items.forEach(item => {
            const card = document.createElement('article'); card.className = 'msc-trailer-result';
            const image = document.createElement('img'); image.src = item.thumbnail; image.alt = ''; image.loading = 'lazy';
            const heading = document.createElement('h4'); heading.textContent = item.title;
            const info = document.createElement('p'); info.textContent = [item.channel, item.duration].filter(Boolean).join(' · ');
            const actions = document.createElement('div'); actions.className = 'msc-trailer-actions';
            const play = document.createElement('button'); play.type = 'button'; play.className = 'button'; play.textContent = 'Preview';
            play.addEventListener('click', () => {
                const frame = document.createElement('iframe'); frame.src = item.embed; frame.title = item.title; frame.allowFullscreen = true; frame.referrerPolicy = 'strict-origin-when-cross-origin';
                const close = document.createElement('button'); close.type = 'button'; close.className = 'button'; close.textContent = 'Close preview'; close.addEventListener('click', () => { preview.replaceChildren(); play.focus(); });
                preview.replaceChildren(frame, close);
            });
            const add = document.createElement('button'); add.type = 'button'; add.className = 'button button-primary'; add.textContent = 'Add trailer'; add.dataset.addTrailer = item.url;
            add.addEventListener('click', () => {
                const values = urls();
                if (values.map(canonical).includes(item.url)) return;
                if (values.length >= 12) { status.textContent = 'Add at most 12 trailers. Remove a URL above to make room.'; return; }
                const override = field.closest('.acf-field, td')?.querySelector('input[name="override[trailers]"]');
                if (override) override.checked = true;
                field.value = [...values, item.url].join('\n'); field.dispatchEvent(new Event('input', {bubbles:true})); field.dispatchEvent(new Event('change', {bubbles:true}));
                status.textContent = 'Trailer added. Save or publish the record to apply it.'; refresh();
            });
            actions.append(play, add); card.append(image, heading, info, actions); results.append(card);
        });
        const load = async page => {
            const term = page === 1 ? query.value.trim() : currentQuery;
            if (!term) { status.textContent = 'Enter a title or search terms.'; return; }
            if (term.length > 160) { status.textContent = 'Use search terms up to 160 characters.'; return; }
            if (!window.mscTrailers?.configured) { status.textContent = 'Configure the Serper key in Image Search settings to search YouTube.'; return; }
            controller?.abort(); controller = new AbortController(); const request = ++generation;
            if (page === 1) { currentQuery = term; results.replaceChildren(); preview.replaceChildren(); }
            more.hidden = true; button.disabled = true; status.textContent = 'Searching YouTube…';
            try {
                const body = new URLSearchParams({action:'msc_search_trailers', nonce:mscTrailers.nonce, query:term, page:String(page)});
                const response = await fetch(mscTrailers.ajaxUrl, {method:'POST', credentials:'same-origin', body, signal:controller.signal});
                const payload = await response.json();
                if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'Trailer search failed. Retry.');
                if (request !== generation) return;
                render(payload.data.items); refresh(); nextPage = payload.data.next_page; more.hidden = !nextPage;
                status.textContent = payload.data.items.length ? 'Choose a trailer below.' : 'No YouTube trailers found. Try different search terms.';
            } catch (error) {
                if (request === generation && error.name !== 'AbortError') { status.textContent = error.message; more.hidden = page === 1; }
            } finally { if (request === generation) button.disabled = false; }
        };
        button.addEventListener('click', () => load(1));
        query.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); load(1); } });
        more.addEventListener('click', () => { if (nextPage) load(nextPage); });
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    if (window.acf?.addAction) acf.addAction('append', init);
})();
