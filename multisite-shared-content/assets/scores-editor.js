(() => {
    const init = () => {
        const input = key => document.querySelector(`.acf-field[data-name="msc_${key}"] input, .acf-field[data-name="msc_${key}"] select, [name="fields[${key}]"]`);
        const anchor = input('metacritic_score');
        if (!anchor || document.querySelector('.msc-score-import') || !window.mscScoreImport) return;
        const panel = document.createElement('section'); panel.className = 'msc-score-import postbox'; panel.style.padding = '16px';
        const title = document.createElement('h2'); title.textContent = 'Import aggregate review scores';
        const description = document.createElement('p'); description.textContent = mscScoreImport.wikipedia ? 'Fetch scores from Wikipedia, choose the matching article and platform, then save selected values. Your editorial review always takes priority.' : 'Fetch Movie/TV scores using the existing OMDb setup, review the matched title, then save selected values. Your editorial review always takes priority.';
        const fetchButton = document.createElement('button'); fetchButton.type = 'button'; fetchButton.className = 'button'; fetchButton.textContent = 'Fetch aggregate scores';
        const status = document.createElement('p'); status.setAttribute('role', 'status');
        const wiki = document.createElement('select'); wiki.hidden = true; wiki.setAttribute('aria-label', 'Wikipedia article');
        wiki.addEventListener('change', () => { const field = input('score_wikipedia_title'); if (field && wiki.value) { field.value = wiki.value; field.dispatchEvent(new Event('change', {bubbles:true})); status.textContent = 'Article selected. Fetch aggregate scores to preview it.'; } });
        const results = document.createElement('div');
        const use = document.createElement('button'); use.type = 'button'; use.className = 'button button-primary'; use.textContent = 'Use selected scores'; use.hidden = true;
        panel.append(title, description, fetchButton, status, wiki, results, use); anchor.closest('form').prepend(panel);
        const providerNames = {rotten_tomatoes:'Rotten Tomatoes', metacritic:'Metacritic', opencritic:'OpenCritic'};
        let proposed = {};
        fetchButton.addEventListener('click', async () => {
            fetchButton.disabled = true; use.hidden = true; results.replaceChildren(); status.textContent = 'Fetching aggregate scores…';
            try {
                const body = new URLSearchParams({action:'msc_score_preview', nonce:mscScoreImport.nonce, record:mscScoreImport.record});
                const imdb = input('score_imdb_id'); if (imdb?.value.trim()) body.set('imdb', imdb.value.trim());
                const article = input('score_wikipedia_title'); if (article?.value.trim()) body.set('wikipedia', article.value.trim());
                const response = await fetch(mscScoreImport.ajaxUrl, {method:'POST', credentials:'same-origin', body});
                const payload = await response.json(); if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'Score lookup failed. Retry.');
                wiki.replaceChildren(); const candidates = payload.data.wiki_candidates || []; wiki.hidden = !candidates.length;
                if (candidates.length) { const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Choose the matching Wikipedia article'; wiki.append(placeholder); candidates.forEach(item => { const option = document.createElement('option'); option.value = item.title; option.textContent = item.title+' — '+item.description; wiki.append(option); }); }
                proposed = payload.data.values; status.textContent = [...payload.data.matches, ...payload.data.warnings].join(' · ');
                Object.keys(proposed).filter(key => key.endsWith('_score')).forEach(key => {
                    const line = document.createElement('p'); const label = document.createElement('label'); const check = document.createElement('input'); check.type = 'checkbox'; check.checked = true; check.dataset.score = key;
                    const provider = key.replace(/_score$/, ''); const text = document.createTextNode('');
                    const describe = () => { text.textContent = ` ${providerNames[provider] || provider}: ${proposed[key]}${provider === 'rotten_tomatoes' ? '%' : provider === 'opencritic' && proposed.opencritic_metric === 'recommended' ? '% critics recommend' : '/100'}`; };
                    describe(); label.append(check, text); line.append(label);
                    const options = payload.data.choices?.[provider] || [];
                    if (options.length) { const select = document.createElement('select'); select.dataset.providerChoice = provider; select.setAttribute('aria-label', provider+' platform or measure'); options.forEach((item,index) => { const option = document.createElement('option'); option.value = String(index); option.textContent = item.label; select.append(option); }); select.style.marginLeft = '12px'; select.addEventListener('change', () => { Object.assign(proposed, options[Number(select.value)].values); describe(); }); line.append(select); }
                    results.append(line);
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
