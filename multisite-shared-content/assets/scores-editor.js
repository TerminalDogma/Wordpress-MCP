(() => {
    const init = () => {
        const input = key => document.querySelector(`.acf-field[data-name="msc_${key}"] input, [name="fields[${key}]"]`);
        const anchor = input('metacritic_score');
        if (!anchor || document.querySelector('.msc-score-import') || !window.mscScoreImport) return;
        const panel = document.createElement('section'); panel.className = 'msc-score-import postbox'; panel.style.padding = '16px';
        const title = document.createElement('h2'); title.textContent = 'Import aggregate review scores';
        const description = document.createElement('p'); description.textContent = 'Enter the provider IDs in the fields below, then fetch scores. Check the matched titles before using them. Your editorial review always takes priority.';
        const fetchButton = document.createElement('button'); fetchButton.type = 'button'; fetchButton.className = 'button'; fetchButton.textContent = 'Fetch aggregate scores';
        const status = document.createElement('p'); status.setAttribute('role', 'status');
        const results = document.createElement('div');
        const use = document.createElement('button'); use.type = 'button'; use.className = 'button button-primary'; use.textContent = 'Use selected scores'; use.hidden = true;
        panel.append(title, description, fetchButton, status, results, use); anchor.closest('form').prepend(panel);
        let proposed = {};
        fetchButton.addEventListener('click', async () => {
            fetchButton.disabled = true; use.hidden = true; results.replaceChildren(); status.textContent = 'Fetching aggregate scores…';
            try {
                const body = new URLSearchParams({action:'msc_score_preview', nonce:mscScoreImport.nonce, record:mscScoreImport.record});
                ['imdb','rawg','opencritic'].forEach(p => { const field = input(`score_${p}_id`); if (field?.value.trim()) body.set(p, field.value.trim()); });
                const response = await fetch(mscScoreImport.ajaxUrl, {method:'POST', credentials:'same-origin', body});
                const payload = await response.json(); if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'Score lookup failed. Retry.');
                proposed = payload.data.values; status.textContent = [...payload.data.matches, ...payload.data.warnings].join(' · ');
                Object.keys(proposed).filter(key => key.endsWith('_score')).forEach(key => {
                    const line = document.createElement('p'); const label = document.createElement('label'); const check = document.createElement('input'); check.type = 'checkbox'; check.checked = true; check.dataset.score = key;
                    label.append(check, ` ${key.replace(/_score$/, '').replaceAll('_', ' ')}: ${proposed[key]}/100`); line.append(label); results.append(line);
                });
                use.hidden = !results.childElementCount;
                if (!results.childElementCount && !status.textContent) status.textContent = 'No aggregate scores are available yet.';
            } catch (error) { status.textContent = error.message; }
            finally { fetchButton.disabled = false; }
        });
        use.addEventListener('click', () => {
            const selected = [...results.querySelectorAll('input:checked')].map(check => check.dataset.score.replace(/_score$/, ''));
            Object.entries(proposed).forEach(([key, value]) => {
                const provider = ['rotten_tomatoes','metacritic','opencritic'].find(p => key.startsWith(p+'_'));
                if (provider && !selected.includes(provider)) return;
                if (!selected.length) return;
                const field = input(key); if (!field) return; field.value = value;
                const override = field.closest('.acf-field, td')?.querySelector(`input[name="override[${key}]"]`); if (override) override.checked = true;
                field.dispatchEvent(new Event('input', {bubbles:true})); field.dispatchEvent(new Event('change', {bubbles:true}));
            });
            status.textContent = selected.length ? 'Selected scores added to the form. Save or publish the item to apply them.' : 'Select at least one score.';
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
