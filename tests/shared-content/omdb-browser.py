"""Browser OMDb workflow using an explicitly enabled local provider fixture plugin."""
import asyncio
import json
import os
from pathlib import Path
import re
import subprocess
import traceback
from urllib.parse import parse_qs, urlencode, urlparse
from playwright.async_api import async_playwright

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Requires the explicitly disposable OMDb browser-test helper.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Tests are local only.')
screenshots = Path('/tmp/msc-omdb-screenshots')
screenshots.mkdir(exist_ok=True)

def wp(code, site='a'):
    out = subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', '33', 'wordpress', 'wp', '--url=' + base + '/site-' + site + '/', 'eval', code], cwd=root, text=True)
    return json.loads(out) if out.strip() else None

async def main():
    imdb = wp('echo wp_json_encode(get_network_option(get_current_network_id(),"msc_omdb_browser_id"));')
    assert re.fullmatch(r'tt[0-9]{9}', imdb)
    title = 'OMDb Browser ' + imdb
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'):
            options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1000})
        requests, errors = [], []
        intentional_error_navigation = False
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost', '127.0.0.1'):
                await route.continue_()
            elif route.request.resource_type == 'image' and 'msc-omdb-fixture.jpg' in route.request.url:
                await route.fulfill(status=200, content_type='image/svg+xml', body='<svg xmlns="http://www.w3.org/2000/svg" width="64" height="96"><rect width="64" height="96" fill="#205f44"/></svg>')
            else:
                await route.abort()
        await context.route('**/*', local_only)
        context.on('request', lambda req: requests.append(req.url))
        page = await context.new_page()
        page.on('pageerror', lambda error: errors.append({'message': str(error), 'intentional_error_navigation': intentional_error_navigation}))
        await page.goto(base + '/wp-login.php')
        await page.fill('#user_login', config['WORDPRESS_ADMIN_USER'])
        await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD'])
        await page.click('#wp-submit')
        await page.wait_for_url('**/wp-admin/**')
        await page.goto(base + '/wp-admin/network/admin.php?page=msc-omdb-settings')
        assert 'Status: Configured' in await page.locator('body').inner_text()
        assert 'msc-test-key' not in await page.content(), 'API keys must never be rendered.'

        async def search(site='a', record=''):
            await page.goto(base + '/site-' + site + '/wp-admin/admin.php?page=msc-omdb' + ('&record=' + record if record else ''))
            await page.locator('input[name=q]').fill(title)
            await page.locator('select[name=kind]').select_option('movie')
            await page.locator('input[name=year]').fill('2003')
            await page.get_by_role('button', name='Search titles', exact=True).click()
            await page.wait_for_load_state()
            assert await page.locator('.msc-omdb-result').count() == 1, 'Year/type search should isolate the requested title.'
            await page.get_by_role('link', name='Preview ' + title, exact=True).click()
            await page.wait_for_load_state()
            assert await page.locator('.msc-omdb-preview h2').inner_text() == title

        await search()
        assert await page.get_by_role('button', name='Create shared record from OMDb', exact=True).count() == 1
        await page.locator('input[name="fields[]"][value=content]').uncheck()
        await page.screenshot(path=str(screenshots / 'omdb-preview.png'), full_page=True)
        form = page.locator('form:has(input[name=action][value=msc_omdb_import])')
        replay = await form.evaluate('(el) => Array.from(new FormData(el).entries())')
        await page.get_by_role('button', name='Create shared record from OMDb', exact=True).click()
        await page.wait_for_url('**done=*', timeout=60000)
        assert 'OMDb import complete' in await page.locator('body').inner_text()
        href = await page.get_by_role('link', name='All fields and publishing', exact=True).get_attribute('href')
        uuid = parse_qs(urlparse(href).query)['record'][0]
        assert re.fullmatch('[a-f0-9-]{36}', uuid)
        check = wp('$r=msc_get_record("' + uuid + '");echo wp_json_encode(["data"=>$r["data"],"status"=>$r["mapping"]["status"]]);')
        assert check['data']['external_ids']['imdb'] == imdb
        assert 'content' not in check['data'], 'Unchecked source fields must not be imported.'
        assert check['status'] == 'draft'
        assert check['data']['poster']['site_id'] == 2
        assert len(check['data']['actors']) == 2
        # Replaying a create POST must not create another shared title or People.
        response = await page.request.post(base + '/site-a/wp-admin/admin-post.php', data=urlencode([tuple(item) for item in replay]), headers={'Content-Type': 'application/x-www-form-urlencoded'})
        assert response.status == 200
        count = wp('echo wp_json_encode(count(\\MSC\\OmdbImport::matches("' + imdb + '")));')
        assert count == 1
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + uuid)
        image = page.locator('.acf-field[data-name=msc_poster] .msc-media-control img')
        assert await image.evaluate('(el) => el.complete && el.naturalWidth > 0'), 'Imported media must render in ACF.'
        # Set up a curated global title and a site-specific runtime to preserve.
        wp('wp_set_current_user(1);$r=\\MSC\\Plugin::store()->get("' + uuid + '");\\MSC\\Plugin::store()->update($r["uuid"],["title"=>"Curated Reloaded","runtime"=>120,"external_ids"=>array_replace($r["data"]["external_ids"],["tmdb"=>77])],(int)$r["version"]);for($i=0;$i<100&&\\MSC\\Queue::status()["pending"];$i++){\\MSC\\Queue::run(1000);}switch_to_blog(3);$r=msc_get_record("' + uuid + '");\\MSC\\Plugin::store()->local($r["uuid"],["runtime"=>999],[],(int)$r["mapping"]["local_version"]);restore_current_blog();'.replace('\\\\', '\\'))
        await search('c', uuid)
        assert await page.get_by_role('button', name='Import selected global fields', exact=True).count() == 1
        assert await page.locator('input[name="fields[]"]:checked').count() == 0, 'Existing fields must start unchecked.'
        await page.locator('input[name="fields[]"][value=runtime]').check()
        await page.get_by_role('button', name='Import selected global fields', exact=True).click()
        await page.wait_for_url('**done=*', timeout=60000)
        check = wp('$r=msc_get_record("' + uuid + '");echo wp_json_encode(["title"=>$r["global"]["title"],"a"=>msc_get_field($r["uuid"],"runtime",2),"b"=>msc_get_field($r["uuid"],"runtime",3),"c"=>msc_get_field($r["uuid"],"runtime",4),"tmdb"=>$r["global"]["external_ids"]["tmdb"]]);')
        assert check == {'title': 'Curated Reloaded', 'a': 136, 'b': 999, 'c': 136, 'tmdb': 77}, check
        await page.screenshot(path=str(screenshots / 'omdb-import-complete.png'), full_page=True)
        # A competing global change while the preview is open yields HTTP 409.
        await search('a', uuid)
        wp('wp_set_current_user(1);$r=\\MSC\\Plugin::store()->get("' + uuid + '");\\MSC\\Plugin::store()->update($r["uuid"],["seo_title"=>"Browser concurrent change"],(int)$r["version"]);'.replace('\\\\', '\\'))
        await page.locator('input[name="fields[]"][value=runtime]').check()
        intentional_error_navigation = True
        async with page.expect_response(lambda response: '/admin-post.php' in response.url and response.request.method == 'POST') as pending:
            await page.get_by_role('button', name='Import selected global fields', exact=True).click()
        response = await pending.value
        assert response.status == 409, 'Stale imports must fail with a conflict.'
        await page.wait_for_load_state()
        assert 'Conflict:' in await page.locator('body').inner_text()
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-omdb&q=Matrix')
        assert 'search token expired' in (await page.locator('body').inner_text()).lower()
        assert not any('apikey=' in url for url in requests), 'The key must stay out of browser requests.'
        # Chromium can abort the core admin ViewTransition when a deliberate
        # HTTP 409/wp_die destination opts out of transitions. Application
        # errors, and transition errors before this deliberate navigation, fail.
        unexpected = [error for error in errors if not (error['intentional_error_navigation'] and error['message'] in ('Transition was aborted because of invalid state. ViewTransition opt-in disabled', 'Transition was skipped'))]
        assert not unexpected, unexpected
        await browser.close()
        print('PASS: OMDb title/year/type search, preview, selective create, duplicate POST, real poster upload/ACF preview, cross-site selective update, override preservation, HTTP 409, CSRF and key isolation.')

try:
    asyncio.run(main())
except Exception:
    report = traceback.format_exc().replace(config['WORDPRESS_ADMIN_PASSWORD'], '[redacted]')
    if config.get('OMDB_API_KEY'):
        report = report.replace(config['OMDB_API_KEY'], '[redacted]')
    raise SystemExit(report)
