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
    document.addEventListener('click', function (event) {
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
