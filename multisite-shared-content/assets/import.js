/* Sequential server-side imports; the browser holds no API credentials or source payloads. */
document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('[data-msc-select-episodes]');
    if (select) select.addEventListener('change', () => document.querySelectorAll('input[name="episodes[]"]').forEach(input => { input.checked = select.checked; }));
    const job = document.querySelector('#msc-import-job');
    if (!job) return;
    const resume = job.querySelector('[data-msc-resume]');
    const pause = job.querySelector('[data-msc-pause]');
    const status = job.querySelector('[role="status"]');
    const progress = job.querySelector('progress');
    const results = job.querySelector('.msc-job-results');
    let running = false;
    let busy = false;
    let retries = 0;
    pause.addEventListener('click', () => { running = false; status.textContent = 'Pausing after the current episode…'; });
    async function run() {
        if (busy || Number(progress.value) >= Number(job.dataset.total)) return;
        running = true; busy = true; resume.disabled = true;
        try {
            while (running && Number(progress.value) < Number(job.dataset.total)) {
                status.textContent = `Importing episode ${Number(progress.value) + 1} of ${job.dataset.total}…`;
                const body = new URLSearchParams({action: 'msc_open_step', token: job.dataset.token, nonce: job.dataset.nonce});
                const response = await fetch(window.ajaxurl, {method: 'POST', credentials: 'same-origin', body});
                const result = await response.json();
                if (!result.success) {
                    if (result.data && result.data.retry && retries++ < 5 && running) {
                        status.textContent = result.data.message + ' Retrying automatically…';
                        await new Promise(resolve => setTimeout(resolve, 1000 * (Number(result.data.wait) || 15)));
                        continue;
                    }
                    throw new Error(result.data && result.data.message || 'Import paused. Reload to renew your session, then resume.');
                }
                retries = 0; progress.value = result.data.index;
                results.replaceChildren(...result.data.results.map(row => { const li = document.createElement('li'); li.textContent = `${row.title} — ${row.status}`; return li; }));
            }
            const done = Number(progress.value) >= Number(job.dataset.total);
            status.textContent = done ? `${job.dataset.total} episodes processed. Review the results below; saved Episodes follow your publication defaults.` : `${progress.value} / ${job.dataset.total} processed. Paused; resume when ready.`;
        } catch (error) { running = false; status.textContent = error.message || 'Connection interrupted. Resume to continue from the last saved episode.'; }
        finally { busy = false; resume.disabled = false; }
    }
    resume.addEventListener('click', run);
});
