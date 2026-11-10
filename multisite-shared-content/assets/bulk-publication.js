document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('#msc-bulk-publish');
    if (!root) return;
    let running = false, active = false;
    const start = root.querySelector('[data-bulk-start]');
    async function step() {
        if (!running || active) return;
        active = true; start.disabled = true;
        try {
            const response = await fetch(root.dataset.endpoint, {method: 'POST', credentials: 'same-origin', headers: {'X-WP-Nonce': root.dataset.nonce}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Publication could not continue.');
            root.querySelector('progress').value = data.index;
            root.querySelector('.msc-bulk-status').textContent = `${data.index} / ${data.total} processed`;
            root.querySelector('.msc-bulk-results').replaceChildren(...data.results.map(result => { const li = document.createElement('li'); li.textContent = `${result.title} — ${result.status}`; return li; }));
            if (data.index >= data.total) running = false;
        } catch (error) { running = false; root.querySelector('.msc-bulk-status').textContent = error.message; }
        finally { active = false; start.disabled = running; if (running) setTimeout(step, 100); }
    }
    start.addEventListener('click', () => { running = true; step(); });
    root.querySelector('[data-bulk-pause]').addEventListener('click', () => { running = false; start.disabled = active; });
});
