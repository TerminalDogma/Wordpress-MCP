"""Real public pages: one overview, AJAX seasons/pagination, history, errors and no-JS fallback."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse, parse_qs
from playwright.async_api import async_playwright, expect

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Disposable local installation required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Local installation required.')
data = json.loads(subprocess.check_output(['sh', 'bin/shared-content.sh', 'wp', '--url=' + base + '/site-a/', 'eval-file', '/msc-tests/season-fixtures.php'], cwd=root, text=True))

async def main():
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width':1440, 'height':1000}, reduced_motion='reduce')
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost', '127.0.0.1'): await route.continue_()
            else: await route.abort()
        await context.route('**/*', local_only)
        page = await context.new_page(); errors = []; page.on('pageerror', lambda e: errors.append(str(e)))
        documents = []; page.on('request', lambda request: documents.append(request.url) if request.is_navigation_request() and request.frame == page.main_frame else None)
        for key, record in data['catalog'].items():
            assert (await page.goto(record['url'])).status == 200
            assert await page.locator('.hero-synopsis').count() == 0, key
            assert record['text'] not in await page.locator('.hero-copy').inner_text(), key
            await expect(page.locator('.story-copy .prose').first).to_contain_text(record['text'])
        print('PASS: One overview/biography on all five catalog types, including an excerpt-only fallback.', flush=True)

        async def assert_season(label, count, status=''):
            await expect(page.locator('.season-heading')).to_have_text(label)
            await expect(page.locator('.episode-guide-content')).to_have_attribute('aria-busy', 'false')
            await expect(page.locator('.episode-list li')).to_have_count(count)
            assert await page.evaluate('window.mscOriginalDocument === true'), 'Unexpected page refresh'
            assert await page.locator('.episode-list img,.episode-list p').count() == 0
            await expect(page.locator('.episode-load-status')).to_have_text(status)
            assert await page.evaluate('document.activeElement?.className !== "season-heading" && String(window.getSelection()) === ""'), 'Season changes must not focus or select the heading'

        for site, url in data['sites'].items():
            await page.goto(url)
            await page.evaluate('window.mscOriginalDocument = true')
            original_documents = len(documents)
            assert await page.locator('.season-pagination a').count() == 4
            assert not await page.get_by_role('link', name='Season 9', exact=True).count()
            await page.get_by_role('link', name='Season 7', exact=True).click()
            await assert_season('Season 7', 2)
            assert parse_qs(urlparse(page.url).query)['season'] == ['7']
            await page.get_by_role('link', name='Season 1', exact=True).click()
            await assert_season('Season 1', 50)
            await page.get_by_role('link', name='Next episodes', exact=True).click()
            await assert_season('Season 1', 1)
            assert 'Episode 51' in await page.locator('.episode-list').inner_text()
            assert parse_qs(urlparse(page.url).query)['episode_page'] == ['2']
            await page.get_by_role('link', name='Season 7', exact=True).click()
            await assert_season('Season 7', 2)
            assert 'episode_page' not in parse_qs(urlparse(page.url).query)
            await page.go_back()
            await assert_season('Season 1', 1)
            await page.go_back()
            await assert_season('Season 1', 50)
            await page.go_forward()
            await assert_season('Season 1', 1)
            await page.get_by_role('link', name='Previous episodes', exact=True).click()
            await assert_season('Season 1', 50)
            await page.get_by_role('link', name='Season not set', exact=True).click()
            await assert_season('Season not set', 1)
            assert 'Season unknown' in await page.locator('.episode-list').inner_text()
            assert len(documents) == original_documents, 'Season navigation issued a document request'
            assert await page.locator('main .msc-attribution,main .msc-image-credit').count() == 0
            assert 'TVmaze test fixture' in await page.locator('#msc-source-footer').inner_html()
            print('PASS: AJAX seasons, 50-row paging, reset, Back/Forward and footer credits on Site ' + site.upper(), flush=True)

        # A failed request keeps the current season and URL, and the next click retries successfully.
        await page.goto(data['sites']['a'])
        await page.evaluate('window.mscOriginalDocument = true')
        old_url = page.url
        async def unavailable(route): await route.fulfill(status=503, content_type='text/html', body='Unavailable')
        await page.route('**/*season=7*', unavailable)
        await page.get_by_role('link', name='Season 7', exact=True).click()
        await expect(page.locator('.episode-load-status')).to_contain_text('could not be loaded')
        assert page.url == old_url
        await assert_season('Season 0', 1, 'Episodes could not be loaded. Please try the season link again.')
        await page.unroute('**/*season=7*', unavailable)
        await page.get_by_role('link', name='Season 7', exact=True).click()
        await assert_season('Season 7', 2)

        # A slow older response cannot overwrite a newer selection.
        async def delayed(route):
            response = await route.fetch(); await asyncio.sleep(0.3)
            try: await route.fulfill(response=response)
            except Exception: pass  # An aborted fetch may already have closed this route.
        await page.route('**/*season=1*', delayed)
        await page.get_by_role('link', name='Season 1', exact=True).click()
        await page.get_by_role('link', name='Season 0', exact=True).click()
        await assert_season('Season 0', 1)
        await page.wait_for_timeout(400)
        await assert_season('Season 0', 1)
        await page.unroute('**/*season=1*', delayed)
        await page.set_viewport_size({'width':390, 'height':844})
        await page.get_by_role('link', name='Season 7', exact=True).click()
        await assert_season('Season 7', 2)
        assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        await page.screenshot(path='/tmp/msc-ajax-seasons-mobile.png', full_page=True)
        print('PASS: Retry after failure, rapid selection, loading state and mobile layout.', flush=True)

        fallback = await browser.new_context(java_script_enabled=False, reduced_motion='reduce', viewport={'width':1440,'height':1000})
        await fallback.route('**/*', local_only)
        no_js = await fallback.new_page()
        await no_js.goto(data['sites']['a'])
        await no_js.get_by_role('link', name='Season 1', exact=True).click()
        assert await no_js.locator('.episode-list li').count() == 50
        await no_js.get_by_role('link', name='Next episodes', exact=True).click()
        assert await no_js.locator('.episode-list li').count() == 1
        assert 'Episode 51' in await no_js.locator('.episode-list').inner_text()
        await no_js.reload()
        assert await no_js.locator('.episode-list li').count() == 1
        assert not errors, errors
        await browser.close()
        print('PASS: Direct links, reloads and ordinary season/episode navigation without JavaScript.', flush=True)

try:
    asyncio.run(main())
except Exception:
    report = traceback.format_exc()
    for key in ('WORDPRESS_ADMIN_PASSWORD', 'OMDB_API_KEY', 'IGDB_CLIENT_SECRET'):
        if config.get(key): report = report.replace(config[key], '[redacted]')
    raise SystemExit(report)
