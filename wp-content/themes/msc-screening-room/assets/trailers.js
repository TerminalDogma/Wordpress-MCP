(() => {
    document.querySelectorAll('[data-trailer-carousel]').forEach(carousel => {
        const slides = [...carousel.querySelectorAll('.trailer-slide')];
        const controls = carousel.querySelector('.trailer-controls');
        const previous = carousel.querySelector('[data-trailer-prev]');
        const next = carousel.querySelector('[data-trailer-next]');
        const status = carousel.querySelector('[data-trailer-status]');
        if (slides.length < 2) return;
        let index = 0;
        controls.hidden = false;
        const show = destination => {
            if (destination < 0 || destination >= slides.length || destination === index) return;
            slides[index].hidden = true;
            slides[index].querySelector('iframe').removeAttribute('src');
            index = destination;
            slides[index].hidden = false;
            const player = slides[index].querySelector('iframe');
            player.src = player.dataset.src;
            previous.disabled = index === 0;
            next.disabled = index === slides.length - 1;
            status.textContent = `Trailer ${index + 1} of ${slides.length}`;
        };
        previous.addEventListener('click', () => show(index - 1));
        next.addEventListener('click', () => show(index + 1));
        controls.addEventListener('keydown', event => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            show(index + (event.key === 'ArrowRight' ? 1 : -1));
            (index === slides.length - 1 ? previous : next).focus();
        });
    });
})();
