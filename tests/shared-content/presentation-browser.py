"""Real-browser templates, taxonomy selectors, Person links and cross-site image UI."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse
from playwright.async_api import async_playwright

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Use a disposable local demo with MSC_DISPOSABLE_TESTS=1.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Browser tests are local only.')
code = '''$result=["records"=>get_network_option(get_current_network_id(),"msc_demo_records"),"sites"=>[]];foreach(get_sites(["number"=>0]) as $site){if(!in_array($site->path,["/site-a/","/site-b/","/site-c/"],true)){continue;}switch_to_blog((int)$site->blog_id);try{$urls=[];foreach($result["records"] as $key=>$uuid){$urls[$key]=get_permalink(msc_local_post_id($uuid));}$result["sites"][$site->path]=$urls;}finally{restore_current_blog();}}echo wp_json_encode($result);'''
data = json.loads(subprocess.check_output(['sh', 'bin/shared-content.sh', 'wp', '--url=' + base + '/site-a/', 'eval', code], cwd=root, text=True))
records = data['records']
screenshots = Path(os.environ.get('MSC_SCREENSHOTS', '/tmp/msc-presentation-screenshots'))
screenshots.mkdir(exist_ok=True, parents=True)

async def main():
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'):
            options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1050})
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost', '127.0.0.1'):
                await route.continue_()
            else:
                await route.abort()
        await context.route('**/*', local_only)
        page = await context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        backgrounds = []
        for letter, brand in [('a', 'ComingSoon.net'), ('b', 'SuperHeroHype'), ('c', 'GameRevolution')]:
            prefix = '/site-' + letter + '/'
            response = await page.goto(base + prefix)
            assert response.status == 200, ('homepage', letter, response.status)
            assert brand == await page.locator('header .brand img').get_attribute('alt')
            assert await page.locator('body').get_attribute('data-identity') == letter, "Browser assertion at line 45: await page.locator('body').get_attribute('data-identity') == letter"
            backgrounds.append(await page.locator('.network-masthead').evaluate('(el) => getComputedStyle(el).backgroundColor'))
            await page.locator('img').evaluate_all("imgs => Promise.all(imgs.map(img => { img.loading = 'eager'; return img.decode().catch(() => {}); }))")
            await page.screenshot(path=str(screenshots / ('site-' + letter + '-home.png')), full_page=True)
            for kind in ['movie', 'show', 'episode', 'actor']:
                response = await page.goto(data['sites'][prefix][kind])
                assert response.status == 200, (prefix, kind, response.status)
                assert 'noindex' in response.headers.get('x-robots-tag', ''), "Browser assertion at line 52: 'noindex' in response.headers.get('x-robots-tag', '')"
                assert await page.locator('.detail-hero h1').count() == 1, "Browser assertion at line 53: await page.locator('.detail-hero h1').count() == 1"
                assert await page.locator('.detail-art').evaluate('(el) => el.tagName === "IMG" && el.complete && el.naturalWidth > 0'), (letter, kind, 'missing art')
                if kind == 'movie':
                    assert await page.locator('.credit-section .person-card').count() >= 2, "Browser assertion at line 56: await page.locator('.credit-section .person-card').count() >= 2"
                    await page.locator('img').evaluate_all("imgs => Promise.all(imgs.map(img => { img.loading = 'eager'; return img.decode().catch(() => {}); }))")
                    await page.screenshot(path=str(screenshots / ('site-' + letter + '-movie.png')), full_page=True)
                if kind == 'show':
                    assert await page.locator('.episode-list a').count() >= 1, "Browser assertion at line 60: await page.locator('.episode-list a').count() >= 1"
                if kind == 'episode':
                    assert await page.locator('.parent-show').count() == 1, "Browser assertion at line 62: await page.locator('.parent-show').count() == 1"
                if kind == 'actor':
                    assert await page.locator('.filmography .catalog-card').count() >= 3, "Browser assertion at line 64: await page.locator('.filmography .catalog-card').count() >= 3"
                await page.set_viewport_size({'width': 390, 'height': 844})
                assert await page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), (letter, kind, 'mobile overflow')
                await page.set_viewport_size({'width': 1440, 'height': 1050})
                print('PASS: Template', letter, kind, flush=True)
        assert len(set(backgrounds)) == 3, 'Site palettes must be distinct.'
        # Category chips lead to a working native taxonomy archive.
        await page.goto(data['sites']['/site-a/']['movie'])
        await page.locator('.chips a').first.click()
        assert await page.locator('.catalog-card').count() >= 1, "Browser assertion at line 72: await page.locator('.catalog-card').count() >= 1"
        assert await page.locator('.archive-heading h1').count() == 1, "Browser assertion at line 73: await page.locator('.archive-heading h1').count() == 1"
        # Admin controls show inherited images instead of JSON, including inside ACF.
        await page.goto(base + '/wp-login.php')
        await page.fill('#user_login', config['WORDPRESS_ADMIN_USER'])
        await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD'])
        await page.click('#wp-submit')
        await page.wait_for_url('**/wp-admin/**')
        url = base + '/site-b/wp-admin/admin.php?page=msc-acf&record=' + records['movie'] + '&scope=local'
        await page.goto(url)
        assert await page.locator('#wp-admin-bar-msc-identity').inner_text() == 'Site B · SuperHeroHype', "Browser assertion at line 82: await page.locator('#wp-admin-bar-msc-identity').inner_text() == 'Site B'"
        poster = page.locator('.acf-field[data-name="msc_poster"]')
        assert await poster.locator('.msc-media-control .msc-media-previews img').first.evaluate('(el) => el.complete && el.naturalWidth > 0'), "Browser assertion at line 84: await poster.locator('.msc-media-control .msc-media-previews img').first.evaluate('(el) => el.complete && el.naturalWidth > 0')"
        value = json.loads(await poster.locator('.msc-media-value').input_value())
        assert value['site_id'] != 3, 'Expected inherited image from Site A.'
        assert await poster.locator('textarea').count() == 0, "Browser assertion at line 87: await poster.locator('textarea').count() == 0"
        assert await page.locator('.acf-field[data-name="msc_genres"] select[multiple]').count() == 1, 'Browser assertion at line 88: await page.locator(\'.acf-field[data-name="msc_genres"] select[multiple]\').count() == 1'
        assert await page.locator('.acf-field[data-name="msc_actors"] select[multiple]').count() == 1, 'Browser assertion at line 89: await page.locator(\'.acf-field[data-name="msc_actors"] select[multiple]\').count() == 1'
        # Remove -> deliberate empty local image -> reset to owner-aware global image.
        await poster.locator('.msc-remove-media').click()
        assert await poster.locator('input[name="override[poster]"]').is_checked(), 'Browser assertion at line 92: await poster.locator(\'input[name="override[poster]"]\').is_checked()'
        assert json.loads(await poster.locator('.msc-media-value').input_value()) == [], "Browser assertion at line 93: json.loads(await poster.locator('.msc-media-value').input_value()) == []"
        await page.get_by_role('button', name='Save local overrides', exact=True).click()
        await page.wait_for_url('**updated=1', timeout=60000)
        poster = page.locator('.acf-field[data-name="msc_poster"]')
        assert json.loads(await poster.locator('.msc-media-value').input_value()) == [], "Browser assertion at line 97: json.loads(await poster.locator('.msc-media-value').input_value()) == []"
        await poster.locator('input[name="override[poster]"]').uncheck()
        await page.get_by_role('button', name='Save local overrides', exact=True).click()
        await page.wait_for_url('**updated=1', timeout=60000)
        poster = page.locator('.acf-field[data-name="msc_poster"]')
        assert json.loads(await poster.locator('.msc-media-value').input_value()) == value, "Browser assertion at line 102: json.loads(await poster.locator('.msc-media-value').input_value()) == value"
        # Use the real media library from Site A and confirm live preview replacement.
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + records['movie'] + '&scope=local')
        poster = page.locator('.acf-field[data-name="msc_poster"]')
        await poster.locator('.msc-choose-media').click()
        modal = page.locator('.media-modal')
        await modal.get_by_role('tab', name='Media Library', exact=True).click()
        image_choice = modal.locator('.attachment:has(.attachment-preview.type-image)').first
        await image_choice.wait_for()
        selected_id = await image_choice.get_attribute('data-id')
        await image_choice.click()
        await modal.get_by_role('button', name='Use this media', exact=True).click()
        await page.wait_for_function("([selector, id]) => JSON.parse(document.querySelector(selector).value).attachment_id === Number(id)", arg=['.acf-field[data-name=msc_poster] .msc-media-value', selected_id])
        assert await poster.locator('.msc-media-control .msc-media-previews img').count() == 1, 'Choosing an image must render its preview card'
        assert await poster.locator('.msc-media-control .msc-media-card').get_attribute('data-attachment') == selected_id, 'Preview must match the chosen image'
        assert await poster.locator('input[name="override[poster]"]').is_checked(), 'Browser assertion at line 113: await poster.locator(\'input[name="override[poster]"]\').is_checked()'
        # Do not save the replacement; it demonstrates the picker without mutating demo artwork.
        await page.goto(base + '/site-b/wp-admin/admin.php?page=msc&record=' + records['movie'])
        assert await page.locator('#msc-poster').get_attribute('type') == 'hidden', "Browser assertion at line 116: await page.locator('#msc-poster').get_attribute('type') == 'hidden'"
        assert await page.locator('#msc-poster').locator('..').locator('.msc-media-previews img').count() == 1, "Browser assertion at line 117: await page.locator('#msc-poster').locator('..').locator('.msc-media-previews img').count() == 1"
        await page.screenshot(path=str(screenshots / 'editor-image-preview.png'), full_page=True)
        assert not errors, errors
        await browser.close()
        print('PASS: Three distinct site designs; 12 desktop/mobile detail pages; taxonomy archives; linked filmographies; ACF/full-editor images; media picker; empty local image override and reset; no JavaScript errors.')

try:
    asyncio.run(main())
except Exception as error:
    raise SystemExit(traceback.format_exc().replace(config['WORDPRESS_ADMIN_PASSWORD'], '[redacted]'))
