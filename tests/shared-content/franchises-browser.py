"""Public franchise collections, back-links, editor controls and narrow layouts."""
import asyncio,json,os,shutil,sys
from pathlib import Path
from urllib.parse import urlsplit
from playwright.async_api import async_playwright,expect
ROOT=Path(__file__).resolve().parents[2]
info=json.loads(next(x.split(':',1)[1] for x in Path(sys.argv[1]).read_text().splitlines() if x.startswith('FRANCHISE_BROWSER_JSON:')))
cfg=dict(x.split('=',1) for x in (ROOT/'.env').read_text().splitlines() if '=' in x and not x.startswith('#'))
out=Path(sys.argv[2] if len(sys.argv)>2 else '/tmp/msc-franchises-browser');out.mkdir(parents=True,exist_ok=True)
async def main():
 async with async_playwright() as p:
  browser=await p.chromium.launch(executable_path=os.environ.get('MSC_BROWSER_EXECUTABLE')or shutil.which('chromium'),headless=True,args=['--no-sandbox'])
  context=await browser.new_context(viewport={'width':1440,'height':1000})
  await context.route('**/*',lambda r:r.continue_() if urlsplit(r.request.url).hostname in ('localhost','127.0.0.1') else r.abort())
  page=await context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
  assert (await page.goto(info['url'])).status==200
  await expect(page.locator('h1')).to_have_text(info['title']);await expect(page.locator('.franchise-title')).to_have_count(2)
  await expect(page.locator('.franchise-grid')).not_to_contain_text(info['draft']);assert await page.locator('.franchise-title-copy p:not(.eyebrow)').count()==0
  await expect(page.locator('.franchise-poster img')).to_be_visible();assert await page.locator('.franchise-poster img').evaluate('(el)=>el.complete&&el.naturalWidth>0')
  await page.screenshot(path=str(out/'franchise-desktop.png'),full_page=True)
  await page.set_viewport_size({'width':390,'height':844});assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
  await page.screenshot(path=str(out/'franchise-mobile.png'),full_page=True)
  await page.goto(info['movie_url']);await expect(page.locator('.franchise-links')).to_contain_text(info['title'])
  await page.goto(cfg['WORDPRESS_URL'].rstrip('/')+'/wp-login.php');await page.fill('#user_login',cfg['WORDPRESS_ADMIN_USER']);await page.fill('#user_pass',cfg['WORDPRESS_ADMIN_PASSWORD']);await page.click('#wp-submit');await page.wait_for_url('**/wp-admin/**')
  await page.set_viewport_size({'width':1440,'height':1100});await page.goto(info['admin']);await expect(page.locator('#msc-movies')).to_be_visible();await expect(page.locator('#msc-shows')).to_be_visible()
  await page.screenshot(path=str(out/'franchise-editor.png'),full_page=True)
  assert not errors,errors
  await browser.close()
 print('PASS: Franchise desktop/mobile, publication filtering, poster image, title back-links and shared relationship editor')
asyncio.run(main())
