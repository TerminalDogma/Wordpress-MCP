"""Real WordPress admin navigation, native filters and Show-scoped Episode creation."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import parse_qs, urlencode, urlparse
from playwright.async_api import async_playwright

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Requires a disposable local WordPress installation.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Local tests only.')

def wp(code):
    output = subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', '33', 'wordpress', 'wp', '--url=' + base + '/site-a/', 'eval', code], cwd=root, text=True)
    return json.loads(output) if output.strip() else None

async def main():
    data = wp('''wp_set_current_user(1);$tag=substr(wp_generate_uuid4(),0,8);$a=\\MSC\\Plugin::store()->create('msc_show',['title'=>'Admin Browser '.$tag.' A']);$b=\\MSC\\Plugin::store()->create('msc_show',['title'=>'Admin Browser '.$tag.' B']);$episodes=[];foreach(['two'=>[1,2],'ten'=>[1,10],'zero'=>[0,0],'unknown'=>[null,null],'other'=>[2,1]] as $key=>$numbers){$values=['title'=>'Pilot','parent_show'=>$key==='other'?$b['uuid']:$a['uuid']];if($numbers[0]!==null){$values['season_number']=$numbers[0];$values['episode_number']=$numbers[1];}$episodes[$key]=\\MSC\\Plugin::store()->create('msc_episode',$values);}for($i=0;$i<100&&\\MSC\\Queue::status()['pending'];$i++){$r=\\MSC\\Queue::run(1000);if($r['errors']){throw new RuntimeException('Fixture queue failed');}}$result=['tag'=>$tag,'show'=>$a,'other'=>$b,'episodes'=>$episodes,'posts'=>[]];foreach(get_sites(['number'=>0]) as $site){if(!in_array($site->path,['/site-a/','/site-b/'],true)){continue;}switch_to_blog((int)$site->blog_id);try{$result['posts'][$site->path]=['show'=>msc_local_post_id($a['uuid'])];foreach($episodes as $key=>$r){$result['posts'][$site->path][$key]=msc_local_post_id($r['uuid']);}}finally{restore_current_blog();}}echo wp_json_encode($result);''')
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'):
            options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1000})
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost', '127.0.0.1'):
                await route.continue_()
            else:
                await route.abort()
        await context.route('**/*', local_only)
        page = await context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        async def navigate(locator):
            async with page.expect_navigation(wait_until='load', timeout=60000):
                await locator.click()
        async def row_ids():
            return await page.locator('#the-list > tr[id^="post-"]').evaluate_all('(rows) => rows.map(row => Number(row.id.slice(5)))')
        await page.goto(base + '/wp-login.php')
        await page.fill('#user_login', config['WORDPRESS_ADMIN_USER'])
        await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD'])
        await page.locator('#wp-submit').click()
        await page.wait_for_url('**/wp-admin/**', timeout=60000, wait_until='domcontentloaded')
        prefix = base + '/site-a/wp-admin/'
        await page.goto(prefix + 'edit.php?' + urlencode({'post_type': 'msc_episode', 'msc_parent_show': data['show']['uuid']}))
        ids = data['posts']['/site-a/']
        assert await row_ids() == [ids['zero'], ids['two'], ids['ten'], ids['unknown']]
        row = page.locator('#post-' + str(ids['two']))
        assert await row.locator('.row-title').inner_text() == data['show']['data']['title'] + ' — S01E02 — Pilot'
        assert await row.locator('.column-msc_parent_show').inner_text() == data['show']['data']['title']
        assert await page.locator('#post-' + str(ids['zero']) + ' .column-msc_season_number').inner_text() == '0'
        await page.screenshot(path='/tmp/msc-episode-admin-native.png', full_page=True)
        await page.locator('#msc-season').select_option('1')
        await navigate(page.locator('#post-query-submit'))
        assert await row_ids() == [ids['two'], ids['ten']]
        sort = page.locator('thead .column-msc_episode_number a')
        await navigate(sort)
        if parse_qs(urlparse(page.url).query).get('order', [''])[0].lower() != 'desc':
            await navigate(page.locator('thead .column-msc_episode_number a'))
        assert await row_ids() == [ids['ten'], ids['two']]
        await page.locator('#msc-season').select_option('0')
        await navigate(page.locator('#post-query-submit'))
        assert await row_ids() == [ids['zero']]
        # Add New in this scoped list must carry its Show rather than create an orphan.
        await navigate(page.locator('a.page-title-action').first)
        assert await page.locator('#msc-parent_show').input_value() == data['show']['uuid']
        assert data['show']['data']['title'] in await page.locator('.msc-episode-context').inner_text()
        await page.locator('#msc-title').fill('New Episode ' + data['tag'])
        await page.locator('#msc-season_number').fill('2')
        await page.locator('#msc-episode_number').fill('1')
        await navigate(page.get_by_role('button', name='Save global record', exact=True))
        record = parse_qs(urlparse(page.url).query)['record'][0]
        assert data['show']['data']['title'] in await page.locator('.msc-episode-context').inner_text()
        assert 'S02E01' in await page.locator('.msc-episode-context').inner_text()
        saved = wp('$r=\\MSC\\Plugin::store()->get("' + record + '");echo wp_json_encode($r["data"]);')
        assert saved['title'] == 'New Episode ' + data['tag'] and saved['parent_show'] == data['show']['uuid']
        # Show row actions lead directly to their Episodes, including unpublished drafts.
        await page.goto(prefix + 'edit.php?' + urlencode({'post_type': 'msc_show', 's': data['tag']}))
        show_row = page.locator('#post-' + str(ids['show']))
        await show_row.hover()
        await navigate(show_row.locator('.row-actions .msc_episodes a'))
        assert await page.locator('#msc-parent-show').input_value() == data['show']['uuid']
        assert len(await row_ids()) == 5
        # The ACF Show editor also exposes its global Episode catalog.
        await page.goto(prefix + 'admin.php?' + urlencode({'page': 'msc-acf', 'record': data['show']['uuid'], 'scope': 'global'}))
        await navigate(page.locator('.msc-episode-navigation').get_by_role('link', name='View Episodes', exact=True))
        assert await page.locator('.msc-episode-catalog tbody tr').count() == 5
        assert 'S01E02 — Pilot' in await page.locator('.msc-episode-catalog').inner_text()
        assert await page.locator('#msc-parent-show').input_value() == data['show']['uuid']
        await page.screenshot(path='/tmp/msc-episode-admin-global.png', full_page=True)
        wp('wp_set_current_user(1);switch_to_blog(3);try{$r=\\MSC\\Plugin::store()->resolved("' + data['episodes']['two']['uuid'] + '");\\MSC\\Plugin::store()->local($r["uuid"],["parent_show"=>"' + data['other']['uuid'] + '","season_number"=>3],[],(int)$r["mapping"]["local_version"]);}finally{restore_current_blog();}')
        await page.goto(base + '/site-b/wp-admin/edit.php?' + urlencode({'post_type': 'msc_episode', 'msc_parent_show': data['show']['uuid']}))
        assert data['posts']['/site-b/']['two'] not in await row_ids()
        await page.goto(base + '/site-b/wp-admin/admin.php?' + urlencode({'page': 'msc', 'type': 'msc_episode', 'msc_parent_show': data['show']['uuid']}))
        assert 'S01E02 — Pilot' in await page.locator('.msc-episode-catalog').inner_text(), 'Global parentage must remain distinct from a local move.'
        assert not errors, errors
        await browser.close()
    print('PASS: Native and Shared Content Episode lists, Show/season filters, numeric sorting, scoped creation, Show row actions, ACF navigation, draft visibility and local/global parent overrides.')

try:
    asyncio.run(main())
except Exception:
    report = traceback.format_exc()
    for name in ['WORDPRESS_ADMIN_PASSWORD', 'OMDB_API_KEY']:
        if config.get(name):
            report = report.replace(config[name], '[redacted]')
    raise SystemExit(report)
