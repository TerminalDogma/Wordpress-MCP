"""Cold-cache Movie search, local limiter recovery and genuine provider cooldown UI."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse
from playwright.async_api import async_playwright, expect

root=Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS')!='1': raise SystemExit('Disposable local installation required.')
config=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base=config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost','127.0.0.1'): raise SystemExit('Local installation required.')
def wp(code): return subprocess.check_output(['docker','compose','exec','-T','--user','33','wordpress','wp','--url='+base+'/site-a/','eval',code],cwd=root,text=True)
seed=json.loads(wp('echo wp_json_encode(get_network_option(get_current_network_id(),"msc_movie_search_fixture_seed"));'))

async def main():
 async with async_playwright() as p:
  options={'headless':True,'args':['--no-sandbox']}
  if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path']=os.environ['MSC_BROWSER_EXECUTABLE']
  browser=await p.chromium.launch(**options)
  context=await browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
  async def local_only(route):
   if urlparse(route.request.url).hostname in ('localhost','127.0.0.1'): await route.continue_()
   else: await route.abort()
  await context.route('**/*',local_only)
  page=await context.new_page(); errors=[]; page.on('pageerror',lambda e:errors.append(str(e)))
  await page.goto(base+'/wp-login.php'); await page.fill('#user_login',config['WORDPRESS_ADMIN_USER']); await page.fill('#user_pass',config['WORDPRESS_ADMIN_PASSWORD']); await page.click('#wp-submit'); await page.wait_for_url('**/wp-admin/**')
  await page.goto(base+'/site-a/wp-admin/admin.php?page=msc-import&kind=msc_movie')
  async def search(query):
   await page.fill('input[name=q]',query); await page.get_by_role('button',name='Search',exact=True).click(); await page.wait_for_load_state('domcontentloaded')
  await search('Movie batch '+str(seed))
  await expect(page.locator('.msc-omdb-result')).to_have_count(12)
  assert not await page.locator('.notice-error').count()
  assert 'Please wait a few seconds' not in await page.locator('main, #wpbody-content').last.inner_text()
  await page.get_by_role('link',name='Preview Fixture Movie 1',exact=True).click()
  await expect(page.locator('.msc-omdb-preview h2')).to_have_text('Review Fixture Movie 1')
  await expect(page.get_by_role('button',name='Create shared record',exact=True)).to_be_visible()
  print('PASS: A cold twelve-result Movie search and its preview complete without a local rate-limit error.',flush=True)
  wp('set_site_transient("msc_open_rate_wikidata",["start"=>time()+600,"count"=>12],610);')
  await search('Movie deep '+str(seed)); await expect(page.locator('.msc-omdb-result')).to_have_count(12)
  assert not await page.locator('.notice-error').count()
  await search('Movie quota '+str(seed)); await expect(page.locator('.notice-error')).to_contain_text('source is busy')
  await search('Movie batch '+str(seed)); await expect(page.locator('.msc-omdb-result')).to_have_count(12)
  assert not await page.locator('.notice-error').count()
  assert not errors,errors
  await browser.close()
  print('PASS: Invalid future-window recovery, visible provider cooldowns and cached searches during a real provider pause.',flush=True)

try: asyncio.run(main())
except Exception:
 report=traceback.format_exc()
 for key in ('WORDPRESS_ADMIN_PASSWORD','OMDB_API_KEY','IGDB_CLIENT_SECRET','SERPER_API_KEY'):
  if config.get(key): report=report.replace(config[key],'[redacted]')
 raise SystemExit(report)
