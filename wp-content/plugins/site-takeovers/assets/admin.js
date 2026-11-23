(() => {
    document.querySelectorAll('[data-sat-media]').forEach(field => {
        const value = field.querySelector('input');
        const preview = field.querySelector('[data-sat-preview]');
        const remove = field.querySelector('[data-sat-remove]');
        const frame = wp.media({title: 'Select campaign artwork', library: {type: 'image'}, multiple: false});
        frame.on('open', () => {
            const selection = frame.state().get('selection');
            selection.reset(value.value && value.value !== '0' ? [wp.media.attachment(value.value)] : []);
        });
        frame.on('select', () => {
            const image = frame.state().get('selection').first().toJSON();
            value.value = image.id;
            const thumbnail = new Image();
            thumbnail.src = image.sizes?.thumbnail?.url || image.url;
            thumbnail.alt = image.alt || '';
            thumbnail.style.maxWidth = '150px';
            thumbnail.style.height = 'auto';
            preview.replaceChildren(thumbnail);
            remove.hidden = false;
        });
        field.querySelector('[data-sat-choose]').addEventListener('click', () => frame.open());
        remove.addEventListener('click', () => {
            value.value = '';
            preview.replaceChildren();
            remove.hidden = true;
        });
    });
})();
