"""Post-list bulk actions, complete training lists, and editorial review before publication."""
import asyncio,json,sys,shutil,os
from pathlib import Path
from urllib.parse import urlsplit
from playwright.async_api import async_playwright,expect
ROOT=Path(__file__).resolve().parents[2]
info=json.loads(next(x.split(':',1)[1]for x in Path(sys.argv[1]).read_text().splitlines()if x.startswith('TRAINING_BROWSER_JSON:')))
cfg=dict(x.split('=',1)for x in(ROOT/'.env').read_text().splitlines()if '='in x and not x.startswith('#'))
out=Path(sys.argv[2]if len(sys.argv)>2 else'/tmp/msc-training-browser');out.mkdir(parents=True,exist_ok=True)
async def main():
 async with async_playwright()as p:
  exe=os.environ.get('MSC_BROWSER_EXECUTABLE')or shutil.which('chromium')
  browser=await p.chromium.launch(**({'executable_path':exe}if exe else{}),headless=True,args=['--no-sandbox'])
  guest=await browser.new_context();g=await guest.new_page();response=await g.goto(info['pending_url']);assert response.status==404
  context=await browser.new_context(viewport={'width':1440,'height':1050})
  await context.route('**/*',lambda route:route.continue_()if urlsplit(route.request.url).hostname in('localhost','127.0.0.1')else route.abort())
  page=await context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  await page.goto(cfg['WORDPRESS_URL'].rstrip('/')+'/wp-login.php');await page.fill('#user_login',cfg['WORDPRESS_ADMIN_USER']);await page.fill('#user_pass',cfg['WORDPRESS_ADMIN_PASSWORD']);await page.click('#wp-submit');await page.wait_for_url('**/wp-admin/**')
  await page.goto(info['movies']);await expect(page.locator('[name=msc_training]')).to_have_value('1');assert '250' in await page.locator('.displaying-num').first.inner_text()
  assert 'Publish selected globally' in await page.locator('#bulk-action-selector-top').inner_text()
  await page.screenshot(path=str(out/'training-movie-list.png'))
  await page.goto(info['shows']);
  if not await page.locator('#screen-options-wrap').is_visible():await page.locator('#show-settings-link').click()
  await page.locator('input[name="wp_screen_options[value]"]').fill('250');await page.locator('#screen-options-apply').click()
  for id in info['bulk_ids']:await page.locator('#cb-select-'+str(id)).check()
  await page.select_option('#bulk-action-selector-top','msc_publish_global');await page.locator('#doaction').click();await page.wait_for_url('**page=msc-bulk-publish**')
  await page.get_by_role('button',name='Start / resume publishing',exact=True).click();await expect(page.locator('.msc-bulk-status')).to_have_text('2 / 2 processed',timeout=60000)
  await expect(page.locator('.msc-bulk-results')).not_to_contain_text('Skipped:')
  await expect(page.get_by_role('link',name='Return to post list',exact=True)).to_have_attribute('href',__import__('re').compile('msc_training=1'))
  await page.screenshot(path=str(out/'global-bulk-publication.png'))
  await page.goto(info['posts']+'?post_status=pending');await expect(page.locator('#post-'+str(info['pending_id']))).to_be_visible()
  assert 'Publish selected globally' not in await page.locator('#bulk-action-selector-top').inner_text()
  await page.locator('#cb-select-'+str(info['pending_id'])).check();await page.select_option('#bulk-action-selector-top','msc_publish_site');await page.locator('#doaction').click();await page.wait_for_url('**page=msc-bulk-publish**')
  await page.get_by_role('button',name='Start / resume publishing',exact=True).click();await expect(page.locator('.msc-bulk-status')).to_have_text('1 / 1 processed',timeout=60000)
  response=await g.goto(info['pending_url'],wait_until='domcontentloaded');assert response.status==200
  await expect(g.locator('h1')).to_have_count(1);await expect(g.locator('h1')).to_contain_text('2044')
  await expect(g.locator('body')).not_to_contain_text('Fictional presentation feature')
  await page.goto(info['release_admin']);await expect(page.get_by_role('heading',name='Release Posts',exact=True)).to_be_visible();await expect(page.get_by_role('button',name='Generate release posts for review',exact=True)).to_be_visible()
  await page.screenshot(path=str(out/'release-post-review-generator.png'))
  await page.goto(info['training']);await page.get_by_role('button',name='Import 250 Movies and 250 TV Shows',exact=True).click();await page.wait_for_url('**job=**');await page.get_by_role('button',name='Start / resume import',exact=True).click()
  await page.wait_for_function('Number(document.querySelector("#msc-training-import progress").value)>=10',timeout=60000)
  await page.get_by_role('button',name='Pause',exact=True).click();await expect(page.get_by_role('button',name='Start / resume import',exact=True)).to_be_enabled(timeout=60000)
  assert int(await page.locator('#msc-training-import progress').get_attribute('value'))<500
  assert not errors,errors
  await browser.close()
 print('PASS: 250-title filtered lists, local/global bulk actions, pending release visibility, reviewed publication, generated post titles and resumable training imports')
asyncio.run(main())
