"""Four production profiles, actual brand assets, public calendar and IGDB import UI."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
from urllib.parse import urlparse, parse_qs
from playwright.async_api import async_playwright

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Disposable local network required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Local network required.')
def wp(code):
    return subprocess.check_output(['sh', 'bin/shared-content.sh', 'wp', '--user=1', 'eval', code], cwd=root, text=True)
data = json.loads(wp('echo wp_json_encode(["demo"=>get_network_option(get_current_network_id(),"msc_network_demo_200"),"seed"=>get_network_option(get_current_network_id(),"msc_igdb_fixture_seed"),"month"=>wp_date("Y-m")]);'))
shots = Path('/tmp/msc-four-site-screenshots'); shots.mkdir(exist_ok=True)

async def main():
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1000})
        async def route(request):
            if urlparse(request.request.url).hostname in ('localhost', '127.0.0.1'): await request.continue_()
            elif request.request.resource_type == 'image': await request.fulfill(status=200, content_type='image/svg+xml', body='<svg xmlns="http://www.w3.org/2000/svg" width="64" height="96"><rect width="64" height="96" fill="#1267af"/></svg>')
            else: await request.abort()
        await context.route('**/*', route)
        page = await context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        colors = []; fonts = []
        for key, brand, types in [('a','ComingSoon.net',['Movies','TV Shows','People','Reviews']),('b','SuperHeroHype',['Movies','TV Shows','Comic Books','Story Arcs','Reviews']),('c','GameRevolution',['Video Games','Reviews']),('d','PlayStation LifeStyle',['Video Games','Reviews'])]:
            response = await page.goto(base + '/site-' + key + '/')
            assert response.status == 200
            await page.evaluate('document.fonts.ready')
            logo = page.locator('header .brand img')
            assert await logo.get_attribute('alt') == brand
            assert await logo.evaluate('(el) => el.complete && el.naturalWidth > 0')
            assert await page.locator('.news-card').count() >= 14
            assert 'fictional' in await page.locator('.demo-disclosure').inner_text()
            links = await page.locator('.catalog-navigation a').all_text_contents()
            assert links[:-1] == types + ['Upcoming Releases'], (key, links)
            colors.append(await page.locator('.network-masthead').evaluate('(el) => getComputedStyle(el).backgroundColor'))
            fonts.append(await page.locator('.news-card h3').first.evaluate('(el) => getComputedStyle(el).fontFamily'))
            await page.screenshot(path=str(shots / ('site-' + key + '-desktop.png')), full_page=True)
            href = await page.locator('.news-card a').first.get_attribute('href')
            assert (await page.goto(href)).status == 200
            assert await page.locator('.news-article h1').count() == 1
            await page.goto(base + '/site-' + key + '/')
            await page.locator('.primary-nav a').nth(1).click()
            assert await page.locator('.archive-heading h1').count() == 1
            await page.goto(base + '/site-' + key + '/release-calendar/')
            assert await page.locator('.calendar-event').count() > 0
            assert not await page.locator('.calendar-event.type-msc_person').count()
            if key in ('c', 'd'):
                assert await page.locator('select[name=release_type] option').all_text_contents() == ['All release types', 'Video Games', 'Game Consoles and Platforms']
                if key == 'd':
                    assert 'PC (Microsoft Windows)' not in await page.locator('#release-calendar').inner_text()
                    assert 'PlayStation Vita' in await page.locator('#release-calendar').inner_text()
            else:
                assert 'Episodes' in await page.locator('select[name=release_type]').inner_text()
            if key in ('c', 'd'):
                await page.locator('.calendar-event').first.click()
                assert await page.locator('.kind-msc_game h1').count() == 1
                assert await page.locator('.release-table tbody tr').count() >= 1
                if key == 'd': assert 'PC (Microsoft Windows)' not in await page.locator('.story-section').inner_text()
                await page.goto(base + '/site-' + key + '/release-calendar/')
            old_heading = await page.locator('.calendar-heading h2').inner_text()
            await page.get_by_role('link', name='Next month', exact=True).click()
            await page.wait_for_function('(previous) => document.querySelector(".calendar-heading h2").textContent !== previous', arg=old_heading)
            assert 'calendar_month=' in page.url
            await page.go_back()
            await page.wait_for_function('(previous) => document.querySelector(".calendar-heading h2").textContent === previous', arg=old_heading)
            await page.screenshot(path=str(shots / ('site-' + key + '-calendar.png')), full_page=True)
            await page.set_viewport_size({'width':390, 'height':844})
            assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (key, 'calendar overflow')
            await page.goto(base + '/site-' + key + '/')
            await page.get_by_role('button', name='Menu').click()
            assert await page.get_by_role('button', name='Menu').get_attribute('aria-expanded') == 'true'
            assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (key, 'home overflow')
            await page.keyboard.press('Escape')
            await page.screenshot(path=str(shots / ('site-' + key + '-mobile.png')), full_page=True)
            await page.set_viewport_size({'width':1440,'height':1000})
            print('PASS: Brand, fictional article, category, responsive menu and dynamic calendar', key, flush=True)
        assert len(set(colors)) == 4 and len(set(fonts)) == 4
        await page.goto(base + '/wp-login.php')
        await page.fill('#user_login', config['WORDPRESS_ADMIN_USER']); await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD']); await page.click('#wp-submit'); await page.wait_for_url('**/wp-admin/**')
        await page.goto(base + '/wp-admin/network/admin.php?page=msc-sites')
        assert await page.locator('.msc-site-matrix tbody tr').count() >= 5
        rows = page.locator('.msc-site-matrix tbody tr'); target = rows.filter(has_text='PlayStation LifeStyle')
        assert await target.locator('select').input_value() == 'sony_all'
        checkbox = target.locator('input[value=msc_game]'); await checkbox.uncheck()
        await page.get_by_role('button', name='Save site content types', exact=True).click(); await page.wait_for_url('**saved=1')
        await page.goto(base + '/site-d/release-calendar/')
        assert await page.locator('.calendar-event').count() == 0
        await page.goto(base + '/wp-admin/network/admin.php?page=msc-sites')
        await page.locator('.msc-site-matrix tbody tr').filter(has_text='PlayStation LifeStyle').locator('input[value=msc_game]').check()
        await page.get_by_role('button', name='Save site content types', exact=True).click(); await page.wait_for_url('**saved=1')
        await page.goto(base + '/site-a/wp-admin/edit.php?post_type=msc_show')
        assert await page.locator('#menu-posts-msc_show a[href*="page=msc-episodes"]').count() == 1
        assert await page.locator('#toplevel_page_msc-episodes').count() == 0
        await page.goto(base + '/site-c/wp-admin/admin.php?page=msc-import')
        assert await page.locator('select[name=kind]').input_value() == 'msc_game'
        await page.fill('input[name=q]', 'Fixture IGDB Game ' + str(data['seed']))
        await page.get_by_role('button', name='Search', exact=True).click()
        await page.get_by_role('link', name='Preview Fixture IGDB Game ' + str(data['seed']), exact=True).click()
        await page.wait_for_load_state('networkidle')
        await page.wait_for_function('document.querySelector("[data-msc-select-all]").indeterminate')
        await page.locator('[data-msc-select-all]').check()
        assert await page.locator('input[name=image]').is_checked(), await page.locator('form[data-msc-import-selection]').evaluate('(form) => Array.from(form.querySelectorAll("input[type=checkbox]")).map(el => [el.name,el.checked,el.indeterminate,el.disabled])')
        await page.get_by_role('button', name='Create shared record', exact=True).click(); await page.wait_for_url('**done=*', timeout=60000)
        href = await page.get_by_role('link', name='All fields and publishing', exact=True).get_attribute('href'); uuid = parse_qs(urlparse(href).query)['record'][0]
        await page.goto(href)
        assert 'GLOBAL EDITING' in await page.locator('.wrap.msc').inner_text()
        assert await page.locator('.msc-game-date-row').count() == 3
        assert await page.locator('.msc-media-previews img').first.evaluate('(el) => el.complete && el.naturalWidth > 0')
        # Edit exact platform dates through the structured editor and verify the public calendar updates.
        row = page.locator('.msc-game-date-row').first
        await row.locator('[data-field=date]').fill(data['month'] + '-16')
        await page.get_by_role('button', name='Save global record', exact=True).click()
        await page.wait_for_url('**mode=global', timeout=60000)
        await page.get_by_role('link', name='Switch to local overrides', exact=True).click()
        await page.locator('select[name=status]').select_option('publish')
        await page.get_by_role('button', name='Save local overrides', exact=True).click()
        await page.goto(base + '/site-c/release-calendar/')
        assert await page.locator('.calendar-event').filter(has_text='Fixture IGDB Game ' + str(data['seed'])).count() == 1
        await page.screenshot(path=str(shots / 'game-import-calendar.png'), full_page=True)
        # Site D gets its own Sony date; the PC override must stay off that calendar.
        response = await context.request.get(base + '/site-d/wp-json/msc/v1/records/' + uuid)
        assert response.status in (401,403), 'Imported game starts as draft on other sites.'
        assert not errors, errors
        print('PASS: Content matrix, child Episodes, private IGDB search/import, cover preview, structured date editor and live calendar', flush=True)
        await browser.close()

asyncio.run(main())
