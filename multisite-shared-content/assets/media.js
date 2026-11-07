/* Image previews with portable owner-site references, shared by MSC and ACF. */
(function (wp) {
    'use strict';
    function changed(control) {
        var scope = control.closest('tr, .acf-field');
        var override = scope && scope.querySelector('input[name^="override["]');
        if (override) { override.checked = true; }
        control.querySelector('.msc-media-value').dispatchEvent(new Event('change', {bubbles: true}));
    }
    function empty(container) {
        if (!container.children.length) {
            var p = document.createElement('p'); p.className = 'msc-media-empty';
            p.textContent = 'No media selected.'; container.appendChild(p);
        }
    }
    function card(model) {
        var data = model.toJSON(), figure = document.createElement('figure');
        figure.className = 'msc-media-card'; figure.dataset.site = window.mscMediaSite;
        figure.dataset.attachment = model.id;
        var link = document.createElement('a'); link.href = data.url;
        link.target = '_blank'; link.rel = 'noopener';
        if (data.type === 'image') {
            var img = document.createElement('img');
            img.src = (data.sizes && (data.sizes.medium || data.sizes.thumbnail) || data).url;
            img.alt = data.alt || data.title || 'Selected image'; link.appendChild(img);
        } else { link.textContent = 'View ' + (data.filename || 'media'); }
        figure.appendChild(link);
        var caption = document.createElement('figcaption'); caption.textContent = data.title || data.filename || 'Media';
        var owner = document.createElement('small'); owner.textContent = 'Library: site ' + window.mscMediaSite;
        caption.appendChild(owner); figure.appendChild(caption);
        var remove = document.createElement('button'); remove.type = 'button';
        remove.className = 'button msc-remove-media'; remove.textContent = 'Remove';
        remove.setAttribute('aria-label', 'Remove ' + (data.title || 'media'));
        figure.appendChild(remove); return figure;
    }
    function googleQuery(panel, useTitle) {
        var query = panel.querySelector('.msc-image-query');
        var title = document.querySelector('#msc-title');
        if (useTitle && title && title.value.trim() && !panel.dataset.queryEdited) {
            query.value = title.value.trim() + ' ' + panel.dataset.imageKind;
        }
    }
    var searches = new WeakMap();
    function resultCard(item) {
        var article = document.createElement('article'); article.className = 'msc-image-result'; article.dataset.imageUrl = item.url;
        var preview = document.createElement('div'); preview.className = 'msc-image-result-preview';
        if (item.thumbnail) {
            var image = document.createElement('img'); image.src = item.thumbnail; image.alt = item.title; image.loading = 'lazy'; image.referrerPolicy = 'no-referrer';
            image.addEventListener('error', function () { preview.textContent = 'Preview unavailable'; }); preview.appendChild(image);
        } else preview.textContent = 'Preview unavailable';
        article.appendChild(preview);
        var title = document.createElement('strong'); title.textContent = item.title; article.appendChild(title);
        var facts = document.createElement('small'); facts.textContent = (item.width && item.height ? item.width + ' × ' + item.height + ' · ' : '') + (item.source || 'Image source'); article.appendChild(facts);
        if (item.source_url) { var link = document.createElement('a'); link.href = item.source_url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Source page'; article.appendChild(link); }
        var use = document.createElement('button'); use.type = 'button'; use.className = 'button msc-import-search-image'; use.textContent = 'Import image'; use.mscImageResult = item; article.appendChild(use);
        return article;
    }
    async function searchImages(panel, page) {
        var query = panel.querySelector('.msc-image-query').value.trim();
        var area = panel.querySelector('.msc-image-search-results'), grid = panel.querySelector('.msc-image-result-grid'), status = panel.querySelector('.msc-image-search-status');
        var button = panel.querySelector('.msc-search-google'), more = panel.querySelector('.msc-image-search-more');
        area.hidden = false;
        if (!query || Array.from(query).length > 160) { status.textContent = 'Enter search terms up to 160 characters.'; return; }
        if (!window.mscMediaImport) { status.textContent = 'Refresh the editor before searching.'; return; }
        var previous = searches.get(panel); if (previous && previous.controller) previous.controller.abort();
        var state = {controller: new AbortController(), query: query, page: page, next: null}; searches.set(panel, state);
        if (page === 1) grid.replaceChildren();
        grid.setAttribute('aria-busy', 'true'); button.disabled = true; more.hidden = true; status.textContent = 'Searching Google Images…';
        try {
            var response = await fetch(window.mscMediaImport.url, {method: 'POST', credentials: 'same-origin', signal: state.controller.signal,
                body: new URLSearchParams({action: 'msc_search_images', nonce: window.mscMediaImport.nonce, query: query, page: page})});
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Image search failed. Please retry.');
            if (searches.get(panel) !== state || !state.controller || state.controller.signal.aborted) return;
            result.data.items.forEach(function (item) { if (!Array.from(grid.children).some(function (card) { return card.dataset.imageUrl === item.url; })) grid.appendChild(resultCard(item)); });
            state.next = result.data.next_page; more.hidden = !state.next;
            status.textContent = grid.children.length ? grid.children.length + ' images · Page ' + result.data.page : 'No images found. Try different search terms.';
        } catch (error) {
            if (error.name !== 'AbortError' && searches.get(panel) === state && state.controller) status.textContent = error.message && !(error instanceof TypeError) && !(error instanceof SyntaxError) ? error.message : 'Image search failed. Please retry.';
        } finally {
            if (searches.get(panel) === state) { grid.setAttribute('aria-busy', 'false'); button.disabled = false; }
        }
    }
    async function importImage(button) {
        var panel = button.closest('.msc-web-image'), control = button.closest('.msc-media-control');
        var status = panel.querySelector('.msc-image-import-status');
        var url = panel.querySelector('.msc-image-url').value.trim();
        try { if (new URL(url).protocol !== 'https:') throw new Error(); }
        catch (_) { status.textContent = 'Paste a complete HTTPS image URL.'; return; }
        if (!window.mscMediaImport) { status.textContent = 'Refresh the editor before importing an image.'; return; }
        button.disabled = true; status.textContent = 'Importing image…';
        var originalLabel = button.textContent; button.textContent = 'Importing…';
        try {
            var title = document.querySelector('#msc-title');
            var response = await fetch(window.mscMediaImport.url, {
                method: 'POST', credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'msc_import_image_url', nonce: window.mscMediaImport.nonce, url: url,
                    title: title ? title.value.trim() : panel.querySelector('.msc-image-query').value.trim(),
                    source_page: panel.querySelector('.msc-image-source-url').value.trim(),
                    credit: panel.querySelector('.msc-image-credit-input').value.trim()
                })
            });
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'The image could not be imported. Refresh the editor and try again.');
            var reference = result.data.reference;
            if (!reference || Number(reference.site_id) !== Number(window.mscMediaSite) || !result.data.attachment) throw new Error('The image response was invalid. Refresh the editor and try again.');
            var model = wp.media.attachment(reference.attachment_id); model.set(result.data.attachment);
            var input = control.querySelector('.msc-media-value'), previews = control.querySelector('.msc-media-previews');
            if (control.dataset.multiple === '1') {
                var refs = JSON.parse(input.value || '[]');
                refs.push(reference); input.value = JSON.stringify(refs);
                var placeholder = previews.querySelector('.msc-media-empty'); if (placeholder) placeholder.remove();
            } else { input.value = JSON.stringify(reference); previews.replaceChildren(); }
            previews.appendChild(card(model)); changed(control);
            panel.querySelector('.msc-image-url').value = '';
            status.textContent = 'Image added to this site’s media library. Save the record to apply it.';
            if (button.classList.contains('msc-import-search-image')) button.dataset.imported = '1';
        } catch (error) {
            status.textContent = error.message && !(error instanceof TypeError) && !(error instanceof SyntaxError) ? error.message : 'The image could not be imported. Check the image URL and try again.';
        } finally { button.disabled = button.dataset.imported === '1'; button.textContent = button.disabled ? 'Imported' : originalLabel; }
    }
    document.addEventListener('toggle', function (event) {
        if (event.target.matches('.msc-web-image') && event.target.open) googleQuery(event.target, true);
    }, true);
    document.addEventListener('input', function (event) {
        if (event.target.matches('.msc-image-query')) {
            var panel = event.target.closest('.msc-web-image'); panel.dataset.queryEdited = 'true';
            var state = searches.get(panel); if (state && state.controller) { state.controller.abort(); state.controller = null; panel.querySelector('.msc-image-result-grid').setAttribute('aria-busy', 'false'); panel.querySelector('.msc-search-google').disabled = false; panel.querySelector('.msc-image-search-status').textContent = ''; }
            panel.querySelector('.msc-image-search-more').hidden = true;
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        if (event.target.matches('.msc-image-query')) { event.preventDefault(); event.target.closest('.msc-web-image').querySelector('.msc-search-google').click(); }
        if (event.target.matches('.msc-image-url')) { event.preventDefault(); event.target.closest('.msc-web-image').querySelector('.msc-import-image-url').click(); }
    });
    document.addEventListener('click', function (event) {
        var searchButton = event.target.closest('.msc-search-google,.msc-image-search-more');
        if (searchButton) { var searchPanel = searchButton.closest('.msc-web-image'); var searchState = searches.get(searchPanel); var more = searchButton.classList.contains('msc-image-search-more') && searchState && searchState.query === searchPanel.querySelector('.msc-image-query').value.trim(); searchImages(searchPanel, more ? searchState.next : 1); return; }
        var selected = event.target.closest('.msc-import-search-image');
        if (selected) { if (selected.disabled) return; var selectedPanel = selected.closest('.msc-web-image'); selectedPanel.querySelector('.msc-image-url').value = selected.mscImageResult.url; selectedPanel.querySelector('.msc-image-source-url').value = selected.mscImageResult.source_url || ''; importImage(selected); return; }
        var importButton = event.target.closest('.msc-import-image-url');
        if (importButton) { if (!importButton.disabled) importImage(importButton); return; }
        var remove = event.target.closest('.msc-remove-media');
        if (remove) {
            var owner = remove.closest('.msc-media-control'), input = owner.querySelector('.msc-media-value');
            remove.closest('.msc-media-card').remove();
            var refs = Array.from(owner.querySelectorAll('.msc-media-card')).map(function (item) {
                return {site_id: Number(item.dataset.site), attachment_id: Number(item.dataset.attachment)};
            });
            input.value = JSON.stringify(owner.dataset.multiple === '1' ? refs : (refs[0] || []));
            empty(owner.querySelector('.msc-media-previews')); changed(owner); return;
        }
        var button = event.target.closest('.msc-choose-media');
        if (!button || !wp.media) { return; }
        var control = button.closest('.msc-media-control');
        var multiple = control.dataset.multiple === '1';
        var frame = wp.media({title: 'Choose shared content media', multiple: multiple, button: {text: 'Use this media'}});
        frame.on('select', function () {
            var models = frame.state().get('selection').toArray();
            var references = models.map(function (model) { return {site_id: window.mscMediaSite, attachment_id: model.id}; });
            control.querySelector('.msc-media-value').value = JSON.stringify(multiple ? references : (references[0] || []));
            var previews = control.querySelector('.msc-media-previews'); previews.replaceChildren();
            models.forEach(function (model) { previews.appendChild(card(model)); });
            empty(previews); changed(control);
        });
        frame.open();
    });
})(window.wp);
