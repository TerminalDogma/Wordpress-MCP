"""Published entity links, empty Episode sections, and expandable month/week calendars."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse, parse_qs
from playwright.async_api import async_playwright, expect

root = Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS') != '1': raise SystemExit('Disposable local installation required.')
config = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base = config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'): raise SystemExit('Local installation required.')
data = json.loads(subprocess.check_output(['sh','bin/shared-content.sh','wp','--url='+base+'/site-a/','eval-file','/msc-tests/entity-fixtures.php'],cwd=root,text=True))

async def main():
    async with async_playwright() as p:
        options = {'headless':True,'args':['--no-sandbox']}
        if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path'] = os.environ['MSC_BROWSER_EXECUTABLE']
        browser = await p.chromium.launch(**options)
        context = await browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
        async def local_only(route):
            if urlparse(route.request.url).hostname in ('localhost','127.0.0.1'): await route.continue_()
            else: await route.abort()
        await context.route('**/*',local_only)
        page = await context.new_page(); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
        assert (await page.goto(data['empty_show']['url'])).status == 200
        assert await page.locator('#episodes').count() == 0
        await expect(page.locator('.details-panel a')).to_have_text([data['network']['data']['title']])
        await page.goto(data['show']['url']); await expect(page.locator('#episodes')).to_be_visible()
        await page.goto(data['movie']['url'])
        for key in ('studio','distributor','streaming','person'):
            await expect(page.locator('.details-panel a[href="'+data[key]['url']+'"]')).to_have_count(1)
        assert data['draft']['data']['title'] in await page.locator('.details-panel').inner_text()
        assert not await page.locator('.details-panel a[href="'+data['draft']['url']+'"]').count()
        await page.locator('.details-panel a[href="'+data['studio']['url']+'"]').click()
        await expect(page.locator('.hero-copy h1')).to_have_text(data['studio']['data']['title'])
        await expect(page.locator('.entity-titles a[href="'+data['movie']['url']+'"]')).to_be_visible()
        for key in ('distributor','streaming','network','developer','publisher','console'):
            assert (await page.goto(data[key]['url'])).status == 200,key
            await expect(page.locator('.hero-copy h1')).to_have_text(data[key]['data']['title'])
        await page.goto(data['game']['url'])
        for key in ('developer','publisher','console','pc'):
            await expect(page.locator('.details-panel a[href="'+data[key]['url']+'"]')).to_be_visible()
        print('PASS: Empty/draft-only Show sections hidden; all seven entity templates and published sidebar/reverse links render.',flush=True)

        calendar = base + '/site-a/release-calendar/?calendar_month=2098-01'
        await page.goto(calendar); await page.evaluate('window.mscCalendarDocument=true')
        day = page.locator('.calendar-day[data-date="2098-01-31"]')
        await expect(day.locator('.calendar-event:visible')).to_have_count(3)
        total = await day.locator('.calendar-event').count(); assert total >= 6
        more = day.locator('.calendar-overflow'); await more.locator('summary').click()
        await expect(day.locator('.calendar-event:visible')).to_have_count(total)
        await more.locator('summary').click(); await expect(day.locator('.calendar-event:visible')).to_have_count(3)
        await page.select_option('[name=calendar_limit]','5'); await page.get_by_role('button',name='Show releases',exact=True).click()
        await expect(day.locator('.calendar-event:visible')).to_have_count(5)
        await page.select_option('[name=calendar_limit]','0'); await page.get_by_role('button',name='Show releases',exact=True).click()
        await expect(day.locator('.calendar-event:visible')).to_have_count(total)
        assert await day.locator('.calendar-overflow').count() == 0
        await page.locator('.calendar-view-link').filter(has_text='Week').click()
        await expect(page.locator('.release-calendar-content')).to_have_attribute('data-view','week')
        await page.fill('[name=calendar_date]','2098-02-01'); await page.get_by_role('button',name='Show releases',exact=True).click()
        await expect(page.locator('.calendar-day').first).to_have_attribute('data-date','2098-01-27')
        await expect(page.locator('.calendar-day').last).to_have_attribute('data-date','2098-02-02')
        await expect(page.locator('.calendar-day')).to_have_count(7)
        await expect(page.locator('.calendar-day[data-date="2098-01-31"] .calendar-event:visible')).to_have_count(total)
        await expect(page.locator('.calendar-day[data-date="2098-02-01"] .calendar-event:visible')).not_to_have_count(0)
        await page.get_by_role('link',name='Next week',exact=True).click()
        await expect(page.locator('.calendar-day').first).to_have_attribute('data-date','2098-02-03')
        await page.go_back(); await expect(page.locator('.calendar-day').first).to_have_attribute('data-date','2098-01-27')
        await page.go_forward(); await expect(page.locator('.calendar-day').first).to_have_attribute('data-date','2098-02-03')
        assert await page.evaluate('window.mscCalendarDocument===true')
        await page.select_option('[name=release_type]','msc_movie'); await page.get_by_role('button',name='Show releases',exact=True).click()
        await expect(page.locator('select[name=release_type]')).to_have_value('msc_movie')
        assert parse_qs(urlparse(page.url).query)['calendar_view'] == ['week']
        await page.set_viewport_size({'width':390,'height':844}); await page.screenshot(path='/tmp/msc-week-calendar-mobile.png',full_page=True)
        assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
        await expect(page.locator('.calendar-day')).to_have_count(7)
        await page.set_viewport_size({'width':1440,'height':1000})
        old_url = page.url
        async def unavailable(route): await route.fulfill(status=503,content_type='application/json',body='{"message":"Try again"}')
        await page.route('**/msc/v1/calendar?**',unavailable)
        await page.get_by_role('link',name='Next week',exact=True).click(); await expect(page.locator('.calendar-count')).to_contain_text('retry')
        assert page.url == old_url
        await page.unroute('**/msc/v1/calendar?**',unavailable)
        await page.get_by_role('link',name='Next week',exact=True).click(); await expect(page.locator('.calendar-day').first).to_have_attribute('data-date','2098-02-10')
        print('PASS: Daily limits/expand/collapse, AJAX week/month boundaries, filters, Back/Forward, error retry and mobile layout.',flush=True)

        nojs = await browser.new_context(java_script_enabled=False,viewport={'width':1280,'height':900},reduced_motion='reduce'); await nojs.route('**/*',local_only)
        fallback = await nojs.new_page(); await fallback.goto(calendar)
        native_day = fallback.locator('.calendar-day[data-date="2098-01-31"]'); await expect(native_day.locator('.calendar-event:visible')).to_have_count(3)
        await native_day.locator('.calendar-overflow summary').click(); await expect(native_day.locator('.calendar-event:visible')).to_have_count(total)
        await fallback.locator('.calendar-view-link').filter(has_text='Week').click(); await expect(fallback.locator('.release-calendar-content')).to_have_attribute('data-view','week')
        await fallback.get_by_role('link',name='Next week',exact=True).click(); await expect(fallback.locator('.release-calendar-content')).to_have_attribute('data-view','week')
        await nojs.close()
        await page.goto(base+'/wp-login.php'); await page.fill('#user_login',config['WORDPRESS_ADMIN_USER']); await page.fill('#user_pass',config['WORDPRESS_ADMIN_PASSWORD']); await page.click('#wp-submit'); await page.wait_for_url('**/wp-admin/**')
        await page.goto(base+'/site-a/wp-admin/admin.php?page=msc-acf&record='+data['movie']['uuid'])
        await expect(page.locator('.acf-field[data-name=msc_production_company_records] .select2-selection')).to_be_visible()
        await expect(page.locator('.acf-field[data-name=msc_production_company_records]')).to_contain_text(data['studio']['data']['title'])
        await page.goto(base+'/site-a/wp-admin/admin.php?page=msc-acf&record='+data['studio']['uuid'])
        await expect(page.locator('.acf-field[data-name=msc_logo] .msc-web-image')).to_be_visible()
        assert not errors,errors
        await browser.close()
        print('PASS: No-JavaScript expansion/navigation, searchable ACF entity relationships and image controls.',flush=True)

try: asyncio.run(main())
except Exception:
    report=traceback.format_exc()
    for key in ('WORDPRESS_ADMIN_PASSWORD','OMDB_API_KEY','IGDB_CLIENT_SECRET','SERPER_API_KEY'):
        if config.get(key): report=report.replace(config[key],'[redacted]')
    raise SystemExit(report)
