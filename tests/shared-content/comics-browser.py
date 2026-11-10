"""Browser checks against the explicit disposable comics.php fixtures."""
import asyncio, json, re, sys, os, shutil
from pathlib import Path
from urllib.parse import urlsplit
from playwright.async_api import async_playwright, expect

ROOT=Path(__file__).resolve().parents[2]
info=json.loads(next(line.split(':',1)[1] for line in Path(sys.argv[1]).read_text().splitlines() if line.startswith('COMIC_BROWSER_JSON:')))
cfg=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
out=Path(sys.argv[2] if len(sys.argv)>2 else '/tmp/msc-comic-browser');out.mkdir(parents=True,exist_ok=True)
base=cfg['WORDPRESS_URL'].rstrip('/')

async def main():
    async with async_playwright() as p:
        executable=os.environ.get('MSC_CHROMIUM_EXECUTABLE') or shutil.which('chromium')
        browser=await p.chromium.launch(**({'executable_path':executable} if executable else {}),headless=True,args=['--no-sandbox'])
        context=await browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
        async def route(request):
            if urlsplit(request.request.url).hostname in ('localhost','127.0.0.1'):await request.continue_()
            else:await request.abort()
        await context.route('**/*',route)
        page=await context.new_page();errors=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        async def go(url):
            response=await page.goto(url,wait_until='domcontentloaded');assert response.status<400,(url,response.status)
            await page.evaluate('document.fonts.ready')
        await go(info['book'])
        await expect(page.locator('.comic-issue-list li')).to_have_count(2)
        await page.evaluate('window.comicSessionMarker=123')
        await page.locator('.comic-arc-filters [data-comic-nav]').last.click()
        await page.wait_for_url('**comic_arc=**')
        await expect(page.locator('[data-comic-browser]')).not_to_have_attribute('aria-busy','true')
        assert await page.evaluate('window.comicSessionMarker')==123
        await page.screenshot(path=str(out/'series.png'),full_page=True)
        await go(info['arc'])
        titles=await page.locator('.comic-list-title strong').all_text_contents()
        assert titles[0]=='Signal #2' and titles[1]=='Second Comic #1' and titles[2].endswith('#10'),titles
        await page.screenshot(path=str(out/'arc.png'),full_page=True)
        await go(info['issue']);await expect(page.locator('.comic-issue-badge')).to_have_text('Issue #10')
        await expect(page.locator('.comic-arc-badge')).to_contain_text('Part 3 of 3')
        await expect(page.locator('.msc-footer-credits')).to_contain_text('Grand Comics Database')
        await page.screenshot(path=str(out/'issue.png'),full_page=True)
        await page.set_viewport_size({'width':390,'height':844})
        assert await page.evaluate('document.documentElement.scrollWidth <= innerWidth+2')
        await page.screenshot(path=str(out/'issue-mobile.png'),full_page=True)
        await page.set_viewport_size({'width':1440,'height':1000})
        await go(info['pagination']);await page.evaluate('window.comicSessionMarker=456')
        await page.locator('.comic-pagination a').filter(has_text=re.compile('^2$')).click()
        await page.wait_for_url('**comic_page=2**')
        await expect(page.locator('.comic-issue-list li')).to_have_count(1)
        assert await page.evaluate('window.comicSessionMarker')==456
        await expect(page.locator('.comic-list-number')).to_have_text('#51')
        await go(info['calendar']);await expect(page.locator('.calendar-event-show').filter(has_text=info['query'])).to_contain_text(info['query'])
        response=await page.goto(base+'/site-a/comics/',wait_until='domcontentloaded');assert response.status==404
        await go(base+'/wp-login.php');await page.fill('#user_login',cfg['WORDPRESS_ADMIN_USER']);await page.fill('#user_pass',cfg['WORDPRESS_ADMIN_PASSWORD'])
        await page.click('#wp-submit');await page.wait_for_url('**/wp-admin/**')
        await go(info['admin']);await expect(page.locator('h2').filter(has_text=info['query'])).to_be_visible()
        await page.screenshot(path=str(out/'admin-issues.png'),full_page=True)
        await page.get_by_role('link',name='Bulk edit / publish issues',exact=True).click()
        await expect(page.locator('input[name="msc_comic_parent"]')).to_have_count(1)
        await expect(page.locator('th.column-comic_parent').first).to_contain_text('Comic Book')
        await expect(page.locator('#the-list')).not_to_contain_text('Second Comic #1')
        await expect(page.locator('select[name="action"]')).to_be_visible()
        await go(info['acf'])
        await expect(page.locator('.acf-field[data-name="msc_issue_number"] input').first).to_have_value('10')
        await expect(page.locator('.acf-field[data-name="msc_arc_position"] input').first).to_have_value('3')
        assert (await page.locator('.acf-field[data-name="msc_parent_comic"] select').input_value()).isdigit()
        await expect(page.get_by_role('button',name='Save and publish globally',exact=True)).to_be_visible()
        await go(info['source'])
        await page.locator('[data-comic-check-all]').check()
        await expect(page.locator('input[name="issues[]"]:checked')).to_have_count(2)
        await page.locator('input[name="refresh"]').check();await page.get_by_role('button',name='Import selected issues',exact=True).click()
        await page.wait_for_url('**job=**');assert await page.locator('#msc-import-job').get_attribute('data-item-label')=='issue'
        await page.get_by_role('button',name='Start / resume',exact=True).click()
        await expect(page.locator('.msc-job-status')).to_contain_text('2 issues processed',timeout=45000)
        assert not errors,errors
        await browser.close()
    print('PASS: comic templates, arc order, AJAX filtering/pagination, mobile layout, calendar, Site A exclusion, ACF fields and selectable batch import')

asyncio.run(main())
