(() => {
    document.querySelectorAll('[data-trailer-carousel]').forEach(carousel => {
        const slides = [...carousel.querySelectorAll('.trailer-slide')];
        const stage = carousel.querySelector('.trailer-stage');
        const controls = carousel.querySelector('.trailer-controls');
        const previous = carousel.querySelector('[data-trailer-prev]');
        const next = carousel.querySelector('[data-trailer-next]');
        const status = carousel.querySelector('[data-trailer-status]');
        if (slides.length < 2) return;
        const capacity = () => Math.max(1, parseInt(getComputedStyle(stage).getPropertyValue('--trailer-columns'), 10) || 1);
        let size = capacity(), page = 0;
        const lastPage = () => Math.ceil(slides.length / size) - 1;
        const render = () => {
            const start = page * size, end = Math.min(start + size, slides.length);
            slides.forEach((slide, index) => {
                const visible = index >= start && index < end;
                slide.hidden = !visible;
                const player = slide.querySelector('iframe');
                if (visible && !player.hasAttribute('src')) player.src = player.dataset.src;
                else if (!visible && player.hasAttribute('src')) player.removeAttribute('src');
            });
            controls.hidden = slides.length <= size;
            previous.disabled = page === 0;
            next.disabled = page === lastPage();
            status.textContent = end - start === 1 ? `Trailer ${start + 1} of ${slides.length}` : `Trailers ${start + 1}–${end} of ${slides.length}`;
        };
        const show = destination => {
            if (destination < 0 || destination > lastPage() || destination === page) return;
            page = destination;
            render();
        };
        previous.addEventListener('click', () => show(page - 1));
        next.addEventListener('click', () => show(page + 1));
        controls.addEventListener('keydown', event => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            show(page + (event.key === 'ArrowRight' ? 1 : -1));
            (page === lastPage() ? previous : next).focus();
        });
        window.addEventListener('resize', () => {
            const updated = capacity();
            if (updated === size) return;
            const first = page * size;
            size = updated;
            page = Math.min(Math.floor(first / size), lastPage());
            render();
        });
        render();
    });
})();
