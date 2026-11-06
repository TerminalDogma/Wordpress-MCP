(() => {
    for (const control of document.querySelectorAll('.msc-game-dates')) {
        const input = control.querySelector('.msc-game-dates-value'), list = control.querySelector('.msc-game-date-rows');
        let rows = JSON.parse(input.value || '[]');
        const sync = () => { input.value = JSON.stringify(rows); const override = control.closest('td').querySelector('input[name="override[release_dates]"]'); if (override) override.checked = true; };
        const render = () => {
            list.replaceChildren();
            rows.forEach((row, index) => {
                const group = document.createElement('fieldset'); group.className = 'msc-game-date-row';
                const legend = document.createElement('legend'); legend.textContent = 'Release ' + (index + 1); group.append(legend);
                for (const [key, title, type] of [['platform','Platform','text'],['platform_id','IGDB platform ID','number'],['region','Region','text'],['precision','Precision','select'],['date','Exact day','date'],['display','Release window / label','text']]) {
                    const label = document.createElement('label'); label.append(title + ' ');
                    const field = document.createElement(type === 'select' ? 'select' : 'input');
                    if (type === 'select') for (const precision of ['day','month','year','quarter','tbd']) { const option = document.createElement('option'); option.value = precision; option.textContent = precision; field.append(option); }
                    else field.type = type;
                    field.value = row[key] ?? (key === 'precision' ? 'day' : ''); field.dataset.field = key;
                    if (key === 'platform_id') { field.min = '0'; field.step = '1'; }
                    field.addEventListener('input', () => { row[key] = key === 'platform_id' ? Number(field.value || 0) : field.value; sync(); });
                    label.append(field); group.append(label);
                }
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button'; remove.textContent = 'Remove release'; remove.addEventListener('click', () => { rows.splice(index, 1); sync(); render(); }); group.append(remove); list.append(group);
            });
        };
        control.querySelector('.msc-game-date-add').addEventListener('click', () => { rows.push({platform:'', platform_id:0, region:'', precision:'tbd', date:'', display:'TBD'}); sync(); render(); list.lastElementChild.querySelector('input').focus(); }); render();
    }
})();
