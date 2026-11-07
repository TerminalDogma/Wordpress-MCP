"""Bespoke archives: metadata, scoped filters, pagination/history, privacy and no excerpts."""
import asyncio
import json
import os
from pathlib import Path
import subprocess
import traceback
from urllib.parse import urlparse, urlencode
from playwright.async_api import async_playwright, expect

root=Path(__file__).resolve().parents[2]
if os.environ.get('MSC_DISPOSABLE_TESTS')!='1': raise SystemExit('Disposable local installation required.')
config=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
base=config['WORDPRESS_URL'].rstrip('/')
if urlparse(base).hostname not in ('localhost','127.0.0.1'): raise SystemExit('Local installation required.')
data=json.loads(subprocess.check_output(['sh','bin/shared-content.sh','wp','--url='+base+'/site-a/','eval-file','/msc-tests/archive-fixtures.php'],cwd=root,text=True))
def url(site,slug,**params): return base+'/site-'+site+'/'+slug+'/?'+urlencode(params or {'catalog_q':data['prefix']})

async def main():
 async with async_playwright() as p:
  options={'headless':True,'args':['--no-sandbox']}
  if os.environ.get('MSC_BROWSER_EXECUTABLE'): options['executable_path']=os.environ['MSC_BROWSER_EXECUTABLE']
  browser=await p.chromium.launch(**options)
  context=await browser.new_context(viewport={'width':1440,'height':1100},reduced_motion='reduce')
  async def local_only(route):
   if urlparse(route.request.url).hostname in ('localhost','127.0.0.1'): await route.continue_()
   else: await route.abort()
  await context.route('**/*',local_only)
  page=await context.new_page(); errors=[]; documents=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.on('request',lambda r:documents.append(r.url) if r.is_navigation_request() and r.frame==page.main_frame else None)
  movies=url('a','movies',catalog_q=data['prefix'],catalog_sort='title_asc')
  await page.goto(movies); await page.evaluate('window.mscArchiveDocument=true'); original_documents=len(documents)
  await expect(page.locator('main h1')).to_have_text('Movies')
  await expect(page.locator('.archive-card')).to_have_count(20)
  await expect(page.locator('.archive-card-title').first).to_have_text(data['movies'][0]['data']['title'])
  await expect(page.locator('.catalog-result-count')).to_have_text('Showing 1–20 of 21')
  assert data['excerpt'] not in await page.locator('main').inner_html()
  assert data['description'] not in await page.locator('main').inner_html()
  assert 'PRIVATE_GENRE_'+data['tag'] not in await page.content()
  await page.screenshot(path='/tmp/msc-archive-movies-desktop.png',full_page=False)
  await page.get_by_role('link',name='Next',exact=True).click()
  await expect(page.locator('.archive-card')).to_have_count(1)
  await expect(page.locator('.archive-card-title')).to_have_text([data['movies'][20]['data']['title']])
  await expect(page.locator('.catalog-result-count')).to_have_text('Showing 21–21 of 21')
  await page.fill('#catalog-query',data['movies'][1]['data']['title']); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.archive-card-title')).to_have_text([data['movies'][1]['data']['title']])
  assert '/page/2/' not in page.url
  await page.go_back(); await expect(page.locator('.archive-card-title')).to_have_text([data['movies'][20]['data']['title']])
  await page.go_back(); await expect(page.locator('.archive-card')).to_have_count(20)
  await page.fill('#catalog-query',data['prefix']); await page.select_option('#catalog-facet','Drama'); await page.select_option('#catalog-year','2091'); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.archive-card')).to_have_count(10)
  await expect(page.locator('.catalog-result-count')).to_have_text('Showing 1–10 of 10')
  await page.fill('#catalog-query','No result '+data['tag']); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.catalog-empty h2')).to_have_text('No matching results')
  assert not await page.locator('.archive-card').count()
  await page.get_by_role('link',name='Reset filters',exact=True).first.click(); await expect(page.locator('#catalog-query')).to_have_value('')
  await page.fill('#catalog-query',data['movies'][0]['data']['title']); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.archive-card-title')).to_have_text([data['movies'][0]['data']['title']])
  old_url=page.url
  async def unavailable(route): await route.fulfill(status=503,content_type='text/html',body='Unavailable')
  await page.route('**/site-a/movies/?**',unavailable)
  await page.fill('#catalog-query',data['movies'][1]['data']['title']); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.catalog-load-status')).to_contain_text('retry')
  assert page.url==old_url
  await page.unroute('**/site-a/movies/?**',unavailable)
  await page.get_by_role('button',name='Apply filters',exact=True).click(); await expect(page.locator('.archive-card-title')).to_have_text([data['movies'][1]['data']['title']])
  assert await page.evaluate('window.mscArchiveDocument===true') and len(documents)==original_documents
  print('PASS: Scoped title search, combined genre/year filters, pagination/reset, history, retry and protected-content exclusion without document refresh.',flush=True)

  # A slow response cannot overwrite the newer filter selection.
  async def delayed(route):
   response=await route.fetch(); await asyncio.sleep(.3)
   try: await route.fulfill(response=response)
   except Exception: pass
  await page.route('**/site-a/movies/?**',delayed)
  await page.fill('#catalog-query',data['movies'][2]['data']['title']); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await page.fill('#catalog-query',data['movies'][3]['data']['title']); await page.get_by_role('button',name='Apply filters',exact=True).click()
  await expect(page.locator('.archive-card-title')).to_have_text([data['movies'][3]['data']['title']])
  await page.unroute('**/site-a/movies/?**',delayed)

  views=[('a','movies','movies'),('a','shows','shows'),('a','people','people'),('a','episodes','episodes'),('c','games','games'),('a','production-companies','directory'),('a','distributors','directory'),('a','streaming-services','directory'),('a','tv-networks','directory'),('c','game-developers','directory'),('c','game-publishers','directory'),('c','game-platforms','hardware')]
  for site,slug,layout in views:
   response=await page.goto(url(site,slug))
   assert response.status==200,(slug,response.status)
   await expect(page.locator('.archive-layout-'+layout)).to_be_visible()
   assert await page.locator('.archive-card').count()>0,slug
   assert not (await page.locator('main h1').inner_text()).startswith('Archives:')
   assert data['excerpt'] not in await page.locator('main').inner_html(),slug
   if layout=='shows': await expect(page.locator('.archive-card')).to_contain_text(['3 seasons'])
   if layout=='episodes': assert await page.locator('.archive-card img').count()==0
   await page.set_viewport_size({'width':390,'height':844})
   assert await page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),slug
   assert await page.locator('.catalog-filter-form').is_visible()
   if layout in ('movies','shows','directory','games'): await page.screenshot(path='/tmp/msc-archive-'+layout+'-mobile.png',full_page=True)
   await page.set_viewport_size({'width':1440,'height':1100})
  colors=[]
  for site,slug in [('a','movies'),('b','movies'),('c','games'),('d','games')]:
   await page.goto(url(site,slug)); colors.append(await page.locator('.archive-hero').evaluate('(el)=>getComputedStyle(el).backgroundImage'))
   if site=='b': await expect(page.locator('.archive-card-facts')).to_contain_text(['Mar 4, 2095'])
   if site=='d':
    await expect(page.locator('.archive-card-title')).to_have_text([data['game']['data']['title']])
    assert 'PC (Microsoft Windows)' not in await page.locator('#catalog-archive').inner_text()
    await expect(page.locator('.archive-card-facts')).to_contain_text(['Feb 2, 2094'])
  assert len(set(colors))==4
  await page.goto(url('a','genre/science-fiction')); await expect(page.locator('main h1')).to_have_text('Science Fiction')
  assert data['excerpt'] not in await page.locator('main').inner_html()
  print('PASS: All twelve content-type archives, taxonomy pages, distinct site colors, useful metadata and mobile layouts without excerpts.',flush=True)

  nojs=await browser.new_context(java_script_enabled=False,viewport={'width':1280,'height':1000},reduced_motion='reduce'); await nojs.route('**/*',local_only)
  fallback=await nojs.new_page(); await fallback.goto(movies); await fallback.get_by_role('link',name='Next',exact=True).click(); await expect(fallback.locator('.archive-card')).to_have_count(1)
  await fallback.fill('#catalog-query',data['prefix']); await fallback.select_option('#catalog-facet','Drama'); await fallback.select_option('#catalog-year','2091'); await fallback.get_by_role('button',name='Apply filters',exact=True).click(); await expect(fallback.locator('.archive-card')).to_have_count(10)
  await nojs.close()
  assert not errors,errors
  await browser.close()
  print('PASS: Stale response protection and ordinary filter/pagination links with JavaScript disabled.',flush=True)

try: asyncio.run(main())
except Exception:
 report=traceback.format_exc()
 for key in ('WORDPRESS_ADMIN_PASSWORD','OMDB_API_KEY','IGDB_CLIENT_SECRET','SERPER_API_KEY'):
  if config.get(key): report=report.replace(config[key],'[redacted]')
 raise SystemExit(report)
