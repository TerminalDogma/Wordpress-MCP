"""Public release guides, generated pages and source-free streaming directory workflow."""
import asyncio,json,os,shutil,sys
from pathlib import Path
from urllib.parse import urlsplit
from playwright.async_api import async_playwright,expect
ROOT=Path(__file__).resolve().parents[2]
info=json.loads(next(x.split(':',1)[1]for x in Path(sys.argv[1]).read_text().splitlines()if x.startswith('UPCOMING_BROWSER_JSON:')))
cfg=dict(x.split('=',1)for x in (ROOT/'.env').read_text().splitlines()if '='in x and not x.startswith('#'))
out=Path(sys.argv[2]if len(sys.argv)>2 else'/tmp/msc-upcoming-browser');out.mkdir(parents=True,exist_ok=True)
async def main():
 async with async_playwright()as p:
  exe=os.environ.get('MSC_BROWSER_EXECUTABLE')or shutil.which('chromium')
  browser=await p.chromium.launch(**({'executable_path':exe}if exe else{}),headless=True,args=['--no-sandbox'])
  context=await browser.new_context(viewport={'width':1440,'height':1050},reduced_motion='reduce')
  await context.route('**/*',lambda route:route.continue_()if urlsplit(route.request.url).hostname in('localhost','127.0.0.1')else route.abort())
  page=await context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  url=info['hub']+'?release_month='+info['month']+'&release_search='+info['query']
  response=await page.goto(url,wait_until='networkidle');assert response.status==200
  await expect(page.locator('h1')).to_have_count(1)
  await expect(page.locator('.release-guide-card')).to_have_count(4)
  logos=page.locator('.release-service-choice img');assert await logos.count()>=8
  assert await logos.evaluate_all('(xs)=>xs.every(x=>x.complete&&x.naturalWidth>0)')
  await page.evaluate('window.releaseMarker=123')
  await page.locator('.release-service-choice[href*="'+info['service']+'"]').click()
  await expect(page.locator('.release-guide-card')).to_have_count(3)
  assert await page.evaluate('window.releaseMarker')==123
  await expect(page.locator('h1')).to_contain_text('Netflix')
  await expect(page.locator('.release-card-parent')).to_contain_text('Release Test Series')
  await expect(page.locator('.release-card-kind').filter(has_text='Season 3 premiere')).to_have_count(1)
  await page.screenshot(path=str(out/'netflix-releases.png'),full_page=True)
  await page.select_option('[name=release_view]','list');await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.release-result-days')).to_have_attribute('data-view','list')
  assert await page.evaluate('window.releaseMarker')==123
  await page.go_back();await expect(page.locator('.release-result-days')).to_have_attribute('data-view','cards')
  await page.locator('.release-months a').nth(2).click()
  await expect(page.locator('.release-guide-empty')).to_be_visible()
  assert await page.evaluate('window.releaseMarker')==123
  await page.go_back();await expect(page.locator('.release-guide-card')).to_have_count(3)
  await page.get_by_role('link',name='Calendar grid',exact=False).click()
  await expect(page.locator('select[name=release_service]')).to_have_value(info['service'])
  await expect(page.locator('.calendar-event-service').filter(has_text='Netflix').first).to_be_visible()
  await page.get_by_role('link',name='Week',exact=True).click()
  await expect(page.locator('select[name=release_service]')).to_have_value(info['service'])
  await page.goto(url,wait_until='networkidle')
  await page.set_viewport_size({'width':390,'height':844})
  assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
  await page.screenshot(path=str(out/'release-guide-mobile.png'),full_page=True)
  await page.goto(info['monthly']+'?release_search='+info['query'],wait_until='networkidle')
  await expect(page.locator('.release-guide-card.type-msc_show')).to_have_count(1)
  await expect(page.locator('body')).to_contain_text('Editorial introduction.')
  await expect(page.locator('.release-date-group')).to_have_attribute('data-date','2032-05-24')
  await page.goto(url+'&release_service='+info['service'],wait_until='networkidle')
  await page.evaluate('window.releaseResetMarker=321')
  await page.locator('.release-service-all').click()
  await expect(page.locator('.release-guide-card')).to_have_count(4)
  assert await page.evaluate('window.releaseResetMarker')==321
  await page.goto(info['monthly']+'?release_search=unmatched-release-marker',wait_until='networkidle')
  await expect(page.locator('.release-guide-empty')).to_be_visible()
  await page.get_by_role('link',name='Clear filters',exact=True).click()
  await expect(page.locator('.release-guide-empty')).to_have_count(0)
  assert await page.locator('.release-guide-card').count()>0
  await page.goto(info['service_url'],wait_until='networkidle')
  await expect(page.get_by_role('link',name='Upcoming releases on Netflix',exact=True)).to_be_visible()
  await page.goto(cfg['WORDPRESS_URL'].rstrip('/')+'/wp-login.php');await page.fill('#user_login',cfg['WORDPRESS_ADMIN_USER']);await page.fill('#user_pass',cfg['WORDPRESS_ADMIN_PASSWORD']);await page.click('#wp-submit');await page.wait_for_url('**/wp-admin/**')
  await page.set_viewport_size({'width':1440,'height':1050});await page.goto(info['admin'])
  await expect(page.get_by_role('heading',name='Release Pages',exact=True)).to_be_visible()
  await page.fill('#release-start',info['month']);await page.fill('#release-months','1');await page.select_option('#release-type',info['type']);await page.select_option('#release-service',info['service'])
  await page.get_by_role('button',name='Generate monthly release pages',exact=True).click();await page.wait_for_url('**generated=1')
  await expect(page.locator('.notice-success')).to_contain_text('Existing pages were retained')
  await page.screenshot(path=str(out/'release-page-generator.png'),full_page=True)
  plain=await browser.new_context(java_script_enabled=False)
  plain_page=await plain.new_page()
  await plain_page.goto(info['monthly']+'?release_search='+info['query'])
  await plain_page.locator('.release-service-all').click()
  await expect(plain_page.locator('h1')).to_have_text('Upcoming releases')
  await plain.close()
  assert not errors,errors
  await browser.close()
 print('PASS: release cards, provider logos/filters, inherited episode service, AJAX views/months/history, mobile layout, live monthly guides and admin generator')
asyncio.run(main())
