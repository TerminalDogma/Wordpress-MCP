"""Optional real-browser ACF workflow; requires Playwright and a disposable demo."""
import asyncio
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
uuid = subprocess.check_output(['sh', 'bin/shared-content.sh', 'wp', 'eval', 'echo get_network_option(get_current_network_id(),"msc_demo_records")["episode"];'], cwd=root, text=True).strip()
if len(uuid) != 36:
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
        assert not errors, errors
        await browser.close()
        print('PASS: Browser ACF date picker, global save, local preservation, reset and JavaScript checks.')

try:
    asyncio.run(main())
except Exception as error:
    raise SystemExit(str(error).replace(config['WORDPRESS_ADMIN_PASSWORD'], '[redacted]'))
