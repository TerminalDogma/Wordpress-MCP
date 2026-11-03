/* Native WordPress media selection with explicit owner-site references. */
(function (wp) {
    'use strict';
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.msc-choose-media');
        if (!button || !wp.media) { return; }
        var input = document.getElementById(button.dataset.field);
        var multiple = button.dataset.multiple === '1';
        var frame = wp.media({title: 'Choose shared content media', multiple: multiple, button: {text: 'Use this media'}});
        frame.on('select', function () {
            var references = frame.state().get('selection').toArray().map(function (model) {
                return {site_id: window.mscMediaSite, attachment_id: model.id};
            });
            input.value = JSON.stringify(multiple ? references : (references[0] || []), null, 2);
            var override = input.closest('tr').querySelector('input[type="checkbox"]');
            if (override) { override.checked = true; }
        });
        frame.open();
    });
})(window.wp);
