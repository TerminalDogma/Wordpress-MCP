"""Optional real-browser ACF workflow; requires Playwright and a disposable demo."""
import asyncio
import json
from pathlib import Path
import os
import subprocess
from urllib.parse import urlparse
from playwright.async_api import async_playwright

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1':
    raise SystemExit('Run only against a disposable demo with MSC_DISPOSABLE_TESTS=1.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config.get('WORDPRESS_URL', 'http://localhost:8080').rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('Browser tests are local only.')
records = json.loads(subprocess.check_output(['sh', 'bin/shared-content.sh', 'wp', 'eval', 'echo wp_json_encode(get_network_option(get_current_network_id(),"msc_demo_records"));'], cwd=root, text=True))
uuid = records.get('episode', '')
movie = records.get('movie', '')
if len(uuid) != 36 or len(movie) != 36:
    raise SystemExit('Run the shared-content demo command first.')

async def main():
    async with async_playwright() as p:
        options = {'headless': True, 'args': ['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'):
            options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context()
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost', '127.0.0.1'):
                await route.continue_()
            else:
                await route.abort()
        await context.route('**/*', local_only)
        page = await context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        await page.goto(base + '/wp-login.php')
        await page.fill('#user_login', config.get('WORDPRESS_ADMIN_USER', 'siteadmin'))
        await page.fill('#user_pass', config['WORDPRESS_ADMIN_PASSWORD'])
        await page.click('#wp-submit')
        await page.wait_for_url('**/wp-admin/**')
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + uuid + '&scope=global')
        date = page.locator('.acf-field[data-name="msc_release_date"]')
        await date.locator('input[type="text"]').click()
        picker = page.locator('#ui-datepicker-div')
        await picker.wait_for(state='visible')
        await picker.locator('.ui-datepicker-year').select_option('2099')
        await picker.locator('.ui-datepicker-month').select_option('1')
        await picker.get_by_role('link', name='1', exact=True).click()
        assert await date.locator('input[type="hidden"]').input_value() == '20990201', 'Date picker did not update its raw value.'
        await page.get_by_role('button', name='Save shared fields globally', exact=True).click()
        await page.wait_for_url('**updated=1', timeout=60000)
        assert await page.get_by_text('Shared fields saved.', exact=False).count(), 'Global ACF save did not complete.'
        await page.goto(base + '/site-b/wp-admin/admin.php?page=msc-acf&record=' + uuid + '&scope=local')
        date = page.locator('.acf-field[data-name="msc_release_date"]')
        assert await date.locator('input[type="hidden"]').input_value() == '20990102', 'Site B lost its local override.'
        await date.locator('input[name="override[release_date]"]').uncheck()
        await page.get_by_role('button', name='Save local overrides', exact=True).click()
        await page.wait_for_url('**updated=1', timeout=60000)
        date = page.locator('.acf-field[data-name="msc_release_date"]')
        assert await date.locator('input[type="hidden"]').input_value() == '20990201', 'Reset did not inherit the newest shared date.'
        assert not await date.locator('input[name="override[release_date]"]').is_checked()
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + movie + '&scope=global')
        assert await page.get_by_role('heading', name='Movies — ACF Shared Fields', exact=True).count()
        for name in ('director', 'distributor', 'runtime', 'release_date'):
            assert await page.locator('.acf-field[data-name="msc_' + name + '"]').count() == 1
        for name in ('premiere_date', 'number_of_seasons', 'number_of_episodes', 'parent_show', 'network'):
            assert await page.locator('.acf-field[data-name="msc_' + name + '"]').count() == 0
        await page.locator('.acf-field[data-name="msc_director"] input[type="text"]').fill('Browser Film Director')
        await page.get_by_role('button', name='Save shared fields globally', exact=True).click()
        await page.wait_for_url('**updated=1', timeout=60000)
        await page.goto(base + '/site-b/wp-admin/admin.php?page=msc-acf&record=' + movie + '&scope=local')
        assert await page.locator('.acf-field[data-name="msc_director"] input[type="text"]').input_value() == 'Browser Film Director'
        await page.goto(base + '/site-a/wp-admin/admin.php?page=msc-acf&record=' + records['show'] + '&scope=global')
        assert await page.get_by_role('heading', name='TV Shows — ACF Shared Fields', exact=True).count()
        assert await page.locator('.acf-field[data-name="msc_premiere_date"]').count() == 1
        assert await page.locator('.acf-field[data-name="msc_director"]').count() == 0
        assert not errors, errors
        await browser.close()
        print('PASS: Browser ACF dates, global/local/reset, distinct TV/Movie fields, Movie save across sites and JavaScript checks.')

try:
    asyncio.run(main())
except Exception as error:
    raise SystemExit(str(error).replace(config['WORDPRESS_ADMIN_PASSWORD'], '[redacted]'))
