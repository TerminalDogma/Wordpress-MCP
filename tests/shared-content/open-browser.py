"""Commercial-source UI and real sequential Episode imports through Apache/WordPress."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import parse_qs, urlparse
from playwright.async_api import async_playwright

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Requires the disposable browser-test helper.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Local tests only.')

def wp(code):
    output = subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', '33', 'wordpress', 'wp', '--url=' + base + '/site-a/', 'eval', code], cwd=root, text=True)
    return json.loads(output) if output.strip() else None

async def main():
    seed = int(wp('echo wp_json_encode(get_network_option(get_current_network_id(),"msc_open_fixture_seed"));'))
    assert isinstance(seed, int)
    async with async_playwright() as playwright:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'):
            options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await playwright.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1000})
        async def route(request):
            if urlparse(request.request.url).hostname in ('localhost', '127.0.0.1'):
                await request.continue_()
            elif 'msc-open-fixture.png' in request.request.url:
                await request.fulfill(status=200, content_type='image/svg+xml', body='<svg xmlns="http://www.w3.org/2000/svg" width="100" height="160"><rect width="100" height="160" fill="#41725f"/></svg>')
            else:
                await request.abort()
        await context.route('**/*', route)
        page = await context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        await page.goto(base + '/wp-login.php')
        await page.fill('#user_login', config['WORDPRESS_ADMIN_USER'])
        await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD'])
        await page.click('#wp-submit')
        await page.wait_for_url('**/wp-admin/**')
        async def navigate(role, name, first=False):
            async with page.expect_navigation(wait_until='load', timeout=60000):
                locator = page.get_by_role(role, name=name, exact=True)
                await (locator.first if first else locator).click()
        async def search(kind, query):
            await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-import')
            await page.locator('select[name=kind]').select_option(kind)
            await page.locator('input[name=q]').fill(query)
            await navigate('button', 'Search')
            await page.wait_for_load_state()
        async def receipt():
            await page.wait_for_url('**done=*', timeout=60000)
            href = await page.get_by_role('link', name='All fields and publishing', exact=True).get_attribute('href')
            return parse_qs(urlparse(href).query)['record'][0]
        await search('msc_episode', 'Open Show ' + str(seed))
        await navigate('link', 'Choose episodes from Open Show ' + str(seed))
        assert 'Import or connect this TV Show' in await page.locator('body').inner_text(), await page.locator('.wrap.msc').inner_text()
        await navigate('link', 'Import TV Show first')
        assert await page.locator('.msc-omdb-preview img').count() == 0, 'TVmaze images must be excluded.'
        await navigate('button', 'Create shared record')
        show = await receipt()
        await navigate('link', 'Choose seasons and episodes')
        await navigate('link', 'Season 1')
        await page.wait_for_load_state()
        assert await page.locator('input[name="episodes[]"]').count() == 3, (await page.locator('.wrap.msc').inner_text())
        assert 'Special / unnumbered' in await page.locator('body').inner_text()
        await page.locator('[data-msc-select-episodes]').check()
        await navigate('button', 'Import selected episodes')
        await page.wait_for_url('**job=*')
        await page.get_by_role('button', name='Start / resume', exact=True).click()
        await page.wait_for_function('document.querySelector("progress").value === 3', timeout=180000)
        assert await page.locator('.msc-job-results li').count() == 3
        assert 'Needs review:' not in await page.locator('.msc-job-results').inner_text(), await page.locator('.msc-job-results').inner_text()
        await page.reload()
        assert await page.locator('progress').get_attribute('value') == '3', 'Batch progress must survive reload.'
        episodes = wp('echo wp_json_encode(array_map(fn($id)=>\\MSC\\Plugin::store()->get($id),\\MSC\\Plugin::store()->query("msc_episode",["parent_show"=>"' + show + '"],0,100)));')
        assert len(episodes) == 3, episodes
        assert sorted(ep['data'].get('episode_number', -1) for ep in episodes) == [-1, 1, 2]
        assert all(ep['data']['parent_show'] == show for ep in episodes)
        await search('msc_movie', 'Open Film ' + str(seed))
        await navigate('link', 'Preview Open Film ' + str(seed))
        assert 'Wikipedia contributors' in await page.locator('.msc-omdb-preview').inner_text()
        await page.locator('input[name=image]').check()
        await navigate('button', 'Create shared record')
        movie = await receipt()
        await search('msc_person', 'Alex Sharedname ' + str(seed))
        assert await page.locator('.msc-omdb-result').count() == 2, 'Namesakes remain separately selectable.'
        await navigate('link', 'Preview Alex Sharedname', first=True)
        assert await page.locator('input[name="fields[]"]:checked').count() == 0
        for field in ['content', 'excerpt', 'birth_date', 'country', 'professions']:
            await page.locator('input[name="fields[]"][value=' + field + ']').check()
        await page.locator('input[name=image]').check()
        await navigate('button', 'Import selected global fields')
        person = await receipt()
        data = wp('$r=\\MSC\\Plugin::store()->get("' + person + '");echo wp_json_encode($r["data"]);')
        assert data['birth_date'] == '1980-04-05' and 'Biography' in data['content']
        assert data['external_ids']['wikidata'] == 'Q' + str(seed + 102)
        assert data['headshot']['attachment_id'] > 0
        wp('wp_set_current_user(1);foreach(["' + movie + '","' + person + '"] as $uuid){$r=\\MSC\\Plugin::store()->resolved($uuid);\\MSC\\Plugin::store()->local($uuid,[],[],(int)$r["mapping"]["local_version"],"publish");}')
        for uuid in [movie, person]:
            url = wp('$r=\\MSC\\Plugin::store()->resolved("' + uuid + '");echo wp_json_encode(get_permalink((int)$r["mapping"]["post_id"]));')
            await page.goto(url)
            assert 'Wikipedia contributors' in '\n'.join(await page.locator('.msc-attribution').all_inner_texts())
            assert 'Fixture Photographer' in await page.locator('.hero-art .msc-image-credit').inner_text()
            assert await page.locator('.hero-art img').evaluate('(img) => img.complete && img.naturalWidth > 0')
        await page.set_viewport_size({'width': 390, 'height': 844})
        assert await page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'Credits must fit on mobile.'
        assert not errors, errors
        await browser.close()
    print('PASS: Browser Show → season → resumable Episode import, Movie/Person enrichment, identity reuse, licensed media, ACF-compatible records and desktop/mobile attribution.')

try:
    asyncio.run(main())
except Exception:
    report = traceback.format_exc()
    for key in ['WORDPRESS_ADMIN_PASSWORD', 'OMDB_API_KEY']:
        if config.get(key):
            report = report.replace(config[key], '[redacted]')
    raise SystemExit(report)
