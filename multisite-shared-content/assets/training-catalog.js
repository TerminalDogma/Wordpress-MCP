document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('#msc-training-import');
    if (!root) return;
    let running = false, active = false, retryAfter = 0;
    const button = root.querySelector('[data-training-start]');
    async function step() {
        if (!running || active) return;
        active = true; button.disabled = true;
        try {
            const response = await fetch(root.dataset.endpoint, {method: 'POST', credentials: 'same-origin', headers: {'X-WP-Nonce': root.dataset.nonce}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'The import could not continue.');
            retryAfter = Math.min(60, Math.max(0, Number(data.retry_after) || 0));
            root.querySelector('progress').value = data.index;
            root.querySelector('.msc-training-status').textContent = `${data.index} / 500 processed${retryAfter ? ' — waiting for source requests' : ''}`;
            root.querySelector('.msc-training-results').replaceChildren(...data.results.map(result => { const li = document.createElement('li'); li.textContent = `${result.title} — ${result.status}`; return li; }));
            if (data.index >= 500) running = false;
        } catch (error) { running = false; root.querySelector('.msc-training-status').textContent = error.message; }
        finally { active = false; button.disabled = running; if (running) setTimeout(step, Math.max(100, retryAfter * 1000)); }
    }
    button.addEventListener('click', () => { running = true; step(); });
    root.querySelector('[data-training-pause]').addEventListener('click', () => { running = false; button.disabled = active; });
});
