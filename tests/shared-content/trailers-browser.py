"""Trailer carousel, sidebar layout, and embedded YouTube selection in both editors."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
from urllib.parse import parse_qs, urlparse
from playwright.async_api import async_playwright, expect

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Disposable local installation required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Local installation required.')
log = subprocess.check_output(['sh', 'bin/shared-content.sh', 'wp', '--url='+base+'/site-a/', 'eval-file', '/msc-tests/trailers.php'], cwd=root, text=True)
for line in log.splitlines():
    if line.startswith(('PASS:', 'Success:')): print(line, flush=True)
data = json.loads(next(line.split(':', 1)[1] for line in log.splitlines() if line.startswith('MSC_TRAILER_FIXTURE:')))

async def main():
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='reduce')
        fail_search = True
        async def routing(route):
            nonlocal fail_search
            if urlparse(route.request.url).hostname not in ('localhost', '127.0.0.1'):
                await route.abort(); return
            body = parse_qs(route.request.post_data or '')
            if body.get('action') == ['msc_search_trailers']:
                assert body.get('nonce')
                if fail_search:
                    fail_search = False
                    await route.fulfill(status=502, json={'success': False, 'data': {'message': 'Fixture search unavailable. Retry.'}}); return
                items = [{'url': 'https://www.youtube.com/watch?v='+vid, 'embed': 'https://www.youtube-nocookie.com/embed/'+vid+'?rel=0', 'thumbnail': 'https://i.ytimg.com/vi/'+vid+'/mqdefault.jpg', 'title': title, 'channel': 'Fixture Studio', 'duration': '2:30'} for vid, title in [('abcdefghijk', 'Existing trailer'), ('nativeabcde' if 'full' in body.get('query', [''])[0] else 'zyxwvutsrqp', 'Official trailer <safe text>')]]
                await route.fulfill(json={'success': True, 'data': {'items': items, 'next_page': None}}); return
            await route.continue_()
        await context.route('**/*', routing)
        page = await context.new_page()
        for kind in ('msc_movie', 'msc_show', 'msc_game'):
            assert (await page.goto(data[kind]['url'])).status == 200
            carousel = page.locator('[data-trailer-carousel]')
            await expect(carousel.locator('.trailer-slide:visible')).to_have_count(3)
            await expect(carousel.locator('.trailer-controls')).to_be_hidden()
            desktop = await carousel.locator('.trailer-slide:visible').evaluate_all('(slides) => slides.map(slide => ({x:slide.offsetLeft,y:slide.offsetTop,width:slide.offsetWidth}))')
            assert len({slide['y'] for slide in desktop}) == 1 and max(slide['width'] for slide in desktop) <= 400
            await page.set_viewport_size({'width': 900, 'height': 1000})
            await expect(carousel.locator('.trailer-slide:visible')).to_have_count(2)
            before = await carousel.locator('.trailer-stage').bounding_box()
            await expect(carousel.locator('[data-trailer-prev]')).to_be_disabled()
            await page.evaluate('window.trailerDocument = true')
            await carousel.locator('[data-trailer-next]').click()
            await expect(carousel.locator('[data-trailer-status]')).to_have_text('Trailer 3 of 3')
            await expect(carousel.locator('iframe[src]')).to_have_count(1)
            await expect(carousel.locator('[data-trailer-next]')).to_be_disabled()
            after = await carousel.locator('.trailer-stage').bounding_box()
            assert abs(before['height'] - after['height']) < 1
            assert await page.evaluate('window.trailerDocument === true')
            await carousel.locator('[data-trailer-prev]').press('ArrowLeft')
            await expect(carousel.locator('[data-trailer-status]')).to_have_text('Trailers 1–2 of 3')
            await page.set_viewport_size({'width': 1440, 'height': 1000})
        print('PASS: All three title templates fit three compact trailers across desktop and two on tablet; paging unloads hidden players and preserves row height.', flush=True)

        await page.goto(data['msc_movie']['url'])
        panel = page.locator('.details-panel')
        text = await panel.inner_text()
        assert data['msc_movie']['names'][4] in text and data['msc_movie']['names'][5] not in text
        lists = panel.locator('dl.facts')
        first, second = await lists.nth(0).bounding_box(), await lists.nth(1).bounding_box()
        assert second['y'] - first['y'] - first['height'] >= 23
        await expect(page.locator('#cast')).to_contain_text(data['msc_movie']['names'][6])
        await panel.screenshot(path='/tmp/msc-sidebar.png')
        await page.set_viewport_size({'width': 390, 'height': 844})
        await expect(page.locator('.trailer-slide:visible')).to_have_count(1)
        assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        await page.screenshot(path='/tmp/msc-trailers-mobile.png', full_page=True)
        print('PASS: Sidebar facts have a clear gap; only five actors appear, with all seven retained below. Mobile layout has no horizontal overflow.', flush=True)

        await page.set_viewport_size({'width': 1440, 'height': 1000})
        await page.goto(base+'/wp-login.php')
        await page.fill('#user_login', config['WORDPRESS_ADMIN_USER'])
        await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD'])
        await page.click('#wp-submit'); await page.wait_for_url('**/wp-admin/**')
        unauthorized = await context.request.post(base+'/site-a/wp-admin/admin-ajax.php', form={'action': 'msc_search_trailers', 'query': 'fixture'})
        assert unauthorized.status == 403
        for editor in ('acf', 'full'):
            await page.goto(data['msc_movie'][editor])
            field = page.locator('textarea[data-msc-trailers], .acf-field[data-name="msc_trailers"] textarea')
            await expect(field).to_be_visible()
            await page.evaluate('mscTrailers.configured = true')
            picker = page.locator('.msc-trailer-picker')
            await picker.locator('input[type="search"]').fill('Trailer fixture '+editor)
            await picker.get_by_role('button', name='Search YouTube', exact=True).click()
            if editor == 'acf':
                await expect(picker.locator('[role="status"]')).to_contain_text('unavailable')
                await picker.get_by_role('button', name='Search YouTube', exact=True).click()
            await expect(picker.locator('.msc-trailer-result')).to_have_count(2)
            await expect(picker.locator('.msc-trailer-result').nth(0).get_by_role('button', name='Added', exact=True)).to_be_disabled()
            card = picker.locator('.msc-trailer-result').nth(1)
            await card.get_by_role('button', name='Preview', exact=True).click()
            await expect(picker.locator('.msc-trailer-preview iframe')).to_have_count(1)
            await picker.get_by_role('button', name='Close preview').click()
            await expect(picker.locator('.msc-trailer-preview iframe')).to_have_count(0)
            await card.get_by_role('button', name='Add trailer', exact=True).click()
            assert (await field.input_value()).splitlines()[-1] == 'https://www.youtube.com/watch?v='+('nativeabcde' if editor == 'full' else 'zyxwvutsrqp')
            await expect(card.get_by_role('button', name='Added', exact=True)).to_be_disabled()
            assert await picker.locator('.msc-trailer-result h4 script').count() == 0
            if editor == 'acf':
                async with page.expect_navigation(wait_until='domcontentloaded'):
                    await page.get_by_role('button', name='Save shared fields globally', exact=True).click()
                await expect(field).to_have_value('https://www.youtube.com/watch?v=abcdefghijk\nhttps://www.youtube.com/watch?v=lmnopqrstuv\nhttps://vimeo.com/123456789\nhttps://www.youtube.com/watch?v=zyxwvutsrqp')
                await page.goto(data['msc_movie']['url'])
                await expect(page.locator('.trailer-slide')).to_have_count(4)
                await expect(page.locator('.trailer-slide:visible')).to_have_count(3)
                await page.locator('[data-trailer-next]').click()
                await expect(page.locator('[data-trailer-status]')).to_have_text('Trailer 4 of 4')
                await expect(page.locator('iframe[src]')).to_have_count(1)
        print('PASS: YouTube picker thumbnails, previews, duplicate prevention and retries work in ACF and the full shared editor; ACF global saves persist selected trailers. Missing AJAX nonces are rejected.', flush=True)
        nojs = await browser.new_context(java_script_enabled=False)
        await nojs.route('**/*', lambda route: route.continue_() if urlparse(route.request.url).hostname in ('localhost', '127.0.0.1') else route.abort())
        fallback = await nojs.new_page(); await fallback.goto(data['msc_movie']['url'])
        await expect(fallback.locator('.trailer-links a')).to_have_count(4)
        await expect(fallback.locator('.trailer-controls')).to_be_hidden()
        print('PASS: Without JavaScript, the first trailer remains embedded and all trailers have ordinary watch links.', flush=True)
        await browser.close()

asyncio.run(main())
