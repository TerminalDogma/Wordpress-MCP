"""Actual duplicate review, global defaults/delivery and public season navigation."""
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
    raise SystemExit('Disposable local installation required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Local tests only.')

def wp(code):
    output = subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', '33', 'wordpress', 'wp', '--url=' + base + '/site-a/', 'eval', code], cwd=root, text=True)
    return json.loads(output) if output.strip() else None

async def main():
    data = wp('wp_set_current_user(1);$seed=random_int(500000000,899999000);update_network_option(get_current_network_id(),"msc_open_fixture_seed",$seed);foreach(["tvmaze","wikidata","wikipedia","commons"] as $p){delete_site_transient("msc_open_rate_".$p);delete_site_transient("msc_open_backoff_".$p);}$p=\\MSC\\Plugin::store()->create("msc_person",["title"=>"Alex Sharedname","content"=>"<p>Existing writer biography.</p>","professions"=>["Writer"]]);echo wp_json_encode(["seed"=>$seed,"person"=>$p["uuid"],"count"=>count(\\MSC\\PersonIdentity::catalogue())]);')
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'):
            options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
        async def route(request):
            if urlparse(request.request.url).hostname in ('localhost', '127.0.0.1'):
                await request.continue_()
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
        try:
            await page.wait_for_url('**/wp-admin/**', timeout=60000, wait_until='domcontentloaded')
        except Exception as error:
            message = await page.locator('#login_error').inner_text() if await page.locator('#login_error').count() else 'No login error message.'
            raise RuntimeError('WordPress login did not reach admin: ' + page.url + ' ' + message) from error
        async def click(locator):
            async with page.expect_navigation(wait_until='load', timeout=60000) as navigation:
                await locator.click()
            return await navigation.value
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-import')
        await page.locator('select[name=kind]').select_option('msc_show')
        await page.locator('input[name=q]').fill('Open Show ' + str(data['seed']))
        await click(page.get_by_role('button', name='Search', exact=True))
        await click(page.get_by_role('link', name='Preview Open Show ' + str(data['seed']), exact=True))
        assert 'Possible duplicate found' in await page.locator('.msc-person-choices').inner_text()
        response = await click(page.get_by_role('button', name='Create shared record', exact=True))
        assert response.status == 409 and 'may already exist' in await page.locator('body').inner_text()
        await page.go_back(wait_until='domcontentloaded')
        await page.locator('.msc-person-choices select').select_option(data['person'])
        await page.get_by_role('checkbox', name='Select all import data', exact=True).check()
        await click(page.get_by_role('button', name='Create shared record', exact=True))
        await page.wait_for_url('**done=*')
        href = await page.get_by_role('link', name='All fields and publishing', exact=True).get_attribute('href')
        show = parse_qs(urlparse(href).query)['record'][0]
        actual = wp('$r=\\MSC\\Plugin::store()->get("' + show + '");$p=\\MSC\\Plugin::store()->get("' + data['person'] + '");echo wp_json_encode(["actors"=>$r["data"]["actors"],"directors"=>$r["data"]["directors"],"professions"=>$p["data"]["professions"],"count"=>count(\\MSC\\PersonIdentity::catalogue())]);')
        assert actual['actors'] == actual['directors'] == [data['person']]
        assert {'Writer', 'Director', 'Actor'} <= set(actual['professions']) and actual['count'] == data['count']
        await click(page.get_by_role('link', name='All fields and publishing', exact=True))
        assert await page.locator('input[name=msc_action]').first.input_value() == 'global'
        assert await page.locator('input[name="override[premiere_date]"]').count() == 0
        await page.locator('#msc-premiere_date').fill('2031-04-09')
        await click(page.get_by_role('button', name='Save global record', exact=True))
        states = wp('$r=\\MSC\\Plugin::store()->get("' + show + '");echo wp_json_encode(array_map(fn($site)=>["version"=>\\MSC\\Plugin::store()->mapping((int)$r["id"],$site)["version"],"date"=>\\MSC\\Plugin::store()->resolved($r["uuid"],$site)["data"]["premiere_date"]],[2,3,4]));')
        assert all(state['date'] == '2031-04-09' for state in states)
        assert await page.locator('.msc-propagation tr[data-site="4"]').get_by_text('Up to date', exact=True).count() == 1
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + show)
        assert await page.locator('input[name=scope]').input_value() == 'global'
        await page.locator('.acf-field[data-name=msc_premiere_date] input[type=hidden]').evaluate('(input) => input.value = "20320409"')
        await click(page.get_by_role('button', name='Save shared fields globally', exact=True))
        dates = wp('echo wp_json_encode(array_map(fn($site)=>\\MSC\\Plugin::store()->resolved("' + show + '",$site)["data"]["premiere_date"],[2,3,4]));')
        assert dates == ['2032-04-09'] * 3, dates
        await click(page.get_by_role('link', name='Switch to local overrides', exact=True))
        assert await page.locator('input[name=scope]').input_value() == 'local'
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-person-duplicates')
        assert 'Alex Sharedname' in await page.locator('.msc-person-duplicate').first.inner_text()
        url = wp('wp_set_current_user(1);$r=\\MSC\\Plugin::store()->resolved("' + show + '");\\MSC\\Plugin::store()->local($r["uuid"],[],[],(int)$r["mapping"]["local_version"],"publish");foreach([0,1,7,9] as $s){$e=\\MSC\\Plugin::store()->create("msc_episode",["title"=>"Season ".$s." Episode","parent_show"=>$r["uuid"],"season_number"=>$s,"episode_number"=>1]);\\MSC\\Plugin::materializer()->sync((int)$e["id"],get_current_blog_id());if($s!==9){$m=\\MSC\\Plugin::store()->mapping((int)$e["id"]);\\MSC\\Plugin::store()->local($e["uuid"],[],[],(int)$m["local_version"],"publish");}}echo wp_json_encode(get_permalink(msc_local_post_id($r["uuid"])));')
        await page.goto(url)
        assert await page.locator('.season-pagination a').count() == 3
        await page.evaluate('window.mscSeasonDocument = true')
        await page.get_by_role('link', name='Season 7', exact=True).click()
        await page.wait_for_function('document.querySelector(".season-heading").textContent === "Season 7"')
        assert await page.evaluate('window.mscSeasonDocument === true'), 'Season selection must preserve the document'
        assert await page.locator('.season-pagination [aria-current=page]').inner_text() == 'Season 7'
        assert await page.locator('.episode-list strong').all_inner_texts() == ['Season 7 Episode']
        assert await page.locator('.episode-list img,.episode-list p').count() == 0
        assert await page.locator('main .msc-attribution,main .msc-image-credit').count() == 0
        assert await page.locator('footer .msc-footer-credits').count() == 1
        assert 'tvmaze.com' in await page.locator('footer .msc-footer-credits').inner_html()
        await page.screenshot(path='/tmp/msc-review-season-seven.png', full_page=True)
        await page.set_viewport_size({'width': 390, 'height': 844})
        assert await page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
        await page.get_by_role('link', name='Season 1', exact=True).click()
        await page.wait_for_function('document.querySelector(".season-heading").textContent === "Season 1"')
        assert await page.evaluate('window.mscSeasonDocument === true')
        assert await page.locator('.episode-list strong').all_inner_texts() == ['Season 1 Episode']
        assert not errors, errors
        await browser.close()
    print('PASS: Duplicate draft review and cross-role reuse, Select all, global defaults and immediate three-site delivery from both editors, public season links and footer-only credits.')

try:
    asyncio.run(main())
except Exception:
    report = traceback.format_exc()
    for key in ['WORDPRESS_ADMIN_PASSWORD', 'OMDB_API_KEY']:
        if config.get(key):
            report = report.replace(config[key], '[redacted]')
    raise SystemExit(report)
